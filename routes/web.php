<?php
declare(strict_types=1);
use LifeOS\Core\Csrf;
use LifeOS\Core\Request;
use LifeOS\Core\Response;
use LifeOS\Core\Router;
use LifeOS\Core\View;
return static function(Router $router): void {
    $router->get('/',static fn(Request $request): Response=>Response::html(View::render('home',['appName'=>app_config('name','LIFEOS'),'version'=>app_config('version','0.1.0'),'csrfToken'=>Csrf::token()])));
};
