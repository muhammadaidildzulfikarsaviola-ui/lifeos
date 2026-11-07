<?php
declare(strict_types=1);

define('LIFEOS_ROOT', dirname(__DIR__));
require_once LIFEOS_ROOT . '/includes/bootstrap.php';
require_once LIFEOS_ROOT . '/function.php';
require_once LIFEOS_ROOT . '/app/Modules/Tasks/TasksService.php';
require_once LIFEOS_ROOT . '/app/Modules/Schedule/ScheduleService.php';

use App\Modules\Schedule\ScheduleService;
use App\Modules\Tasks\TasksService;

$configuredHost = strtolower(getenv('DB_HOST') ?: '127.0.0.1');
if (!in_array($configuredHost, ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Tasks MariaDB test only runs against a loopback database host.');
}

$db = db();
$identity = $db->query('SELECT DATABASE() AS db_name, VERSION() AS version')->fetch(PDO::FETCH_ASSOC);
if (($identity['db_name'] ?? null) !== 'lifeos' || !str_contains((string) ($identity['version'] ?? ''), 'MariaDB')) {
    throw new RuntimeException('Tasks MariaDB test requires the local lifeos database.');
}

$service = new TasksService($db);
$schedule = new ScheduleService($db);
if (!$service->isSchemaReady()) {
    throw new RuntimeException('Tasks schema is not ready; no test data was written.');
}

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    $checks++;
    echo "PASS: {$message}\n";
};

$marker = 'LIFEOS_TASK_TEST_' . bin2hex(random_bytes(5));
$taskIds = [];
$eventId = 0;
$db->beginTransaction();
try {
    $baseline = $service->getSummary();
    $category = $service->listCategories()[0] ?? null;
    $assert($category !== null, 'existing task category is available');

    $overdueId = $service->createTask([
        'title' => $marker . '_overdue',
        'description' => 'Schedule relationship regression entry',
        'status' => 'todo',
        'priority' => 'critical',
        'due_date' => '2000-01-01',
        'category_id' => (string) $category['id'],
    ]);
    $taskIds[] = $overdueId;
    $completedId = $service->createTask([
        'title' => $marker . '_completed',
        'status' => 'done',
        'priority' => 'low',
        'due_date' => '2000-01-01',
    ]);
    $taskIds[] = $completedId;

    $assert($overdueId > 0 && $completedId > 0, 'task creation persists generated ids');
    $createdSummary = $service->getSummary();
    $assert($createdSummary['total'] === $baseline['total'] + 2
        && $createdSummary['pending'] === $baseline['pending'] + 1
        && $createdSummary['completed'] === $baseline['completed'] + 1,
        'summary counts track pending and completed task creation');
    $assert((bool) $service->getTask($overdueId)['is_overdue'], 'past due date is overdue for a pending task');
    $completed = $service->getTask($completedId);
    $assert(!empty($completed['completed_at']) && !(bool) $completed['is_overdue'], 'completed task records completion time and is not overdue');

    $filters = ['status' => 'pending', 'priority' => 'critical', 'search' => $marker . '_overdue'];
    $filtered = $service->listTasks($filters, 'priority');
    $assert(count($filtered) === 1 && (int) $filtered[0]['id'] === $overdueId, 'status, priority, and text search filters match the task');
    $assert($service->countTasks($filters) === 1, 'filtered count matches filtered task list');

    $service->updateTask($overdueId, [
        'title' => $marker . '_updated', 'description' => 'Updated task description',
        'status' => 'in_progress', 'priority' => 'high', 'due_date' => '2000-01-01',
        'category_id' => (string) $category['id'],
    ]);
    $assert($service->getTask($overdueId)['title'] === $marker . '_updated', 'task edit persists fields and category');

    $_SERVER['SCRIPT_NAME'] = '/tasks/index.php';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = [];
    ob_start();
    include LIFEOS_ROOT . '/tasks/index.php';
    $taskPage = (string) ob_get_clean();
    $assert(!preg_match('/PHP (Warning|Notice|Fatal error|Parse error)|Warning:|Notice:/', $taskPage), 'Tasks route renders without PHP warnings');
    $assert(str_contains($taskPage, $marker . '_updated'), 'Tasks route shows live MariaDB task title');
    $assert(str_contains($taskPage, 'Updated task description'), 'Tasks route shows live MariaDB task description');
    $assert(str_contains($taskPage, '/assets/js/modules/tasks.js'), 'Tasks page loads its external interaction script');

    $service->setTaskStatus($overdueId, 'done');
    $assert(!empty($service->getTask($overdueId)['completed_at']), 'completion transition stamps completed_at');
    $service->setTaskStatus($overdueId, 'todo');
    $reopened = $service->getTask($overdueId);
    $assert($reopened['completed_at'] === null && (bool) $reopened['is_overdue'], 'reopening clears completed_at and recalculates overdue');

    $eventId = $schedule->createEvent([
        'title' => $marker . ' linked event', 'start_date' => date('Y-m-d'), 'start_time' => '09:00',
        'end_date' => date('Y-m-d'), 'end_time' => '09:30', 'task_id' => $overdueId,
    ]);
    $linkedEvent = $schedule->getEvent($eventId);
    $assert((int) $linkedEvent['task_id'] === $overdueId && $linkedEvent['task_title'] === $marker . '_updated', 'Schedule event reads its linked task through the existing foreign key');
    $assert(count($service->getScheduleEvents($overdueId)) === 1, 'task detail can list its linked Schedule event');

    $service->softDeleteTask($overdueId);
    $assert($service->getTask($overdueId) === null, 'archive hides the task from active reads');
    $archivedEvent = $schedule->getEvent($eventId);
    $assert((int) $archivedEvent['task_id'] === $overdueId && $archivedEvent['task_title'] === null, 'archiving a task retains the event and safely hides unavailable task details');

    try {
        $service->createTask(['title' => '   ']);
        $assert(false, 'empty task title is rejected');
    } catch (InvalidArgumentException) {
        $assert(true, 'empty task title is rejected');
    }
    try {
        $service->createTask(['title' => $marker . '_invalid_date', 'due_date' => '2026-02-31']);
        $assert(false, 'impossible due date is rejected');
    } catch (InvalidArgumentException) {
        $assert(true, 'impossible due date is rejected');
    }
    try {
        $service->setTaskStatus($completedId, 'unknown');
        $assert(false, 'invalid status transition is rejected');
    } catch (InvalidArgumentException) {
        $assert(true, 'invalid status transition is rejected');
    }

    $db->rollBack();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $e;
}

$cleanup = $db->prepare('SELECT COUNT(*) FROM tasks WHERE title LIKE :marker');
$cleanup->execute(['marker' => $marker . '%']);
$assert((int) $cleanup->fetchColumn() === 0, 'transaction rollback leaves no test task rows');
if ($eventId > 0) {
    $cleanup = $db->prepare('SELECT COUNT(*) FROM schedule_events WHERE title LIKE :marker');
    $cleanup->execute(['marker' => $marker . '%']);
    $assert((int) $cleanup->fetchColumn() === 0, 'transaction rollback leaves no test Schedule events');
}

echo "Tasks MariaDB checks: {$checks} passed.\n";
