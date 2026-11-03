<?php
declare(strict_types=1);
namespace LifeOS\Core;
use PDO;
final class NotificationQueue {
 public function __construct(private readonly PDO $pdo,private readonly int $profileId) {}
 public function enqueue(string $sourceType,int $sourceId,string $occurrence,string $type,string $title,?string $body=null,?string $deliverAt=null): int {
  $q=$this->pdo->prepare("INSERT INTO notifications(profile_id,source_type,source_id,occurrence_key,notification_type,title,body,status,deliver_at,created_at) VALUES(:p,:st,:si,:ok,:nt,:title,:body,'pending',COALESCE(:at,UTC_TIMESTAMP()),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
  $q->execute(['p'=>$this->profileId,'st'=>$sourceType,'si'=>$sourceId,'ok'=>$occurrence,'nt'=>$type,'title'=>$title,'body'=>$body,'at'=>$deliverAt]);return (int)$this->pdo->lastInsertId();
 }
 public function claim(int $limit=20,int $lease=120): array {
  $limit=max(1,min(100,$limit));$lease=max(30,min(3600,$lease));$token=bin2hex(random_bytes(16));
  return Database::transaction(function(PDO $pdo)use($limit,$lease,$token){$q=$pdo->prepare("SELECT id FROM notifications WHERE profile_id=:p AND status='pending' AND deliver_at<=UTC_TIMESTAMP() ORDER BY deliver_at,id LIMIT {$limit} FOR UPDATE");$q->execute(['p'=>$this->profileId]);$ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));if(!$ids)return [];
   $marks=implode(',',array_fill(0,count($ids),'?'));$u=$pdo->prepare("UPDATE notifications SET status='processing',claim_token=?,claimed_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL {$lease} SECOND),attempts=attempts+1 WHERE profile_id=? AND status='pending' AND id IN ({$marks})");$u->execute(array_merge([$token,$this->profileId],$ids));
   $s=$pdo->prepare("SELECT id,title,body,notification_type,source_type,source_id,occurrence_key,claim_token FROM notifications WHERE profile_id=? AND status='processing' AND claim_token=? ORDER BY id");$s->execute([$this->profileId,$token]);return $s->fetchAll(PDO::FETCH_ASSOC);
  });
 }
 public function complete(int $id,string $token,string $channel): void {Database::transaction(function(PDO $pdo)use($id,$token,$channel){$q=$pdo->prepare("INSERT INTO notification_deliveries(notification_id,channel,status,delivered_at) SELECT id,:c,'sent',UTC_TIMESTAMP() FROM notifications WHERE id=:id AND profile_id=:p AND status='processing' AND claim_token=:t");$q->execute(['c'=>$channel,'id'=>$id,'p'=>$this->profileId,'t'=>$token]);$u=$pdo->prepare("UPDATE notifications SET status='sent',sent_at=UTC_TIMESTAMP(),claim_token=NULL,claimed_until=NULL WHERE id=:id AND profile_id=:p AND status='processing' AND claim_token=:t");$u->execute(['id'=>$id,'p'=>$this->profileId,'t'=>$token]);});}
 public function fail(int $id,string $token,string $code,int $max=5): void {$q=$this->pdo->prepare("UPDATE notifications SET status=IF(attempts>=:m,'failed','pending'),last_error_code=:e,claim_token=NULL,claimed_until=NULL WHERE id=:id AND profile_id=:p AND status='processing' AND claim_token=:t");$q->execute(['m'=>max(1,$max),'e'=>substr($code,0,80),'id'=>$id,'p'=>$this->profileId,'t'=>$token]);}
 public function recoverExpired(): int {$q=$this->pdo->prepare("UPDATE notifications SET status=IF(attempts>=5,'failed','pending'),claim_token=NULL,claimed_until=NULL,last_error_code='CLAIM_EXPIRED' WHERE profile_id=:p AND status='processing' AND claimed_until<UTC_TIMESTAMP()");$q->execute(['p'=>$this->profileId]);return $q->rowCount();}
}
