<?php
declare(strict_types=1);

define('LIFEOS_ROOT', dirname(__DIR__));
require_once LIFEOS_ROOT . '/includes/bootstrap.php';
require_once LIFEOS_ROOT . '/app/Modules/Habits/HabitsService.php';
require_once LIFEOS_ROOT . '/app/Modules/Finance/FinanceService.php';
require_once LIFEOS_ROOT . '/app/Modules/Schedule/ScheduleService.php';
require_once LIFEOS_ROOT . '/app/Modules/Tasks/TasksService.php';

use App\Modules\Habits\HabitsService;
use App\Modules\Finance\FinanceService;
use App\Modules\Schedule\ScheduleService;
use App\Modules\Tasks\TasksService;

$host = strtolower(getenv('DB_HOST') ?: '127.0.0.1');
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) throw new RuntimeException('Habits test requires a loopback database host.');
$db = db();
$identity = $db->query('SELECT DATABASE() AS db_name,VERSION() AS version')->fetch(PDO::FETCH_ASSOC);
if (($identity['db_name'] ?? null) !== 'lifeos' || !str_contains((string) ($identity['version'] ?? ''), '10.4.32-MariaDB')) throw new RuntimeException('Habits integration test requires local lifeos MariaDB 10.4.32.');

$service = new HabitsService($db);
if (!$service->isSchemaReady()) throw new RuntimeException('Approved Habits schema is not ready.');
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
    echo "PASS: {$message}\n";
};
$marker = 'LIFEOS_HABIT_TEST_' . bin2hex(random_bytes(5));
$habitIds = [];
$db->beginTransaction();
try {
    $baseline = (int) $db->query('SELECT COUNT(*) FROM habits')->fetchColumn();
    ob_start();
    $oldGet = $_GET;
    $_GET = [];
    include LIFEOS_ROOT . '/habits/index.php';
    $emptyHtml = (string) ob_get_clean();
    $_GET = $oldGet;
    $assert($baseline === 0 && str_contains($emptyHtml, 'No habits scheduled on this date'), 'empty Habits state renders without stored records');
    ob_start();
    $oldGet = $_GET;
    $_GET = ['action' => 'create'];
    include LIFEOS_ROOT . '/habits/index.php';
    $createHtml = (string) ob_get_clean();
    $_GET = $oldGet;
    $assert(str_contains($createHtml, 'data-habit-frequency') && str_contains($createHtml, 'Create habit') && str_contains($createHtml, 'assets/js/modules/habits.js'), 'habit creation form exposes schedule controls and external behavior script');

    $today = new DateTimeImmutable('today');
    $todayString = $today->format('Y-m-d');
    $dailyStart = $today->modify('-3 days')->format('Y-m-d');
    $dailyId = $service->createHabit(['name' => $marker . ' daily', 'frequency' => 'daily', 'start_date' => $dailyStart]);
    $habitIds[] = $dailyId;
    $assert($dailyId > 0 && $service->getHabit($dailyId)['frequency'] === 'daily', 'daily habit create and read work');
    $firstDate = $today->modify('-2 days')->format('Y-m-d');
    $firstEntry = $service->saveEntry($dailyId, ['entry_date' => $firstDate, 'completed' => '1']);
    $sameEntry = $service->saveEntry($dailyId, ['entry_date' => $firstDate, 'completed' => '1']);
    $assert($firstEntry === $sameEntry && count($service->listEntries($dailyId, $firstDate, $firstDate)) === 1, 'updating an entry preserves its ID and prevents duplicates');
    $service->saveEntry($dailyId, ['entry_date' => $today->modify('-1 day')->format('Y-m-d'), 'completed' => '1']);
    $assert($service->getStats($dailyId)['current_streak'] === 2, 'pending scheduled habit today does not break current streak before day end');
    $assert($service->getStats($dailyId)['completion_rate'] === 66.7, 'completion rate excludes the unfinished current day from its denominator');
    $service->saveEntry($dailyId, ['entry_date' => $todayString, 'completed' => '1']);
    $dailyStats = $service->getStats($dailyId);
    $assert($dailyStats['current_streak'] === 3 && $dailyStats['longest_streak'] === 3, 'daily current and longest streaks update on completion');
    $service->saveEntry($dailyId, ['entry_date' => $todayString, 'completed' => '0']);
    $assert($service->getStats($dailyId)['current_streak'] === 2, 'undo completion updates current streak');
    $beforeFuture = $service->getStats($dailyId);
    $futureInsert = $db->prepare('INSERT INTO habit_entries (habit_id,entry_date,progress,completed) VALUES (:id,:date,1,1)');
    $futureInsert->execute(['id' => $dailyId, 'date' => $today->modify('+5 days')->format('Y-m-d')]);
    $afterFuture = $service->getStats($dailyId);
    $assert($afterFuture['total_completed_days'] === $beforeFuture['total_completed_days'] && $afterFuture['current_streak'] === $beforeFuture['current_streak'], 'unexpected future rows are excluded from all statistics');

    $waterId = $service->createHabit(['name' => $marker . ' water', 'frequency' => 'daily', 'start_date' => $dailyStart, 'daily_target' => '8', 'unit' => 'glasses']);
    $habitIds[] = $waterId;
    $waterEntry = $service->saveEntry($waterId, ['entry_date' => $todayString, 'progress' => '5']);
    $waterOverview = $service->getOverviewData($todayString);
    $waterItem = array_values(array_filter($waterOverview['habits'], static fn(array $item): bool => (int) $item['id'] === $waterId))[0];
    $assert($waterEntry > 0 && $waterItem['progress_percent'] === 62.5, 'quantitative progress computes 5 of 8 as 62.5 percent');
    $waterUpdated = $service->saveEntry($waterId, ['entry_date' => $todayString, 'progress' => '12.50']);
    $waterUpdatedOverview = $service->getOverviewData($todayString);
    $waterUpdatedItem = array_values(array_filter($waterUpdatedOverview['habits'], static fn(array $item): bool => (int) $item['id'] === $waterId))[0];
    $assert($waterUpdated === $waterEntry && (float) $service->getEntry($waterId, $todayString)['progress'] === 12.5 && $waterUpdatedItem['progress_percent'] === 100.0, 'progress over target is retained while displayed percentage is capped at 100 percent');
    $service->saveEntry($waterId, ['entry_date' => $today->modify('-1 day')->format('Y-m-d'), 'progress' => '8']);
    $assert((int) $service->getEntry($waterId, $today->modify('-1 day')->format('Y-m-d'))['completed'] === 1, 'reaching a quantitative target completes a new entry when completion is not explicitly overridden');
    foreach (['-1', '1.234', '1e3'] as $invalidProgress) {
        try { $service->saveEntry($waterId, ['entry_date' => $todayString, 'progress' => $invalidProgress]); $assert(false, 'invalid progress rejected: ' . $invalidProgress); }
        catch (InvalidArgumentException) { $assert(true, 'invalid progress rejected: ' . $invalidProgress); }
    }
    try { $service->saveEntry($dailyId, ['entry_date' => $today->modify('+1 day')->format('Y-m-d'), 'completed' => '1']); $assert(false, 'future entry rejected'); }
    catch (InvalidArgumentException) { $assert(true, 'future entry rejected'); }

    $weekdaysId = $service->createHabit(['name' => $marker . ' weekdays', 'frequency' => 'weekdays', 'weekdays' => [1, 3], 'start_date' => '2026-10-26']);
    $habitIds[] = $weekdaysId;
    $service->saveEntry($weekdaysId, ['entry_date' => '2026-11-02', 'completed' => '1']);
    $service->saveEntry($weekdaysId, ['entry_date' => '2026-11-04', 'completed' => '1']);
    $weekdayStats = $service->getStats($weekdaysId, '2026-11-06');
    $assert($weekdayStats['current_streak'] === 2 && $weekdayStats['longest_streak'] === 2, 'weekday streak skips unscheduled Tuesday, Thursday, and Friday');
    try { $service->saveEntry($weekdaysId, ['entry_date' => '2026-11-05', 'completed' => '1']); $assert(false, 'unscheduled weekday entry rejected'); }
    catch (InvalidArgumentException) { $assert(true, 'unscheduled weekday entry rejected'); }
    $service->updateHabit($weekdaysId, ['name' => $marker . ' weekdays changed', 'frequency' => 'weekdays', 'weekdays' => [2, 4], 'start_date' => '2026-10-26']);
    $preservedWeekdayEntries = $service->listEntries($weekdaysId, '2026-11-02', '2026-11-04');
    $assert(count($preservedWeekdayEntries) === 2 && (int) $service->getEntry($weekdaysId, '2026-11-02')['completed'] === 1, 'frequency edits preserve existing completion rows and values');

    $monday = $today->modify('monday this week');
    $previousMonday = $monday->modify('-7 days');
    $weeklyId = $service->createHabit(['name' => $marker . ' weekly', 'frequency' => 'weekly_target', 'period_target' => 2, 'start_date' => $previousMonday->format('Y-m-d')]);
    $habitIds[] = $weeklyId;
    foreach ([$previousMonday, $previousMonday->modify('+2 days'), $monday, $monday->modify('+1 day')] as $date) {
        $service->saveEntry($weeklyId, ['entry_date' => $date->format('Y-m-d'), 'completed' => '1']);
    }
    $weeklyStats = $service->getStats($weeklyId, $todayString);
    $assert($weeklyStats['current_streak'] === 2 && $weeklyStats['longest_streak'] === 2, 'weekly target streak counts consecutive Monday-start weeks');
    $assert($weeklyStats['weekly_progress']['period_start'] === $monday->format('Y-m-d') && $weeklyStats['weekly_progress']['completed_days'] === 2, 'weekly summary uses Monday and counts unique completed days');
    $assert($weeklyStats['completion_rate'] === 100.0, 'weekly completion rate counts successful calendar weeks');
    $weeklyIncompleteId = $service->createHabit(['name' => $marker . ' weekly pending', 'frequency' => 'weekly_target', 'period_target' => 3, 'start_date' => $previousMonday->format('Y-m-d')]);
    $habitIds[] = $weeklyIncompleteId;
    foreach ([$previousMonday, $previousMonday->modify('+1 day'), $previousMonday->modify('+2 days'), $monday] as $date) $service->saveEntry($weeklyIncompleteId, ['entry_date' => $date->format('Y-m-d'), 'completed' => '1']);
    $assert($service->getStats($weeklyIncompleteId, $todayString)['current_streak'] === 1, 'unfinished current week does not break streak before Sunday');

    $monthStart = $today->modify('first day of this month');
    $priorMonth = $monthStart->modify('-1 month')->modify('first day of this month');
    $monthlyId = $service->createHabit(['name' => $marker . ' monthly', 'frequency' => 'monthly_target', 'period_target' => 2, 'start_date' => $priorMonth->format('Y-m-d')]);
    $habitIds[] = $monthlyId;
    $service->saveEntry($monthlyId, ['entry_date' => $priorMonth->format('Y-m-d'), 'completed' => '1']);
    $service->saveEntry($monthlyId, ['entry_date' => $priorMonth->modify('+5 days')->format('Y-m-d'), 'completed' => '1']);
    $service->saveEntry($monthlyId, ['entry_date' => $monthStart->modify('+1 day')->format('Y-m-d'), 'completed' => '1']);
    $monthlyStats = $service->getStats($monthlyId, $todayString);
    $assert($monthlyStats['current_streak'] === 1 && $monthlyStats['longest_streak'] === 1, 'incomplete current month preserves prior successful monthly streak');
    $assert($monthlyStats['monthly_progress']['completed_days'] === 1 && $monthlyStats['monthly_progress']['target'] === 2, 'monthly target progress counts unique completed days');
    $assert($monthlyStats['completion_rate'] === 100.0, 'monthly completion rate excludes an unfinished current month');

    $archiveId = $service->createHabit(['name' => $marker . ' archive', 'frequency' => 'daily', 'start_date' => $todayString]);
    $habitIds[] = $archiveId;
    $service->saveEntry($archiveId, ['entry_date' => $todayString, 'completed' => '1']);
    $service->archiveHabit($archiveId);
    $assert($service->getHabit($archiveId) === null && $service->getEntry($archiveId, $todayString) !== null, 'archive hides habit and retains history');
    try { $service->permanentlyDeleteHabit($archiveId); $assert(false, 'hard delete with history rejected'); }
    catch (RuntimeException) { $assert(true, 'hard delete with history rejected'); }
    $service->restoreHabit($archiveId);
    $assert($service->getHabit($archiveId) !== null, 'archived habit can be restored');

    foreach ([['name' => ''], ['name' => $marker, 'frequency' => 'weekdays', 'weekdays' => []], ['name' => $marker, 'frequency' => 'weekly_target', 'period_target' => 8], ['name' => $marker, 'frequency' => 'daily', 'daily_target' => '-2', 'start_date' => $todayString]] as $invalidHabit) {
        try { $service->createHabit($invalidHabit); $assert(false, 'invalid habit configuration rejected'); }
        catch (InvalidArgumentException) { $assert(true, 'invalid habit configuration rejected'); }
    }

    $financeBefore = (new FinanceService($db))->getOverviewData();
    $scheduleBefore = (new ScheduleService($db))->listEvents(['start' => $todayString, 'end' => $todayString]);
    $tasksBefore = (new TasksService($db))->getSummary();
    $dashboard = $service->getDashboardData($todayString);
    ob_start();
    $oldGet = $_GET;
    $_GET = [];
    include LIFEOS_ROOT . '/index.php';
    $dashboardHtml = (string) ob_get_clean();
    $_GET = $oldGet;
    $assert(str_contains($dashboardHtml, $marker . ' daily') && str_contains($dashboardHtml, 'Open Habits'), 'Dashboard renders actual Habits data from the shared service');
    $assert($dashboard['scheduled_total'] > 0 && $dashboard['completed_today'] >= 0 && $dashboard['completion_percent'] <= 100, 'Dashboard Habits summary uses service-computed scheduled progress');
    $assert((new FinanceService($db))->getOverviewData() === $financeBefore, 'Finance summary unchanged by Habits operations');
    $assert((new ScheduleService($db))->listEvents(['start' => $todayString, 'end' => $todayString]) === $scheduleBefore, 'Schedule data unchanged by Habits operations');
    $assert((new TasksService($db))->getSummary() === $tasksBefore, 'Tasks summary unchanged by Habits operations');

    $warningMessages = [];
    set_error_handler(static function (int $severity, string $message) use (&$warningMessages): bool { $warningMessages[] = $message; return true; });
    try {
        ob_start();
        $oldGet = $_GET;
        $_GET = ['action' => 'view', 'id' => (string) $dailyId, 'date' => $todayString];
        include LIFEOS_ROOT . '/habits/index.php';
        $detailHtml = (string) ob_get_clean();
        $_GET = $oldGet;
    } finally { restore_error_handler(); }
    $assert($warningMessages === [] && str_contains($detailHtml, 'habit-calendar') && str_contains($detailHtml, 'Recorded history'), 'Habit detail and activity calendar render without PHP warnings');
    $assert(str_contains($detailHtml, 'assets/css/modules/habits.css') && str_contains($detailHtml, 'assets/js/modules/habits.js'), 'Habits route loads its external stylesheet and interaction script');
    ob_start();
    $oldGet = $_GET;
    $_GET = ['action' => 'view', 'id' => (string) $weekdaysId, 'date' => '2026-11-06'];
    include LIFEOS_ROOT . '/habits/index.php';
    $weekdayHistoryHtml = (string) ob_get_clean();
    $_GET = $oldGet;
    $assert(str_contains($weekdayHistoryHtml, 'state-unscheduled') && str_contains($weekdayHistoryHtml, 'state-missed'), 'history distinguishes unscheduled dates from missed scheduled weekdays');

    $db->rollBack();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    throw $e;
}

$stmt = $db->prepare('SELECT COUNT(*) FROM habits WHERE name LIKE :marker');
$stmt->execute(['marker' => $marker . '%']);
$assert((int) $stmt->fetchColumn() === 0, 'rollback leaves no Habits test records');
$stmt = $db->query('SELECT COUNT(*) FROM habit_entries WHERE habit_id NOT IN (SELECT id FROM habits)');
$assert((int) $stmt->fetchColumn() === 0, 'no orphan Habit entries remain');
echo "Habits MariaDB checks: {$checks} passed.\n";
