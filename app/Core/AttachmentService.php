<?php
declare(strict_types=1);
namespace LifeOS\Core;
use PDO;
use Throwable;
use LifeOS\Core\Exceptions\NotFoundException;
use LifeOS\Core\Exceptions\StorageException;
use LifeOS\Core\Exceptions\ValidationException;
final class AttachmentService {
 private const EXT=['jpg','jpeg','png','webp','pdf','docx','xlsx','txt','zip'];
 public function __construct(private readonly PDO $pdo,private readonly int $profileId,private readonly int $maxBytes=52428800) {}
 public function store(array $file): array {
  if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new ValidationException(['file'=>['Upload gagal atau file belum dipilih.']]);
  $tmp=(string)($file['tmp_name']??'');$name=basename((string)($file['name']??''));$size=(int)($file['size']??0);$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
  if(!$tmp||!is_uploaded_file($tmp))throw new ValidationException(['file'=>['File upload tidak valid.']]);
  if($size<1||$size>$this->maxBytes)throw new ValidationException(['file'=>['Ukuran file melebihi batas 50 MB.']]);
  if(!in_array($ext,self::EXT,true))throw new ValidationException(['file'=>['Ekstensi file tidak diizinkan.']]);
  $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($tmp)?:'application/octet-stream';
  if(!$this->valid($tmp,$ext,$mime))throw new ValidationException(['file'=>['Isi file tidak sesuai ekstensi yang diizinkan.']]);
  $stored=bin2hex(random_bytes(16)).'.'.$ext;$relative=gmdate('Y/m').'/'.$stored;$target=Storage::path('attachments',$relative);$dir=dirname($target);
  if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new StorageException('Folder attachment gagal dibuat.');
  if(!move_uploaded_file($tmp,$target))throw new StorageException('File gagal disimpan.');
  try{$q=$this->pdo->prepare("INSERT INTO attachments(profile_id,uuid,original_filename,stored_filename,storage_path,mime_type,extension,file_size,checksum,upload_status,created_at) VALUES(:p,:u,:n,:s,:path,:m,:e,:z,:c,'ready',UTC_TIMESTAMP())");$q->execute(['p'=>$this->profileId,'u'=>self::uuid(),'n'=>$name,'s'=>$stored,'path'=>$relative,'m'=>$mime,'e'=>$ext,'z'=>$size,'c'=>hash_file('sha256',$target)]);return ['id'=>(int)$this->pdo->lastInsertId(),'filename'=>$name,'mime_type'=>$mime,'size'=>$size];}
  catch(Throwable $e){@unlink($target);throw $e;}
 }
 public function find(int $id): array {$q=$this->pdo->prepare("SELECT * FROM attachments WHERE id=:id AND profile_id=:p AND upload_status='ready' AND deleted_at IS NULL");$q->execute(['id'=>$id,'p'=>$this->profileId]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new NotFoundException('Attachment tidak ditemukan.');$path=Storage::path('attachments',(string)$r['storage_path']);if(!is_file($path)||!hash_equals((string)$r['checksum'],hash_file('sha256',$path)))throw new StorageException('File hilang atau checksum tidak cocok.');$r['_absolute_path']=$path;return $r;}
 public function attach(int $id,string $type,int $entityId): void {$this->find($id);if(!preg_match('/^[a-z][a-z0-9_]{1,49}$/',$type)||$entityId<1)throw new ValidationException(['entity'=>['Referensi tidak valid.']]);$q=$this->pdo->prepare('INSERT IGNORE INTO entity_attachments(profile_id,attachment_id,entity_type,entity_id,created_at) VALUES(:p,:a,:t,:i,UTC_TIMESTAMP())');$q->execute(['p'=>$this->profileId,'a'=>$id,'t'=>$type,'i'=>$entityId]);}
 public function softDelete(int $id): void {$q=$this->pdo->prepare('UPDATE attachments SET deleted_at=UTC_TIMESTAMP() WHERE id=:id AND profile_id=:p AND deleted_at IS NULL');$q->execute(['id'=>$id,'p'=>$this->profileId]);if(!$q->rowCount())throw new NotFoundException('Attachment tidak ditemukan.');}
 public function purge(int $id): void {$q=$this->pdo->prepare('SELECT storage_path FROM attachments WHERE id=:id AND profile_id=:p AND deleted_at IS NOT NULL');$q->execute(['id'=>$id,'p'=>$this->profileId]);$rel=$q->fetchColumn();if($rel===false)throw new NotFoundException('Attachment tidak berada di trash.');$q=$this->pdo->prepare('SELECT COUNT(*) FROM entity_attachments WHERE attachment_id=:id');$q->execute(['id'=>$id]);if((int)$q->fetchColumn())throw new StorageException('Attachment masih terhubung.');$path=Storage::path('attachments',(string)$rel);if(is_file($path)&&!unlink($path))throw new StorageException('File gagal dihapus.');$q=$this->pdo->prepare('DELETE FROM attachments WHERE id=:id AND profile_id=:p');$q->execute(['id'=>$id,'p'=>$this->profileId]);}
 private function valid(string $path,string $ext,string $mime): bool {$m=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','pdf'=>'application/pdf','txt'=>'text/plain'];if(isset($m[$ext]))return $mime===$m[$ext]||($ext==='txt'&&$mime==='application/octet-stream');if(!in_array($mime,['application/zip','application/x-zip','application/x-zip-compressed'],true)||!class_exists(\ZipArchive::class))return false;$z=new \ZipArchive();if($z->open($path)!==true)return false;$ok=$ext==='zip'||($ext==='docx'?$z->locateName('word/document.xml')!==false:$z->locateName('xl/workbook.xml')!==false);$z->close();return $ok;}
 private static function uuid(): string {$b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);$h=bin2hex($b);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);}
}
