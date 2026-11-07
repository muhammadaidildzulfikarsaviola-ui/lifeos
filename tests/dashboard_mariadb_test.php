<?php
declare(strict_types=1);

define('LIFEOS_ROOT', dirname(__DIR__));
require_once LIFEOS_ROOT . '/includes/bootstrap.php';
require_once LIFEOS_ROOT . '/app/Modules/Finance/FinanceService.php';
require_once LIFEOS_ROOT . '/app/Modules/Schedule/ScheduleService.php';
require_once LIFEOS_ROOT . '/app/Modules/Tasks/TasksService.php';

use App\Modules\Finance\FinanceService;
use App\Modules\Schedule\ScheduleService;
use App\Modules\Tasks\TasksService;

$host = strtolower(getenv('DB_HOST') ?: '127.0.0.1');
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Dashboard integration test only runs against a loopback database host.');
}

$db = db();
$identity = $db->query('SELECT DATABASE() AS db_name, VERSION() AS version')->fetch(PDO::FETCH_ASSOC);
if (($identity['db_name'] ?? null) !== 'lifeos' || !str_contains((string) ($identity['version'] ?? ''), 'MariaDB')) {
    throw new RuntimeException('Dashboard integration test requires the local lifeos MariaDB database.');
}

$tasks = new TasksService($db);
$schedule = new ScheduleService($db);
if (!$tasks->isSchemaReady() || !$schedule->isSchemaReady()) {
    throw new RuntimeException('Tasks or Schedule schema is unavailable; no test rows were written.');
}

$marker = 'LIFEOS_DASHBOARD_TEST_' . bin2hex(random_bytes(5));
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    $checks++;
    echo "PASS: {$message}\n";
};

$taskId = 0;
$eventId = 0;
$db->beginTransaction();
try {
    $taskId = $tasks->createTask([
        'title' => $marker . ' task',
        'status' => 'todo',
        'priority' => 'high',
        'due_date' => date('Y-m-d', strtotime('+1 day')),
    ]);
    $start = new DateTimeImmutable('+30 minutes');
    $end = $start->modify('+30 minutes');
    $eventId = $schedule->createEvent([
        'title' => $marker . ' event',
        'start_date' => $start->format('Y-m-d'),
        'start_time' => $start->format('H:i'),
        'end_date' => $end->format('Y-m-d'),
        'end_time' => $end->format('H:i'),
        'reminder_minutes' => 60,
        'task_id' => $taskId,
    ]);

    $financeOverview = (new FinanceService($db))->getOverviewData();
    ob_start();
    include LIFEOS_ROOT . '/index.php';
    $html = (string) ob_get_clean();

    $assert(str_contains($html, $marker . ' task'), 'dashboard shows the task returned by TasksService');
    $assert(str_contains($html, $marker . ' event'), 'dashboard shows the Schedule event returned by ScheduleService');
    $assert(str_contains($html, '1 reminder due now'), 'dashboard derives an active reminder from the Schedule reminder fields');
    $assert(str_contains($html, e(format_rupiah($financeOverview['total_balance'])))
        && str_contains($html, e(format_rupiah($financeOverview['total_income']))),
        'dashboard Finance indicators match FinanceService summary values');
    $assert(str_contains($html, 'No active habits yet')
        && str_contains($html, 'Reports are not implemented'),
        'dashboard identifies empty Habits and unsupported modules without fabricated data');

    $db->rollBack();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $e;
}

$stmt = $db->prepare('SELECT COUNT(*) FROM tasks WHERE title LIKE :marker');
$stmt->execute(['marker' => $marker . '%']);
$assert((int) $stmt->fetchColumn() === 0, 'rollback leaves no dashboard test tasks');
$stmt = $db->prepare('SELECT COUNT(*) FROM schedule_events WHERE title LIKE :marker');
$stmt->execute(['marker' => $marker . '%']);
$assert((int) $stmt->fetchColumn() === 0, 'rollback leaves no dashboard test events');

echo "Dashboard MariaDB checks: {$checks} passed.\n";
