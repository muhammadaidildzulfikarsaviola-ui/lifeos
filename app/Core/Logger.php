<?php
declare(strict_types=1);
namespace LifeOS\Core;

final class Logger
{
    public static function log(string $level,string $message,array $context=[]): void {
        $dir=BASE_PATH.'/storage/logs'; if(!is_dir($dir))mkdir($dir,0775,true);
        foreach(['password','token','csrf','session','authorization'] as $key)unset($context[$key]);
        $line='['.gmdate('Y-m-d\TH:i:s\Z').'] '.strtoupper($level).' '.$message.' '.json_encode($context,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE).PHP_EOL;
        file_put_contents($dir.'/lifeos-'.gmdate('Y-m').'.log',$line,FILE_APPEND|LOCK_EX);
    }
}
