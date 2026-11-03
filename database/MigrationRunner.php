<?php
declare(strict_types=1);
namespace LifeOS\Database;

use PDO;
use RuntimeException;
use Throwable;

final class MigrationRunner
{
    private string $directory;
    private string $lockName='lifeos_migration_runner';
    public function __construct(private readonly PDO $pdo,?string $directory=null){$this->directory=$directory??BASE_PATH.'/database/migrations';}

    public function migrate(bool $retryFailed=false): array {
        $this->lock();
        try {
            $this->ensureTable();
            $records=$this->records();$results=[];
            foreach($this->files() as $name=>$path){
                $checksum=hash_file('sha256',$path);$record=$records[$name]??null;
                if($record&&$record['status']==='applied'){
                    if(!hash_equals($record['checksum'],$checksum))throw new RuntimeException('Checksum migration berubah: '.$name);
                    continue;
                }
                if($record&&$record['status']==='failed'&&!$retryFailed)throw new RuntimeException('Migration gagal sebelumnya: '.$name.'. Periksa kondisi database lalu jalankan migrate:retry '.$name);
                $migration=require $path;
                if(!is_array($migration)||!isset($migration['up'])||!is_callable($migration['up']))throw new RuntimeException('Format migration tidak valid: '.$name);
                $this->markRunning($name,$checksum);
                try{($migration['up'])($this->pdo);$this->markApplied($name);$results[]=['name'=>$name,'status'=>'applied'];}
                catch(Throwable $e){$this->markFailed($name,$e->getMessage());throw new RuntimeException('Migration '.$name.' gagal: '.$e->getMessage(),0,$e);}
            }
            return $results;
        } finally {$this->unlock();}
    }

    public function retry(string $name): array {
        $this->ensureTable();$records=$this->records();
        if(!isset($records[$name])||$records[$name]['status']!=='failed')throw new RuntimeException('Migration tidak ditemukan atau bukan berstatus failed: '.$name);
        return $this->migrate(true);
    }

    public function status(): array {
        $this->ensureTable();$records=$this->records();$rows=[];
        foreach($this->files() as $name=>$path){$r=$records[$name]??null;$sum=hash_file('sha256',$path);$rows[]=['name'=>$name,'status'=>$r['status']??'pending','checksum'=>$r?(hash_equals($r['checksum'],$sum)?'ok':'CHANGED'):'n/a','applied_at'=>$r['applied_at']??null,'error'=>$r['error_message']??null];}
        return $rows;
    }

    private function ensureTable(): void {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            migration VARCHAR(190) NOT NULL,
            checksum CHAR(64) NOT NULL,
            status ENUM('running','applied','failed') NOT NULL DEFAULT 'running',
            error_message TEXT NULL,
            started_at DATETIME NOT NULL,
            applied_at DATETIME NULL,
            PRIMARY KEY(id),
            UNIQUE KEY uq_schema_migrations_name(migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    private function files(): array {
        if(!is_dir($this->directory))throw new RuntimeException('Folder migration tidak ditemukan: '.$this->directory);
        $paths=glob($this->directory.'/*.php')?:[];sort($paths,SORT_STRING);$files=[];
        foreach($paths as $path){$name=basename($path,'.php');if(!preg_match('/^[0-9]{4,}_[a-z0-9_]+$/',$name))throw new RuntimeException('Nama migration harus NNNN_nama_file: '.$name);$files[$name]=$path;}
        return $files;
    }
    private function records(): array {
        $rows=$this->pdo->query('SELECT * FROM schema_migrations')->fetchAll(PDO::FETCH_ASSOC);$out=[];
        foreach($rows as $row)$out[$row['migration']]=$row;return $out;
    }
    private function markRunning(string $name,string $checksum): void {
        $q=$this->pdo->prepare('SELECT id FROM schema_migrations WHERE migration=:name');$q->execute(['name'=>$name]);$id=$q->fetchColumn();
        if($id){$q=$this->pdo->prepare("UPDATE schema_migrations SET checksum=:sum,status='running',error_message=NULL,started_at=UTC_TIMESTAMP(),applied_at=NULL WHERE id=:id");$q->execute(['sum'=>$checksum,'id'=>$id]);}
        else{$q=$this->pdo->prepare("INSERT INTO schema_migrations(migration,checksum,status,started_at) VALUES(:name,:sum,'running',UTC_TIMESTAMP())");$q->execute(['name'=>$name,'sum'=>$checksum]);}
    }
    private function markApplied(string $name): void {$q=$this->pdo->prepare("UPDATE schema_migrations SET status='applied',error_message=NULL,applied_at=UTC_TIMESTAMP() WHERE migration=:name");$q->execute(['name'=>$name]);}
    private function markFailed(string $name,string $error): void {$q=$this->pdo->prepare("UPDATE schema_migrations SET status='failed',error_message=:error WHERE migration=:name");$q->execute(['error'=>mb_substr($error,0,60000),'name'=>$name]);}
    private function lock(): void {$q=$this->pdo->query("SELECT GET_LOCK('".$this->lockName."',10)");if((int)$q->fetchColumn()!==1)throw new RuntimeException('Migration sedang dijalankan proses lain.');}
    private function unlock(): void {$this->pdo->query("SELECT RELEASE_LOCK('".$this->lockName."')");}
}
