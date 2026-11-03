<?php
declare(strict_types=1);
namespace LifeOS\Core;
final class RequestContext {
    private static ?string $id=null;
    public static function start(): string {self::$id=bin2hex(random_bytes(16));return self::$id;}
    public static function id(): string {return self::$id??self::start();}
}
