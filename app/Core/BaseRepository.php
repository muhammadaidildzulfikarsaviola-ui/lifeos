<?php
declare(strict_types=1);
namespace LifeOS\Core;

use PDO;
use PDOStatement;

abstract class BaseRepository
{
    public function __construct(protected readonly PDO $pdo) {}

    protected function fetchOne(string $sql,array $params=[]): ?array {
        $statement=$this->execute($sql,$params);
        $row=$statement->fetch(PDO::FETCH_ASSOC);
        return $row===false?null:$row;
    }

    protected function fetchAll(string $sql,array $params=[]): array {
        return $this->execute($sql,$params)->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function execute(string $sql,array $params=[]): PDOStatement {
        $statement=$this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    protected function lastInsertId(): int { return (int)$this->pdo->lastInsertId(); }
}
