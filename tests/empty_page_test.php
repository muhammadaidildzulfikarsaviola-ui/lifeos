<?php
declare(strict_types=1);

define('LIFEOS_ROOT', dirname(__DIR__));
require_once LIFEOS_ROOT . '/function.php';
require_once LIFEOS_ROOT . '/app/Modules/Finance/FinanceService.php';

use App\Modules\Finance\FinanceService;

echo "Testing empty database resilience...\n";

$emptyDb = new PDO('sqlite::memory:');
$service = new FinanceService($emptyDb);

$installed = $service->isInstalled();
echo "isInstalled on empty db: " . ($installed ? 'true' : 'false') . "\n";
assert($installed === false, "Empty DB should return isInstalled = false");

echo "Empty DB test passed successfully!\n";
