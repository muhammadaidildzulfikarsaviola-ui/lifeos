<?php
declare(strict_types=1);
define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/app/Core/helpers.php';
spl_autoload_register(static function(string $class): void {
    $prefix='LifeOS\\Core\\'; if(!str_starts_with($class,$prefix))return;
    $file=BASE_PATH.'/app/Core/'.str_replace('\\',DIRECTORY_SEPARATOR,substr($class,strlen($prefix))).'.php';
    if(is_file($file))require $file;
});
$router=LifeOS\Core\Bootstrap::boot();
$router->dispatch(LifeOS\Core\Request::capture())->send();
