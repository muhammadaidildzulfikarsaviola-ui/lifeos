<?php
declare(strict_types=1);
use LifeOS\Core\Csrf;
use LifeOS\Core\Request;
use LifeOS\Core\Response;
use LifeOS\Core\Router;
return static function(Router $router): void {
    $router->get('/api/health',static fn(Request $r): Response=>Response::success(['application'=>app_config('name','LIFEOS'),'status'=>'ok','timestamp'=>gmdate('c')],'LIFEOS is running.'));
    $router->get('/api/csrf-token',static fn(Request $r): Response=>Response::success(['token'=>Csrf::token()],'CSRF token generated.'));
};
