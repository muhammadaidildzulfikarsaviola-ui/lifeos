<?php
declare(strict_types=1);
namespace LifeOS\Core;
use PDO;
use LifeOS\Core\Exceptions\StorageException;
use LifeOS\Core\Exceptions\ValidationException;
final class BackupService {
 public function __construct(private readonly PDO $pdo,private readonly string $databaseName,private readonly string $version='0.1.0') {}
 public function createFull(): array {
  if(!class_exists(\ZipArchive::class))throw new StorageException('Ekstensi zip belum aktif.');
  $lock=new MaintenanceLock('lifeos_backup_restore');$lock->acquire();$tmp=null;$sqlPath=null;
  try {
   $dir=Storage::ensure('backups');$name='LIFEOS_backup_'.gmdate('Y-m-d_His').'_'.bin2hex(random_bytes(3));$tmp=$dir.'/'.$name.'.tmp.zip';$final=$dir.'/'.$name.'.zip';
   $sql=$this->dump();$sqlPath=Storage::path('temporary',$name.'.sql');if(file_put_contents($sqlPath,$sql,LOCK_EX)===false)throw new StorageException('Dump database gagal ditulis.');
   $zip=new \ZipArchive();if($zip->open($tmp,\ZipArchive::CREATE|\ZipArchive::OVERWRITE)!==true)throw new StorageException('ZIP backup gagal dibuat.');
   $zip->addFile($sqlPath,'database/lifeos.sql');$count=0;$bytes=0;
   $q=$this->pdo->query("SELECT storage_path,checksum FROM attachments WHERE upload_status='ready' AND deleted_at IS NULL");
   foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){$rel=(string)$row['storage_path'];$path=Storage::path('attachments',$rel);if(!is_file($path)||!hash_equals((string)$row['checksum'],hash_file('sha256',$path)))throw new StorageException('Attachment hilang atau checksum salah.');$zip->addFile($path,'attachments/'.$rel);$count++;$bytes+=(int)filesize($path);}
   $manifest=['format'=>'LIFEOS_FULL_BACKUP','format_version'=>1,'application_version'=>$this->version,'database'=>$this->databaseName,'database_engine'=>(string)$this->pdo->query('SELECT VERSION()')->fetchColumn(),'created_at_utc'=>gmdate('c'),'attachment_count'=>$count,'attachment_bytes'=>$bytes,'database_sha256'=>hash('sha256',$sql)];
   $zip->addFromString('manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));$zip->addFromString('checksums.sha256',hash('sha256',$sql).'  database/lifeos.sql'.PHP_EOL);
   if(!$zip->close())throw new StorageException('ZIP backup gagal ditutup.');
   @unlink($sqlPath);$sqlPath=null;$check=$this->verify($tmp);if(!$check['valid'])throw new StorageException('Verifikasi backup gagal: '.$check['reason']);
   if(!rename($tmp,$final))throw new StorageException('Gagal menyimpan backup final.');
   return ['filename'=>basename($final),'path'=>$final,'size'=>(int)filesize($final),'manifest'=>$manifest,'verification'=>$check];
  } catch(\Throwable $e){if($tmp&&is_file($tmp))@unlink($tmp);if($sqlPath&&is_file($sqlPath))@unlink($sqlPath);throw $e;}
  finally {$lock->release();}
 }
 public function verify(string $path): array {
  if(!class_exists(\ZipArchive::class))return ['valid'=>false,'reason'=>'ZIP_EXTENSION_MISSING'];
  $zip=new \ZipArchive();if($zip->open($path)!==true)return ['valid'=>false,'reason'=>'INVALID_ZIP'];
  try {
   $raw=$zip->getFromName('manifest.json');$sql=$zip->getFromName('database/lifeos.sql');if($raw===false||$sql===false)return ['valid'=>false,'reason'=>'REQUIRED_ENTRY_MISSING'];
   $m=json_decode($raw,true);if(!is_array($m)||($m['format']??'')!=='LIFEOS_FULL_BACKUP'||($m['format_version']??0)!==1)return ['valid'=>false,'reason'=>'INVALID_MANIFEST'];
   if(($m['database']??'')!==$this->databaseName)return ['valid'=>false,'reason'=>'DATABASE_MISMATCH'];
   if(!hash_equals((string)($m['database_sha256']??''),hash('sha256',$sql)))return ['valid'=>false,'reason'=>'CHECKSUM_MISMATCH'];
   if($zip->numFiles>100000)return ['valid'=>false,'reason'=>'ENTRY_LIMIT'];$total=0;
   for($i=0;$i<$zip->numFiles;$i++){$s=$zip->statIndex($i);if(!$s)continue;$n=(string)$s['name'];if(str_contains($n,"\0")||str_starts_with($n,'/')||preg_match('/^[a-zA-Z]:/',$n)||preg_match('#(^|/)\.\.(/|$)#',$n))return ['valid'=>false,'reason'=>'UNSAFE_PATH'];$total+=(int)$s['size'];if($total>2147483648)return ['valid'=>false,'reason'=>'EXPANSION_LIMIT'];}
   return ['valid'=>true,'manifest'=>$m,'entries'=>$zip->numFiles,'uncompressed_bytes'=>$total];
  } finally {$zip->close();}
 }
 public function restorePreview(string $path): array {$v=$this->verify($path);if(!$v['valid'])throw new ValidationException(['backup'=>['Backup tidak valid: '.$v['reason']]]);return ['valid'=>true,'mode'=>'replace','confirmation_phrase'=>'RESTORE LIFEOS','safety_backup_required'=>true,'execution_enabled'=>false,'manifest'=>$v['manifest'],'warning'=>'Preview tidak mengubah database. Restore execution belum tersedia.'];}
 private function dump(): string {
  $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$this->pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
  try {$out="SET FOREIGN_KEY_CHECKS=0;\n";$tables=$this->pdo->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
   foreach($tables as $t){$n=(string)$t[0];if(!preg_match('/^[a-zA-Z0-9_]+$/',$n))continue;$id=$this->qi($n);$create=$this->pdo->query("SHOW CREATE TABLE {$id}")->fetch(PDO::FETCH_NUM);$out.="DROP TABLE IF EXISTS {$id};\n".$create[1].";\n";$rows=$this->pdo->query("SELECT * FROM {$id}");while($r=$rows->fetch(PDO::FETCH_ASSOC)){$cols=array_map(fn($c)=>$this->qi((string)$c),array_keys($r));$vals=array_map(fn($v)=>$v===null?'NULL':$this->pdo->quote((string)$v),array_values($r));$out.='INSERT INTO '.$id.' ('.implode(',',$cols).') VALUES ('.implode(',',$vals).");\n";}}
   $this->pdo->commit();return $out."SET FOREIGN_KEY_CHECKS=1;\n";
  } catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
 }
 private function qi(string $s): string {$tick=chr(96);return $tick.str_replace($tick,$tick.$tick,$s).$tick;}
}
