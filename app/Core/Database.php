<?php
declare(strict_types=1);
namespace LifeOS\Core;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class Database
{
    private static ?PDO $connection=null;
    private function __construct() {}
    public static function connection(): PDO {
        if(self::$connection instanceof PDO)return self::$connection;
        $config=require BASE_PATH.'/config/database.php';
        if(!is_array($config))throw new RuntimeException('config/database.php harus mengembalikan array.');
        $dsn=sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',(string)($config['host']??'127.0.0.1'),(int)($config['port']??3306),(string)($config['database']??'lifeos'),(string)($config['charset']??'utf8mb4'));
        try {
            self::$connection=new PDO($dsn,(string)($config['username']??''),(string)($config['password']??''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_PERSISTENT=>false]);
            self::$connection->exec("SET time_zone = '+00:00'");
            return self::$connection;
        } catch(PDOException $e) {
            if(class_exists(Logger::class))Logger::log('error','Database connection failed.',['driver_error'=>$e->getMessage()]);
            throw new RuntimeException('Tidak dapat terhubung ke database LIFEOS.',0,$e);
        }
    }
    public static function transaction(callable $callback): mixed {
        $pdo=self::connection();
        if($pdo->inTransaction())return $callback($pdo);
        $pdo->beginTransaction();
        try{$result=$callback($pdo);$pdo->commit();return $result;}
        catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public static function disconnect(): void { self::$connection=null; ProfileContext::reset(); }
}
