<?php
declare(strict_types=1);
namespace LifeOS\Core;

use LifeOS\Core\Exceptions\AppException;
use Throwable;

final class ErrorHandler
{
    public static function register(): void {
        set_error_handler(static function(int $severity,string $message,string $file,int $line): bool {
            if(!(error_reporting()&$severity))return false;
            throw new \ErrorException($message,0,$severity,$file,$line);
        });

        set_exception_handler(static function(Throwable $e): void {
            $id=bin2hex(random_bytes(8));
            Logger::log('error',$e->getMessage(),[
                'request_id'=>$id,'type'=>get_class($e),'file'=>$e->getFile(),'line'=>$e->getLine(),
                'trace'=>app_config('debug',false)?$e->getTraceAsString():null
            ]);

            if(PHP_SAPI==='cli'){
                fwrite(STDERR,'[LIFEOS '.$id.'] '.$e->getMessage().PHP_EOL);
                exit(1);
            }

            $isApplicationError=$e instanceof AppException;
            $status=$isApplicationError?$e->httpStatus():500;
            $message=$isApplicationError?$e->getMessage():'Terjadi kesalahan internal.';
            $errors=$isApplicationError?$e->fieldErrors():null;
            $errorCode=$isApplicationError?$e->errorCode():'INTERNAL_ERROR';
            http_response_code($status);

            $json=str_starts_with((string)($_SERVER['REQUEST_URI']??''),'/api/')
                ||str_contains((string)($_SERVER['HTTP_ACCEPT']??''),'application/json');

            if($json){
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success'=>false,
                    'message'=>$message,
                    'data'=>null,
                    'errors'=>$errors,
                    'meta'=>['request_id'=>$id,'error_code'=>$errorCode]
                ],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
                return;
            }

            header('Content-Type: text/html; charset=utf-8');
            $safeMessage=e($message);
            echo '<!doctype html><html lang="id"><meta charset="utf-8"><title>'.$status.' - LIFEOS</title><body><h1>'.$status.' - LIFEOS</h1><p>'.$safeMessage.'</p><small>Request ID '.e($id).'</small></body></html>';
        });
    }
}
