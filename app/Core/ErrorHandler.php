<?php
declare(strict_types=1);
namespace LifeOS\Core;

use Throwable;

final class ErrorHandler
{
    public static function register(): void {
        set_error_handler(static function(int $severity,string $message,string $file,int $line): bool { if(!(error_reporting()&$severity))return false; throw new \ErrorException($message,0,$severity,$file,$line); });
        set_exception_handler(static function(Throwable $e): void {
            $id=bin2hex(random_bytes(8));
            Logger::log('error',$e->getMessage(),['request_id'=>$id,'type'=>get_class($e),'file'=>$e->getFile(),'line'=>$e->getLine(),'trace'=>app_config('debug',false)?$e->getTraceAsString():null]);
            if(PHP_SAPI==='cli'){fwrite(STDERR,'[LIFEOS '.$id.'] '.$e->getMessage().PHP_EOL);exit(1);}
            http_response_code(500);
            $json=str_starts_with((string)($_SERVER['REQUEST_URI']??''),'/api/')||str_contains((string)($_SERVER['HTTP_ACCEPT']??''),'application/json');
            if($json){header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>false,'message'=>app_config('debug',false)?$e->getMessage():'Terjadi kesalahan internal.','data'=>null,'errors'=>null,'meta'=>['request_id'=>$id]],JSON_UNESCAPED_UNICODE);return;}
            header('Content-Type: text/html; charset=utf-8');
            $message=app_config('debug',false)?e($e->getMessage()):'Terjadi kesalahan internal. Silakan periksa log aplikasi.';
            echo '<!doctype html><html lang="id"><meta charset="utf-8"><title>500</title><body><h1>500 - LIFEOS</h1><p>'.$message.'</p><small>Request ID '.e($id).'</small></body></html>';
        });
    }
}
