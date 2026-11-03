<?php
declare(strict_types=1);
namespace LifeOS\Core;
use LifeOS\Core\Exceptions\ValidationException;
use LifeOS\Core\Exceptions\StorageException;
final class CsvService {
    public function parse(string $path,int $maxRows=100000): array {
        if(!is_file($path)||!is_readable($path))throw new ValidationException(['file'=>['CSV tidak dapat dibaca.']]);
        $h=fopen($path,'rb');if(!$h)throw new StorageException('CSV gagal dibuka.');
        try {
            $first=fgets($h);if($first===false)throw new ValidationException(['file'=>['CSV kosong.']]);
            $delimiter=$this->delimiter($first);rewind($h);
            $headers=fgetcsv($h,0,$delimiter,'"','\\');if(!$headers)throw new ValidationException(['file'=>['Header CSV tidak ditemukan.']]);
            $headers[0]=preg_replace('/^\xEF\xBB\xBF/','',(string)$headers[0]);$headers=array_map(static fn($v)=>trim((string)$v),$headers);
            if(in_array('', $headers,true)||count(array_unique($headers))!==count($headers))throw new ValidationException(['file'=>['Header kosong atau duplikat.']]);
            $rows=[];$n=1;
            while(($row=fgetcsv($h,0,$delimiter,'"','\\'))!==false){$n++;if($n>$maxRows+1)throw new ValidationException(['file'=>['CSV melewati batas baris.']]);if($row===[null])continue;
                if(count($row)!==count($headers)){$rows[]=['row_number'=>$n,'data'=>null,'errors'=>['COLUMN_COUNT_MISMATCH']];continue;}
                $rows[]=['row_number'=>$n,'data'=>array_combine($headers,$row),'errors'=>[]];
            }
            return ['headers'=>$headers,'rows'=>$rows,'delimiter'=>$delimiter];
        } finally {fclose($h);}
    }
    public function write(string $path,array $headers,iterable $rows): int {
        $dir=dirname($path);if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new StorageException('Folder export gagal dibuat.');
        $h=fopen($path,'xb');if(!$h)throw new StorageException('File export tidak dapat dibuat.');
        $count=0;
        try {fwrite($h,"\xEF\xBB\xBF");fputcsv($h,$headers,',','"','\\');foreach($rows as $row){$cells=[];foreach($headers as $key)$cells[]=$this->safe($row[$key]??'');fputcsv($h,$cells,',','"','\\');$count++;}fflush($h);}
        catch(\Throwable $e){fclose($h);@unlink($path);throw $e;}fclose($h);return $count;
    }
    private function safe(mixed $v): string {$s=is_scalar($v)||$v===null?(string)$v:json_encode($v,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);return preg_match('/^[\s\x00-\x20]*[=+@\-]/u',$s)?"'".$s:$s;}
    private function delimiter(string $line): string {$counts=[','=>substr_count($line,','),';'=>substr_count($line,';'),"\t"=>substr_count($line,"\t"),'|'=>substr_count($line,'|')];arsort($counts);return (string)array_key_first($counts);}
}
