<?php
declare(strict_types=1);
namespace LifeOS\Core;

use RuntimeException;

final class ProfileContext
{
    private static ?int $profileId=null;

    public static function id(): int {
        if(self::$profileId!==null)return self::$profileId;
        $id=Database::connection()->query('SELECT id FROM profiles ORDER BY id ASC LIMIT 1')->fetchColumn();
        if($id===false)throw new RuntimeException('Belum ada profile LIFEOS. Jalankan migration terlebih dahulu.');
        self::$profileId=(int)$id;
        return self::$profileId;
    }

    public static function reset(): void { self::$profileId=null; }
}
