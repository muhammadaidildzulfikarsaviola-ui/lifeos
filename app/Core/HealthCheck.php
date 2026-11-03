<?php
declare(strict_types=1);
namespace LifeOS\Core;

use PDO;
use Throwable;

final class HealthCheck
{
    public function run(bool $includeDetails=false): array {
        $checks=[];
        $checks['php']=['status'=>version_compare(PHP_VERSION,'8.2.0','>=' )?'ok':'fail','version'=>PHP_VERSION,'required'=>'8.2+'];
        foreach(['pdo','pdo_mysql','mbstring','fileinfo','json','zip'] as $extension){
            $checks['extension_'.$extension]=['status'=>extension_loaded($extension)?'ok':'fail'];
        }
        try {
            $pdo=Database::connection();
            $checks['database']=['status'=>'ok','engine'=>$this->databaseVersion($pdo)];
            $checks['database_connection']=['status'=>'ok'];
        } catch(Throwable $e) {
            $checks['database']=['status'=>'fail','message'=>'Database tidak dapat dihubungi.'];
            $checks['database_connection']=['status'=>'fail'];
        }
        foreach(Storage::areas() as $area){
            try{$path=Storage::ensure($area);$checks['storage_'.$area]=['status'=>'ok','writable'=>is_writable($path)];}
            catch(Throwable){$checks['storage_'.$area]=['status'=>'fail','writable'=>false];}
        }
        if(isset($pdo)&&$pdo instanceof PDO){
            try {
                $rows=(new \LifeOS\Database\MigrationRunner($pdo))->status();
                $pending=array_filter($rows,static fn(array $r):bool=>$r['status']!=='applied'||$r['checksum']!=='ok');
                $checks['migrations']=['status'=>$pending===[]?'ok':'warning','total'=>count($rows),'pending_or_changed'=>count($pending)];
            } catch(Throwable) {$checks['migrations']=['status'=>'warning','message'=>'Status migration belum dapat diperiksa.'];}
            try {
                $checks['profile']=['status'=>(int)$pdo->query('SELECT COUNT(*) FROM profiles')->fetchColumn()>0?'ok':'warning'];
            } catch(Throwable) {$checks['profile']=['status'=>'warning','message'=>'Tabel profile belum tersedia.'];}
            $checks['database_disk']=['status'=>'ok','free_bytes'=>disk_free_space(BASE_PATH)?:null];
        }
        $failed=array_filter($checks,static fn(array $c):bool=>$c['status']==='fail');
        $warnings=array_filter($checks,static fn(array $c):bool=>$c['status']==='warning');
        return [
            'status'=>$failed!==[]?'fail':($warnings!==[]?'warning':'ok'),
            'checked_at'=>gmdate('c'),
            'checks'=>$checks,
            'summary'=>['total'=>count($checks),'failed'=>count($failed),'warnings'=>count($warnings)]
        ];
    }

    private function databaseVersion(PDO $pdo): string {
        return (string)$pdo->query('SELECT VERSION()')->fetchColumn();
    }
}
