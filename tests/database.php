<?php
declare(strict_types=1);
define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/app/Core/helpers.php';
spl_autoload_register(static function(string $class):void{
    $prefix='LifeOS\\';
    if(!str_starts_with($class,$prefix))return;
    $relative=str_replace('\\',DIRECTORY_SEPARATOR,substr($class,strlen($prefix)));
    $file=BASE_PATH.'/'.(str_starts_with($relative,'Core'.DIRECTORY_SEPARATOR)?'app/':'').$relative.'.php';
    if(is_file($file))require $file;
});
$env=BASE_PATH.'/.env';
if(is_readable($env))foreach(file($env,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){
    $line=trim($line);if($line===''||str_starts_with($line,'#')||!str_contains($line,'='))continue;
    [$key,$value]=explode('=',$line,2);$key=trim($key);$value=trim(trim($value),"'"");
    if($key!==''&&getenv($key)===false)putenv($key.'='.$value);
}
$config=require BASE_PATH.'/config/database.php';
$db=(string)($config['database']??'');
LifeOS\Core\DatabaseTestGuard::assertSafeDatabaseName($db);
$pdo=LifeOS\Core\Database::connection();
$checks=0;
$assert=static function(bool $ok,string $message)use(&$checks):void{
    $checks++;if(!$ok)throw new RuntimeException($message);
};
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS _lifeos_test_probe (id INT UNSIGNED NOT NULL AUTO_INCREMENT,value VARCHAR(30) NOT NULL,PRIMARY KEY(id)) ENGINE=InnoDB');
    $pdo->exec('DELETE FROM _lifeos_test_probe');
    LifeOS\Core\Database::transaction(static function(PDO $pdo):void{
        $q=$pdo->prepare('INSERT INTO _lifeos_test_probe(value) VALUES(:value)');$q->execute(['value'=>'outer']);
        try{LifeOS\Core\Database::transaction(static function(PDO $pdo):void{
            $q=$pdo->prepare('INSERT INTO _lifeos_test_probe(value) VALUES(:value)');$q->execute(['value'=>'inner']);
            throw new RuntimeException('rollback nested');
        });}catch(RuntimeException $e){if($e->getMessage()!=='rollback nested')throw $e;}
    });
    $count=(int)$pdo->query('SELECT COUNT(*) FROM _lifeos_test_probe')->fetchColumn();
    $assert($count===1,'Nested rollback did not preserve only outer transaction.');
    $pdo->exec('DELETE FROM _lifeos_test_probe');
    try{LifeOS\Core\Database::transaction(static function(PDO $pdo):void{
        $pdo->exec("INSERT INTO _lifeos_test_probe(value) VALUES('rollback')");
        throw new RuntimeException('rollback outer');
    });}catch(RuntimeException $e){if($e->getMessage()!=='rollback outer')throw $e;}
    $assert((int)$pdo->query('SELECT COUNT(*) FROM _lifeos_test_probe')->fetchColumn()===0,'Outer rollback failed.');
    $storage=LifeOS\Core\Storage::ensure('temporary');
    $probe=$storage.'/_lifeos_write_probe.tmp';
    file_put_contents($probe,'lifeos');
    $assert(file_get_contents($probe)==='lifeos','Storage write/read failed.');
    unlink($probe);
    echo "[PASS] Nested transaction savepoint\n[PASS] Outer transaction rollback\n[PASS] Private storage write/read\n";
    echo "3 passed, 0 failed. Database: {$db}\n";
} catch(Throwable $e) {
    fwrite(STDERR,'[FAIL] '.$e->getMessage().PHP_EOL);exit(1);
} finally {
    $pdo->exec('DROP TABLE IF EXISTS _lifeos_test_probe');
}
