<?php
declare(strict_types=1);
namespace LifeOS\Core;
use RuntimeException;
final class MaintenanceLock {
 private bool $held=false;
 public function __construct(private readonly string $name='lifeos_maintenance') {if(!preg_match('/^[A-Za-z0-9_]{1,64}$/',$name))throw new RuntimeException('Nama lock tidak valid.');}
 public function acquire(int $timeout=0): void {if($this->held)return;$q=Database::connection()->prepare('SELECT GET_LOCK(:name,:timeout)');$q->execute(['name'=>$this->name,'timeout'=>max(0,min(60,$timeout))]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Proses maintenance lain sedang berjalan.');$this->held=true;}
 public function release(): void {if(!$this->held)return;try{$q=Database::connection()->prepare('SELECT RELEASE_LOCK(:name)');$q->execute(['name'=>$this->name]);}finally{$this->held=false;}}
 public function synchronized(callable $callback,int $timeout=0): mixed {$this->acquire($timeout);try{return $callback();}finally{$this->release();}}
 public function __destruct(){try{$this->release();}catch(\Throwable){}}
}
