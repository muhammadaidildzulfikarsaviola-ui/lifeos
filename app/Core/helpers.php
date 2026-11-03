<?php
declare(strict_types=1);
use LifeOS\Core\Csrf;
function app_config(string $key,mixed $default=null): mixed {
    static $config=null;
    if($config===null){$config=require BASE_PATH.'/config/app.php';if(!is_array($config))throw new RuntimeException('config/app.php harus mengembalikan array.');}
    return $config[$key]??$default;
}
function e(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function csrf_token(): string { return Csrf::token(); }
function csrf_field(): string { return '<input type="hidden" name="_token" value="'.e(csrf_token()).'">'; }
