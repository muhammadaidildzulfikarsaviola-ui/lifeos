<?php
declare(strict_types=1);
namespace LifeOS\Core;

final class Bootstrap
{
    public static function boot(): Router {
        if(!defined('BASE_PATH'))define('BASE_PATH',dirname(__DIR__,2));
        require_once BASE_PATH.'/app/Core/helpers.php';
        self::loadEnv(BASE_PATH.'/.env');
        date_default_timezone_set((string)app_config('timezone','Asia/Jakarta'));
        ErrorHandler::register();
        Session::start();
        $router=new Router();
        foreach([BASE_PATH.'/routes/web.php',BASE_PATH.'/routes/api.php'] as $file) {
            if(!is_file($file))continue;
            $register=require $file;
            if(is_callable($register))$register($router);
        }
        return $router;
    }
    private static function loadEnv(string $file): void {
        if(!is_readable($file))return;
        foreach(file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){
            $line=trim($line); if($line===''||str_starts_with($line,'#')||!str_contains($line,'='))continue;
            [$key,$value]=explode('=',$line,2);$key=trim($key);$value=trim(trim($value),"'\"");
            if($key!==''&&getenv($key)===false){putenv($key.'='.$value);$_ENV[$key]=$value;}
        }
    }
}
