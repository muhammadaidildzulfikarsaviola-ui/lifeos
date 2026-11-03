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
    private static int $transactionDepth=0;
    private function __construct() {}

    public static function connection(): PDO {
        if(self::$connection instanceof PDO)return self::$connection;
        $config=require BASE_PATH.'/config/database.php';
        if(!is_array($config))throw new RuntimeException('config/database.php harus mengembalikan array.');
        $host=trim((string)($config['host']??'127.0.0.1'));
        $database=trim((string)($config['database']??''));
        $port=(int)($config['port']??3306);
        $charset=(string)($config['charset']??'utf8mb4');
        if($host===''||$database===''||$port<1||$port>65535||!preg_match('/^[a-zA-Z0-9_]+$/',$database)||!in_array($charset,['utf8mb4','utf8'],true)) {
            throw new RuntimeException('Konfigurasi koneksi database LIFEOS tidak valid.');
        }
        $dsn=sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',$host,$port,$database,$charset);
        try {
            self::$connection=new PDO($dsn,(string)($config['username']??''),(string)($config['password']??''),[
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES=>false,
                PDO::ATTR_PERSISTENT=>false,
                PDO::ATTR_STRINGIFY_FETCHES=>false
            ]);
            self::$connection->exec("SET time_zone = '+00:00'");
            self::$connection->exec("SET NAMES {$charset} COLLATE utf8mb4_unicode_ci");
            return self::$connection;
        } catch(PDOException $e) {
            Logger::log('error','Database connection failed.',['sql_state'=>$e->getCode(),'driver_code'=>$e->errorInfo[1]??null]);
            throw new RuntimeException('Tidak dapat terhubung ke database LIFEOS.',0,$e);
        }
    }

    public static function transaction(callable $callback): mixed {
        $pdo=self::connection();
        $level=self::$transactionDepth;
        $savepoint='lifeos_sp_'.$level;
        if($level===0)$pdo->beginTransaction();
        else $pdo->exec('SAVEPOINT '.$savepoint);
        self::$transactionDepth=$level+1;
        try {
            $result=$callback($pdo);
            if($level===0)$pdo->commit();
            else $pdo->exec('RELEASE SAVEPOINT '.$savepoint);
            self::$transactionDepth=$level;
            return $result;
        } catch(Throwable $e) {
            try {
                if($level===0&&$pdo->inTransaction())$pdo->rollBack();
                elseif($level>0){
                    $pdo->exec('ROLLBACK TO SAVEPOINT '.$savepoint);
                    $pdo->exec('RELEASE SAVEPOINT '.$savepoint);
                }
            } catch(Throwable $rollbackError) {
                Logger::log('critical','Database rollback failed.',['original_error'=>get_class($e),'rollback_error'=>get_class($rollbackError)]);
            }
            self::$transactionDepth=$level;
            throw $e;
        }
    }

    public static function disconnect(): void { self::$connection=null;self::$transactionDepth=0;ProfileContext::reset(); }
}
