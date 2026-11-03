<?php
declare(strict_types=1);
namespace LifeOS\Core;

use RuntimeException;

final class Router
{
    private array $routes=[];
    public function get(string $path,callable $handler,array $middleware=[]): void {$this->add('GET',$path,$handler,$middleware);}
    public function post(string $path,callable $handler,array $middleware=[]): void {$this->add('POST',$path,$handler,$middleware);}
    public function put(string $path,callable $handler,array $middleware=[]): void {$this->add('PUT',$path,$handler,$middleware);}
    public function patch(string $path,callable $handler,array $middleware=[]): void {$this->add('PATCH',$path,$handler,$middleware);}
    public function delete(string $path,callable $handler,array $middleware=[]): void {$this->add('DELETE',$path,$handler,$middleware);}
    public function add(string $method,string $path,callable $handler,array $middleware=[]): void {
        $path='/'.trim($path,'/');$path=$path==='/'?'/':rtrim($path,'/');
        $this->routes[]=['method'=>strtoupper($method),'path'=>$path,'handler'=>$handler,'middleware'=>$middleware];
    }
    public function dispatch(Request $request): Response {
        $pathMatched=false;
        foreach($this->routes as $route){
            [$pattern,$names]=$this->compile($route['path']);
            if(!preg_match($pattern,$request->path(),$matches))continue;
            $pathMatched=true;if($route['method']!==$request->method())continue;
            $params=[];foreach($names as $name)$params[$name]=rawurldecode($matches[$name]);
            $csrf=Csrf::middleware();$guard=$csrf($request);if($guard instanceof Response)return $guard;
            foreach($route['middleware'] as $middleware){$result=$middleware($request);if($result instanceof Response)return $result;}
            $response=($route['handler'])($request,$params);
            if(!$response instanceof Response)throw new RuntimeException('Route handler must return Response.');
            return $response;
        }
        if($pathMatched)return $request->expectsJson()?Response::error('Method not allowed.',405):Response::html('<h1>405 - Method Not Allowed</h1>',405);
        return $request->expectsJson()?Response::error('Resource not found.',404):Response::html('<!doctype html><html lang="id"><meta charset="utf-8"><title>404</title><body><h1>404 - Halaman tidak ditemukan</h1></body></html>',404);
    }
    private function compile(string $path): array {
        $names=[];$parts=explode('/',$path);$regex=[];
        foreach($parts as $part){if(preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/',$part,$m)){$names[]=$m[1];$regex[]='(?P<'.$m[1].'>[^/]+)';}else{$regex[]=preg_quote($part,'~');}}
        return ['~^'.implode('/',$regex).'$~D',$names];
    }
}
