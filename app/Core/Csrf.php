<?php
declare(strict_types=1);
namespace LifeOS\Core;

final class Csrf
{
    public static function token(): string {
        $token=Session::get('_csrf_token');
        if (!is_string($token) || strlen($token)!==64) { $token=bin2hex(random_bytes(32)); Session::put('_csrf_token',$token); }
        return $token;
    }
    public static function middleware(): callable {
        return static function(Request $request): ?Response {
            if (!in_array($request->method(),['POST','PUT','PATCH','DELETE'],true)) return null;
            $provided=$request->header('X-CSRF-TOKEN') ?? $request->input('_token');
            $known=Session::get('_csrf_token');
            if (is_string($provided) && is_string($known) && hash_equals($known,$provided)) return null;
            return $request->expectsJson() ? Response::error('CSRF token tidak valid atau kedaluwarsa.',419) : Response::html('<h1>419 - CSRF token tidak valid</h1>',419);
        };
    }
}
