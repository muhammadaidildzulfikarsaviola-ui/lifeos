<?php
declare(strict_types=1);
namespace LifeOS\Core;
use PDO;
use LifeOS\Core\Exceptions\NotFoundException;
final class ImportJobService {
    public function __construct(private readonly PDO $pdo,private readonly int $profileId) {}
    public function create(string $type,string $filename,int $size,string $storagePath): int {
        $q=$this->pdo->prepare("INSERT INTO import_jobs(profile_id,uuid,import_type,source_filename,source_path,file_size,status,created_at) VALUES(:p,:u,:t,:f,:s,:z,'uploaded',UTC_TIMESTAMP())");
        $q->execute(['p'=>$this->profileId,'u'=>self::uuid(),'t'=>$type,'f'=>basename($filename),'s'=>$storagePath,'z'=>$size]);return (int)$this->pdo->lastInsertId();
    }
    public function addRow(int $jobId,int $rowNumber,array $raw,array $normalized,?array $errors=null): void {
        $this->assertJob($jobId);$q=$this->pdo->prepare('INSERT INTO import_job_rows(import_job_id,row_number,raw_data,normalized_data,validation_errors,processing_status) VALUES(:j,:r,:raw,:norm,:err,:status)');
        $q->execute(['j'=>$jobId,'r'=>$rowNumber,'raw'=>json_encode($raw,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),'norm'=>json_encode($normalized,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),'err'=>$errors?json_encode($errors,JSON_UNESCAPED_UNICODE):null,'status'=>$errors?'invalid':'valid']);$this->counts($jobId);
    }
    public function preview(int $jobId,int $limit=100): array {$this->assertJob($jobId);$limit=max(1,min(500,$limit));$q=$this->pdo->prepare("SELECT row_number,normalized_data,validation_errors,processing_status FROM import_job_rows WHERE import_job_id=:j ORDER BY row_number LIMIT {$limit}");$q->execute(['j'=>$jobId]);return $q->fetchAll(PDO::FETCH_ASSOC);}
    public function markCommitted(int $jobId,array $rows): void {
        $this->assertJob($jobId);Database::transaction(function(PDO $pdo)use($jobId,$rows){$q=$pdo->prepare("UPDATE import_job_rows SET processing_status='committed',committed_at=UTC_TIMESTAMP() WHERE import_job_id=:j AND row_number=:r AND processing_status='valid'");foreach(array_unique(array_map('intval',$rows)) as $row)$q->execute(['j'=>$jobId,'r'=>$row]);$this->counts($jobId);$pdo->prepare("UPDATE import_jobs SET status='completed',completed_at=UTC_TIMESTAMP() WHERE id=:j AND profile_id=:p")->execute(['j'=>$jobId,'p'=>$this->profileId]);});
    }
    public function fail(int $jobId,string $code): void {$this->assertJob($jobId);$q=$this->pdo->prepare("UPDATE import_jobs SET status='failed',error_code=:e,completed_at=UTC_TIMESTAMP() WHERE id=:j AND profile_id=:p");$q->execute(['e'=>substr($code,0,80),'j'=>$jobId,'p'=>$this->profileId]);}
    private function assertJob(int $id): void {$q=$this->pdo->prepare('SELECT id FROM import_jobs WHERE id=:j AND profile_id=:p');$q->execute(['j'=>$id,'p'=>$this->profileId]);if(!$q->fetchColumn())throw new NotFoundException('Import job tidak ditemukan.');}
    private function counts(int $id): void {$q=$this->pdo->prepare("UPDATE import_jobs SET total_rows=(SELECT COUNT(*) FROM import_job_rows WHERE import_job_id=:a),valid_rows=(SELECT COUNT(*) FROM import_job_rows WHERE import_job_id=:b AND processing_status IN ('valid','committed')),invalid_rows=(SELECT COUNT(*) FROM import_job_rows WHERE import_job_id=:c AND processing_status='invalid') WHERE id=:d");$q->execute(['a'=>$id,'b'=>$id,'c'=>$id,'d'=>$id]);}
    private static function uuid(): string {$b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);$h=bin2hex($b);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);}
}
