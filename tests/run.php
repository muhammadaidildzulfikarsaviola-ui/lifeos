<?php
declare(strict_types=1);

define('BASE_PATH',dirname(__DIR__));

spl_autoload_register(static function(string $class): void {
    $prefix='LifeOS\\Core\\';
    if(str_starts_with($class,$prefix)){
        $path=BASE_PATH.'/app/Core/'.str_replace('\\',DIRECTORY_SEPARATOR,substr($class,strlen($prefix))).'.php';
        if(is_file($path))require $path;
    }
});

require BASE_PATH.'/app/Core/helpers.php';

use LifeOS\Core\Csrf;
use LifeOS\Core\Request;
use LifeOS\Core\Response;
use LifeOS\Core\Router;
use LifeOS\Core\Session;
use LifeOS\Core\Validator;

$passed=0;$failed=0;
$test=static function(string $name,callable $callback)use(&$passed,&$failed):void{
    try{$callback();$passed++;echo "[PASS] {$name}\n";}
    catch(Throwable $e){$failed++;echo "[FAIL] {$name}: {$e->getMessage()}\n";}
};
$assert=static function(bool $condition,string $message='Assertion failed.'):void{if(!$condition)throw new RuntimeException($message);};

$test('Request normalizes path and HTTP method',static function()use($assert):void{
    $request=new Request([],[],[],['REQUEST_METHOD'=>'POST','REQUEST_URI'=>'/api/tasks/42/?q=1'],null);
    $assert($request->method()==='POST');
    $assert($request->path()==='/api/tasks/42');
});
$test('Request supports method override',static function()use($assert):void{
    $request=new Request([],['_method'=>'PATCH'],[],['REQUEST_METHOD'=>'POST','REQUEST_URI'=>'/tasks/1']);
    $assert($request->method()==='PATCH');
});
$test('Request rejects malformed JSON distinctly',static function()use($assert):void{
    $request=new Request([],[],[],['REQUEST_METHOD'=>'POST','REQUEST_URI'=>'/api/test','CONTENT_TYPE'=>'application/json'],'{"broken":');
    $assert($request->hasValidJsonBody()===false);
});
$test('Request accepts JSON object body',static function()use($assert):void{
    $request=new Request([],[],[],['REQUEST_METHOD'=>'POST','REQUEST_URI'=>'/api/test','CONTENT_TYPE'=>'application/json'],'{"title":"Test"}');
    $assert($request->hasValidJsonBody());
    $assert($request->input('title')==='Test');
});
$test('Response success envelope is consistent',static function()use($assert):void{
    $response=Response::success(['id'=>7],'Created',201);
    $payload=json_decode($response->body(),true,512,JSON_THROW_ON_ERROR);
    $assert($response->statusCode()===201);
    $assert($payload['success']===true&&$payload['data']['id']===7);
    $assert(array_key_exists('meta',$payload));
});
$test('Router matches literal and parameterized paths',static function()use($assert):void{
    $router=new Router();
    $router->get('/api/tasks/{taskId}',static fn(Request $r,array $p):Response=>Response::success(['id'=>$p['taskId']]));
    $response=$router->dispatch(new Request([],[],[],['REQUEST_METHOD'=>'GET','REQUEST_URI'=>'/api/tasks/abc%201']));
    $payload=json_decode($response->body(),true,512,JSON_THROW_ON_ERROR);
    $assert($response->statusCode()===200&&$payload['data']['id']==='abc 1');
});
$test('Router returns 404 and 405 correctly',static function()use($assert):void{
    $router=new Router();$router->get('/known',static fn():Response=>Response::success());
    $notFound=$router->dispatch(new Request([],[],[],['REQUEST_METHOD'=>'GET','REQUEST_URI'=>'/missing']));
    $wrongMethod=$router->dispatch(new Request([],[],[],['REQUEST_METHOD'=>'POST','REQUEST_URI'=>'/known']));
    $assert($notFound->statusCode()===404);
    $assert($wrongMethod->statusCode()===405);
});
$test('Router rejects malformed JSON before controller',static function()use($assert):void{
    $router=new Router();$called=false;
    $router->post('/api/write',static function()use(&$called):Response{$called=true;return Response::success();});
    $response=$router->dispatch(new Request([],[],[],['REQUEST_METHOD'=>'POST','REQUEST_URI'=>'/api/write','CONTENT_TYPE'=>'application/json'],'{'));
    $assert($response->statusCode()===400&&!$called);
});
$test('CSRF rejects write requests without token',static function()use($assert):void{
    Session::start();$router=new Router();
    $router->post('/api/write',static fn():Response=>Response::success());
    $response=$router->dispatch(new Request([],[],[],['REQUEST_METHOD'=>'POST','REQUEST_URI'=>'/api/write','CONTENT_TYPE'=>'application/json'],'{}'));
    $assert($response->statusCode()===419);
});
$test('CSRF accepts a matching session token',static function()use($assert):void{
    $token=Csrf::token();$router=new Router();
    $router->post('/api/write',static fn():Response=>Response::success(['saved'=>true]));
    $response=$router->dispatch(new Request([],[],[],['REQUEST_METHOD'=>'POST','REQUEST_URI'=>'/api/write','CONTENT_TYPE'=>'application/json','HTTP_X_CSRF_TOKEN'=>$token],'{}'));
    $assert($response->statusCode()===200);
});
$test('Validator reports field-level errors',static function()use($assert):void{
    $validator=(new Validator(['name'=>'','status'=>'unknown']))
        ->required('name','Nama')
        ->oneOf('status','Status',['active','inactive'],false);
    $assert(!$validator->valid());
    $assert(isset($validator->errors()['name'],$validator->errors()['status']));
});
$test('Validator accepts valid date and integer',static function()use($assert):void{
    $validator=(new Validator(['date'=>'2026-11-03','count'=>'12']))
        ->date('date','Tanggal',false)
        ->integer('count','Jumlah',1,20,false);
    $assert($validator->valid());
});

echo "\n{$passed} passed, {$failed} failed.\n";
exit($failed===0?0:1);
