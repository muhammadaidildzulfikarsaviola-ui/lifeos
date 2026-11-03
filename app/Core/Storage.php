<?php
declare(strict_types=1);
namespace LifeOS\Core;
use LifeOS\Core\Exceptions\StorageException;
final class Storage {
    private const AREAS=['attachments','backups','exports','imports','logs','temporary'];
    public static function path(string $area,string $relative=''): string {
        if(!in_array($area,self::AREAS,true)) throw new StorageException('Storage area tidak dikenal.');
        if(str_contains($relative,"\0")||preg_match('/^[a-zA-Z]:/',$relative)||str_starts_with($relative,'/')||str_starts_with($relative,'\\')) throw new StorageException('Storage path tidak valid.');
        $parts=$relative===''?[]:preg_split('#[\\\\/]#',$relative);
        foreach($parts as $part) if($part===''||$part==='.'||$part==='..') throw new StorageException('Storage path traversal ditolak.');
        $root=BASE_PATH.'/storage'; $path=$root.'/'.$area.($parts?'/'.implode('/',$parts):'');
        $resolved=realpath(dirname($path));
        if($resolved!==false && !str_starts_with(str_replace('\\','/',$resolved),str_replace('\\','/',$root))) throw new StorageException('Storage path keluar dari root.');
        return $path;
    }
    public static function ensure(string $area): string {
        $path=self::path($area);
        if(!is_dir($path)&&!mkdir($path,0770,true)&&!is_dir($path)) throw new StorageException('Direktori storage gagal dibuat.');
        if(!is_writable($path)) throw new StorageException('Direktori storage tidak dapat ditulis.');
        return $path;
    }
    public static function initialize(): array {$out=[];foreach(self::AREAS as $area)$out[$area]=self::ensure($area);return $out;}
    public static function areas(): array{return self::AREAS;}
}
