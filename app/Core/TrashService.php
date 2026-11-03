<?php
declare(strict_types=1);
namespace LifeOS\Core;
use PDO;
use LifeOS\Core\Exceptions\NotFoundException;
use LifeOS\Core\Exceptions\ConflictException;
final class TrashService {
    public function __construct(private readonly PDO $pdo,private readonly int $profileId) {}
    public function register(string $type,int $id,?array $metadata=null): void {
        $this->assertRef($type,$id);
        $q=$this->pdo->prepare("INSERT INTO trash_items(profile_id,entity_type,entity_id,deleted_at,restore_status,metadata) VALUES(:p,:t,:i,UTC_TIMESTAMP(),'trashed',:m) ON DUPLICATE KEY UPDATE deleted_at=VALUES(deleted_at),restore_status='trashed',metadata=VALUES(metadata)");
        $q->execute(['p'=>$this->profileId,'t'=>$type,'i'=>$id,'m'=>$metadata?json_encode($metadata,JSON_UNESCAPED_UNICODE):null]);
    }
    public function list(int $limit=100): array {$limit=max(1,min(500,$limit));$q=$this->pdo->prepare("SELECT id,entity_type,entity_id,deleted_at,metadata FROM trash_items WHERE profile_id=:p AND restore_status='trashed' ORDER BY deleted_at DESC LIMIT {$limit}");$q->execute(['p'=>$this->profileId]);return $q->fetchAll(PDO::FETCH_ASSOC);}
    public function restore(string $type,int $id,callable $restoreCallback): mixed {
        $this->assertRef($type,$id);
        return Database::transaction(function(PDO $pdo)use($type,$id,$restoreCallback){
            $q=$pdo->prepare("SELECT id FROM trash_items WHERE profile_id=:p AND entity_type=:t AND entity_id=:i AND restore_status='trashed' FOR UPDATE");
            $q->execute(['p'=>$this->profileId,'t'=>$type,'i'=>$id]);$trashId=$q->fetchColumn();
            if($trashId===false)throw new NotFoundException('Item tidak ditemukan di trash.');
            $result=$restoreCallback($pdo,$id);
            $u=$pdo->prepare("UPDATE trash_items SET restore_status='restored',restored_at=UTC_TIMESTAMP() WHERE id=:id");$u->execute(['id'=>$trashId]);return $result;
        });
    }
    public function markPurged(string $type,int $id): void {$q=$this->pdo->prepare("UPDATE trash_items SET restore_status='purged',purged_at=UTC_TIMESTAMP() WHERE profile_id=:p AND entity_type=:t AND entity_id=:i AND restore_status='trashed'");$q->execute(['p'=>$this->profileId,'t'=>$type,'i'=>$id]);if(!$q->rowCount())throw new ConflictException('Item tidak berada di trash.');}
    private function assertRef(string $type,int $id): void {if(!preg_match('/^[a-z][a-z0-9_]{1,49}$/',$type)||$id<1)throw new ConflictException('Referensi trash tidak valid.');}
}
