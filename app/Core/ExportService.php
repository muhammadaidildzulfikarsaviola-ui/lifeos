<?php
declare(strict_types=1);
namespace LifeOS\Core;
use PDO;
final class ExportService {
 public function __construct(private readonly PDO $pdo,private readonly int $profileId,private readonly CsvService $csv) {}
 public function csv(string $table,array $columns,array $filters=[]): array {
  $allowed=['finance_transactions'=>['id','type','amount','description','transaction_date'],'tasks'=>['id','title','status','priority','due_date'],'habits'=>['id','name','frequency','is_active'],'journals'=>['id','title','mood','journal_date']];
  if(!isset($allowed[$table])||!$columns)throw new \InvalidArgumentException('Target export tidak diizinkan.');
  foreach($columns as $c)if(!in_array($c,$allowed[$table],true))throw new \InvalidArgumentException('Kolom export tidak diizinkan.');
  $tick=chr(96);$qi=static fn($v)=>$tick.str_replace($tick,$tick.$tick,$v).$tick;
  $sql='SELECT '.implode(',',array_map($qi,$columns)).' FROM '.$qi($table).' WHERE profile_id=:profile';$params=['profile'=>$this->profileId];
  if(isset($filters['from'],$filters['to'])&&in_array('transaction_date',$allowed[$table],true)){$sql.=' AND transaction_date BETWEEN :from AND :to';$params['from']=$filters['from'];$params['to']=$filters['to'];}
  $sql.=' ORDER BY id ASC';$q=$this->pdo->prepare($sql);$q->execute($params);$path=Storage::path('exports','lifeos_export_'.bin2hex(random_bytes(12)).'.csv');$count=$this->csv->write($path,$columns,$q);
  return ['path'=>$path,'filename'=>basename($path),'rows'=>$count,'expires_at'=>gmdate('c',time()+86400)];
 }
}
