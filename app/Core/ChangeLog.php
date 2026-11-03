<?php
declare(strict_types=1);
namespace LifeOS\Core;
use PDO;
final class ChangeLog {
    public function __construct(private readonly PDO $pdo,private readonly int $profileId) {}
    public function record(string $entityType,int $entityId,string $action,?array $before=null,?array $after=null): void {
        $q=$this->pdo->prepare('INSERT INTO change_logs(profile_id,entity_type,entity_id,action,before_data,after_data,request_id,created_at) VALUES(:p,:t,:i,:a,:b,:n,:r,UTC_TIMESTAMP())');
        $q->execute(['p'=>$this->profileId,'t'=>$entityType,'i'=>$entityId,'a'=>$action,'b'=>$before===null?null:json_encode($before,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),'n'=>$after===null?null:json_encode($after,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),'r'=>RequestContext::id()]);
    }
    public function history(string $entityType,int $entityId,int $limit=50): array {
        $limit=max(1,min(200,$limit));$q=$this->pdo->prepare("SELECT id,action,before_data,after_data,request_id,created_at FROM change_logs WHERE profile_id=:p AND entity_type=:t AND entity_id=:i ORDER BY id DESC LIMIT {$limit}");
        $q->execute(['p'=>$this->profileId,'t'=>$entityType,'i'=>$entityId]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
