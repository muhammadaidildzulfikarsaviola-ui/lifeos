<?php
declare(strict_types=1);
namespace LifeOS\Core;

use RuntimeException;

final class View
{
    public static function render(string $view,array $data=[],?string $layout='layouts/app'): string {
        $viewPath=self::resolve($view);
        extract($data,EXTR_SKIP);
        ob_start();
        try { require $viewPath; $content=(string)ob_get_clean(); }
        catch(\Throwable $e) { ob_end_clean(); throw $e; }
        if($layout===null)return $content;
        $layoutPath=self::resolve($layout);
        ob_start();
        try { require $layoutPath; return (string)ob_get_clean(); }
        catch(\Throwable $e) { ob_end_clean(); throw $e; }
    }

    private static function resolve(string $name): string {
        if(!preg_match('#^[a-zA-Z0-9_/-]+$#',$name)||str_contains($name,'..'))throw new RuntimeException('Nama view tidak valid.');
        $path=BASE_PATH.'/resources/views/'.$name.'.php';
        if(!is_file($path))throw new RuntimeException('View tidak ditemukan: '.$name);
        return $path;
    }
}
