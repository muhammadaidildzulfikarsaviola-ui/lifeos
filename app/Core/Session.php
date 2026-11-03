<?php
declare(strict_types=1);
namespace LifeOS\Core;

final class Session
{
    public static function start(): void {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_name('LIFEOSSESSID');
        session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
        if (!session_start()) throw new \RuntimeException('Gagal memulai session LIFEOS.');
    }
    public static function get(string $key,mixed $default=null): mixed { return $_SESSION[$key] ?? $default; }
    public static function put(string $key,mixed $value): void { $_SESSION[$key]=$value; }
}
