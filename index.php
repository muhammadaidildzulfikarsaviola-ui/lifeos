<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once LIFEOS_ROOT . '/app/Modules/Finance/FinanceService.php';
require_once LIFEOS_ROOT . '/app/Modules/Schedule/ScheduleService.php';
require_once LIFEOS_ROOT . '/app/Modules/Tasks/TasksService.php';
require_once LIFEOS_ROOT . '/app/Modules/Habits/HabitsService.php';

use App\Modules\Finance\FinanceService;
use App\Modules\Schedule\ScheduleService;
use App\Modules\Tasks\TasksService;
use App\Modules\Habits\HabitsService;

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
$today = new DateTimeImmutable('today');
$dbConnected = false;
$dashboardError = null;
$financeState = 'unavailable';
$scheduleState = 'unavailable';
$tasksState = 'unavailable';
$habitsState = 'unavailable';
$financeOverview = null;
$scheduleEvents = [];
$scheduleReminders = [];
$taskSummary = null;
$upcomingTasks = [];
$habitsOverview = null;

try {
    $pdo = db();
    $pdo->query('SELECT 1');
    $dbConnected = true;
} catch (Throwable $e) {
    $dashboardError = 'Database tidak dapat dihubungkan. Data module belum tersedia.';
    $financeState = 'error';
    $scheduleState = 'error';
    $tasksState = 'error';
    $habitsState = 'error';
    error_log('[Dashboard] ' . $e->getMessage());
}

if ($dbConnected) {
    try {
        $financeService = new FinanceService($pdo);
        if ($financeService->isInstalled()) {
            $financeOverview = $financeService->getOverviewData();
            $financeState = 'ready';
        }
    } catch (Throwable $e) {
        $financeState = 'error';
        error_log('[DashboardFinance] ' . $e->getMessage());
    }

    try {
        $scheduleService = new ScheduleService($pdo);
        if ($scheduleService->isInstalled() && $scheduleService->isSchemaReady()) {
            $scheduleEvents = $scheduleService->listEvents([
                'start' => $today->format('Y-m-d'),
                'end' => $today->modify('+6 days')->format('Y-m-d'),
            ]);
            $scheduleState = 'ready';
            $now = new DateTimeImmutable();
            foreach ($scheduleEvents as $event) {
                if ($event['status'] !== 'upcoming'
                    || $event['reminder_minutes'] === null) {
                    continue;
                }
                $startAt = new DateTimeImmutable($event['occurrence_date'] . ' ' . ($event['start_time'] ?: '00:00:00'));
                $remindAt = $startAt->modify('-' . (int) $event['reminder_minutes'] . ' minutes');
                if ($now >= $remindAt && $now <= $startAt) {
                    $scheduleReminders[] = $event;
                }
            }
        }
    } catch (Throwable $e) {
        $scheduleState = 'error';
        error_log('[DashboardSchedule] ' . $e->getMessage());
    }

    try {
        $tasksService = new TasksService($pdo);
        if ($tasksService->isSchemaReady()) {
            $taskSummary = $tasksService->getSummary();
            $upcomingTasks = array_slice($tasksService->listTasks(['status' => 'pending', 'due' => 'upcoming'], 'due_date'), 0, 5);
            $tasksState = 'ready';
        }
    } catch (Throwable $e) {
        $tasksState = 'error';
        error_log('[DashboardTasks] ' . $e->getMessage());
    }

    try {
        $habitsService = new HabitsService($pdo);
        if ($habitsService->isSchemaReady()) {
            $habitsOverview = $habitsService->getDashboardData($today->format('Y-m-d'));
            $habitsState = 'ready';
        }
    } catch (Throwable $e) {
        $habitsState = 'error';
        error_log('[DashboardHabits] ' . $e->getMessage());
    }
}

$modules = [
    ['finance', 'Finance', 'Accounts, budgets, and cash flow', '↗', $financeState],
    ['schedule', 'Schedule', 'Events, reminders, and shifts', '▦', $scheduleState],
    ['tasks', 'Tasks', 'Priorities, deadlines, and progress', '☷', $tasksState],
    ['habits', 'Habits', 'Daily routines and consistency', '◷', $habitsState],
    ['journal', 'Journal', 'Journal entries are not implemented', '▤', 'unavailable'],
    ['notes', 'Notes', 'Notes are not implemented', '▧', 'unavailable'],
    ['goals', 'Goals', 'Goal tracking is not implemented', '◎', 'unavailable'],
    ['projects', 'Projects', 'Project tracking is not implemented', '◇', 'unavailable'],
    ['reports', 'Reports', 'Report views are not implemented', '▥', 'unavailable'],
];
require LIFEOS_ROOT . '/includes/header.php';
?>

<section class="dashboard-intro">
    <div>
        <p class="eyebrow">YOUR PERSONAL WORKSPACE</p>
        <h1>Make room for <span>what matters.</span></h1>
        <p class="intro-copy">A clear view of your day, your plans, and everything you're working toward.</p>
    </div>
    <div class="intro-date">
        <span class="date-day"><?= e(date('l, F Y')) ?></span>
        <strong class="date-number"><?= e(date('d')) ?></strong>
    </div>
</section>

<?php if ($dashboardError !== null): ?>
    <div class="dashboard-data-alert" role="alert"><?= e($dashboardError) ?></div>
<?php endif; ?>

<section class="dashboard-section">
    <div class="section-heading">
        <div><p class="eyebrow">YOUR SPACE</p><h2>Workspace</h2></div>
        <span class="section-note">9 modules</span>
    </div>
    <div class="workspace-grid">
        <?php foreach ($modules as [$slug, $name, $description, $icon, $state]): ?>
            <a href="<?= e(url($slug . '/')) ?>" class="workspace-card">
                <span class="workspace-card-top"><span class="workspace-icon"><?= e($icon) ?></span><span class="card-open">↗</span></span>
                <span class="workspace-card-title"><?= e($name) ?></span>
                <span class="workspace-card-description"><?= e($description) ?></span>
                <span class="workspace-module-status <?= $state === 'ready' ? 'is-ready' : ($state === 'error' ? 'has-error' : '') ?>"><?= $state === 'ready' ? 'Data aktif' : ($state === 'error' ? 'Data error' : ($state === 'unavailable' ? 'Scaffold' : 'Tidak termuat')) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="dashboard-section">
    <div class="section-heading">
        <div><p class="eyebrow">AT A GLANCE</p><h2>Module overview</h2></div>
        <span class="section-note">Read-only summaries from active modules</span>
    </div>
    <div class="dashboard-data-grid">
        <article class="dashboard-data-card">
            <div class="dashboard-data-heading"><div><span class="widget-icon">↗</span><h3>Finance</h3></div><a href="<?= e(url('finance/')) ?>">Open Finance ↗</a></div>
            <?php if ($financeState === 'error'): ?>
                <p class="dashboard-module-message">Finance data could not be loaded. Open Finance to review its database status.</p>
            <?php elseif ($financeState !== 'ready'): ?>
                <p class="dashboard-module-message">Finance tables are not installed in this database.</p>
            <?php elseif (!$financeOverview['has_any_data']): ?>
                <div class="dashboard-empty-state"><strong>No Finance records yet</strong><p>Accounts, posted transactions, budgets, bills, and active loans will appear here when they are recorded.</p></div>
            <?php else: ?>
                <div class="dashboard-finance-metrics">
                    <div><span>Active balance</span><strong><?= e(format_rupiah($financeOverview['total_balance'])) ?></strong></div>
                    <div><span>Income this month</span><strong class="dashboard-income-value"><?= e(format_rupiah($financeOverview['total_income'])) ?></strong></div>
                    <div><span>Expenses this month</span><strong class="dashboard-expense-value"><?= e(format_rupiah($financeOverview['total_expenses'])) ?></strong></div>
                </div>
                <?php if ($financeOverview['recent_transactions']): ?>
                    <ul class="dashboard-compact-list">
                        <?php foreach (array_slice($financeOverview['recent_transactions'], 0, 3) as $transaction): ?>
                            <li><a href="<?= e(url('finance/transactions.php')) ?>"><span><?= e($transaction['description']) ?><small><?= e($transaction['transaction_date']) ?></small></span><strong><?= e(format_rupiah($transaction['amount'])) ?></strong></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </article>

        <article class="dashboard-data-card">
            <div class="dashboard-data-heading"><div><span class="widget-icon">▦</span><h3>Schedule</h3></div><a href="<?= e(url('schedule/')) ?>">Open Schedule ↗</a></div>
            <?php if ($scheduleState === 'error'): ?>
                <p class="dashboard-module-message">Schedule data could not be loaded. Open Schedule to review its database status.</p>
            <?php elseif ($scheduleState !== 'ready'): ?>
                <p class="dashboard-module-message">Schedule tables or the current Schedule schema are not available.</p>
            <?php elseif (!$scheduleEvents): ?>
                <div class="dashboard-empty-state"><strong>No upcoming events</strong><p>Events over the next seven days will appear here.</p></div>
            <?php else: ?>
                <?php if ($scheduleReminders): ?><p class="dashboard-reminder-count"><?= count($scheduleReminders) ?> reminder<?= count($scheduleReminders) === 1 ? '' : 's' ?> due now</p><?php endif; ?>
                <ul class="dashboard-compact-list">
                    <?php foreach (array_slice($scheduleEvents, 0, 5) as $event): ?>
                        <li><a href="<?= e(url('schedule/events.php?id=' . (int) $event['id'])) ?>"><span><?= e($event['title']) ?><small><?= e($event['occurrence_date']) ?><?= empty($event['all_day']) && $event['start_time'] ? ' · ' . e(substr((string) $event['start_time'], 0, 5)) : ' · All day' ?></small></span><span class="dashboard-inline-badge status-<?= e($event['status']) ?>"><?= e($event['status']) ?></span></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>

        <article class="dashboard-data-card">
            <div class="dashboard-data-heading"><div><span class="widget-icon">☷</span><h3>Tasks</h3></div><a href="<?= e(url('tasks/')) ?>">Open Tasks ↗</a></div>
            <?php if ($tasksState === 'error'): ?>
                <p class="dashboard-module-message">Task data could not be loaded. Open Tasks to review its database status.</p>
            <?php elseif ($tasksState !== 'ready'): ?>
                <p class="dashboard-module-message">Tasks tables are not installed in this database.</p>
            <?php else: ?>
                <div class="dashboard-task-metrics"><div><strong><?= $taskSummary['total'] ?></strong><span>Total</span></div><div><strong><?= $taskSummary['pending'] ?></strong><span>Pending</span></div><div><strong><?= $taskSummary['completed'] ?></strong><span>Completed</span></div><div><strong><?= $taskSummary['overdue'] ?></strong><span>Overdue</span></div></div>
                <?php if ($upcomingTasks): ?>
                    <ul class="dashboard-compact-list">
                        <?php foreach ($upcomingTasks as $task): ?>
                            <li><a href="<?= e(url('tasks/index.php?action=view&id=' . (int) $task['id'])) ?>"><span><?= e($task['title']) ?><small>Due <?= e($task['due_date']) ?></small></span><span class="dashboard-inline-badge priority-<?= e($task['priority']) ?>"><?= e($task['priority']) ?></span></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif ($taskSummary['total'] === 0): ?>
                    <div class="dashboard-empty-state"><strong>No tasks yet</strong><p>Create the first task from Tasks.</p></div>
                <?php else: ?>
                    <p class="dashboard-module-message">No pending tasks with upcoming due dates.</p>
                <?php endif; ?>
            <?php endif; ?>
        </article>

        <article class="dashboard-data-card">
            <div class="dashboard-data-heading"><div><span class="widget-icon">◷</span><h3>Habits</h3></div><a href="<?= e(url('habits/')) ?>">Open Habits ↗</a></div>
            <?php if ($habitsState === 'error'): ?>
                <p class="dashboard-module-message">Habit data could not be loaded. Open Habits to review its database status.</p>
            <?php elseif ($habitsState !== 'ready'): ?>
                <p class="dashboard-module-message">Habits tables or the approved schema are not available.</p>
            <?php elseif ($habitsOverview['active_total'] === 0): ?>
                <div class="dashboard-empty-state"><strong>No active habits yet</strong><p>Create a habit to start tracking today's routines and longer-term targets.</p></div>
            <?php else: ?>
                <div class="dashboard-task-metrics dashboard-habit-metrics"><div><strong><?= $habitsOverview['completed_today'] ?>/<?= $habitsOverview['scheduled_total'] ?></strong><span>Completed today</span></div><div><strong><?= e((string) $habitsOverview['completion_percent']) ?>%</strong><span>Today's progress</span></div></div>
                <?php if ($habitsOverview['scheduled_today']): ?><ul class="dashboard-compact-list"><?php foreach (array_slice($habitsOverview['scheduled_today'], 0, 5) as $habitItem): ?><li><a href="<?= e(url('habits/index.php?action=view&id=' . (int) $habitItem['id'])) ?>"><span><?= e($habitItem['name']) ?><small><?= $habitItem['completed'] ? 'Completed today' : ($habitItem['progress_percent'] > 0 ? e((string) $habitItem['progress_percent']) . '% progress' : 'Scheduled today') ?></small></span><?php if ($habitItem['current_streak'] > 0): ?><span class="dashboard-inline-badge"><?= (int) $habitItem['current_streak'] ?> streak</span><?php endif; ?></a></li><?php endforeach; ?></ul><?php endif; ?>
                <?php if ($habitsOverview['period_targets']): ?><div class="dashboard-module-message"><?php foreach (array_slice($habitsOverview['period_targets'], 0, 3) as $target): ?><span><?= e($target['name']) ?> · <?= (int) $target['period_progress']['completed_days'] ?>/<?= (int) $target['period_progress']['target'] ?> days <?= e((string) $target['period_progress']['percent']) ?>%</span><?php endforeach; ?></div><?php endif; ?>
                <?php if (!$habitsOverview['scheduled_today'] && !$habitsOverview['period_targets']): ?><div class="dashboard-empty-state"><strong>No habits scheduled today</strong><p>Current streaks and historical progress remain available in Habits.</p></div><?php endif; ?>
                <?php if ($habitsOverview['streak_highlights']): ?><p class="dashboard-reminder-count">Streak highlight: <?= e($habitsOverview['streak_highlights'][0]['name']) ?> · <?= (int) $habitsOverview['streak_highlights'][0]['current_streak'] ?></p><?php endif; ?>
            <?php endif; ?>
        </article>
        <article class="dashboard-data-card dashboard-unavailable-card">
            <div class="dashboard-data-heading"><div><span class="widget-icon">▤</span><h3>Journal</h3></div><a href="<?= e(url('journal/')) ?>">Open Journal ↗</a></div>
            <div class="dashboard-empty-state"><strong>Journal entries are not implemented</strong><p>No journal activity is available to summarize.</p></div>
        </article>
        <article class="dashboard-data-card dashboard-unavailable-card">
            <div class="dashboard-data-heading"><div><span class="widget-icon">▧</span><h3>Notes</h3></div><a href="<?= e(url('notes/')) ?>">Open Notes ↗</a></div>
            <div class="dashboard-empty-state"><strong>Notes are not implemented</strong><p>No note records are available to summarize.</p></div>
        </article>
        <article class="dashboard-data-card dashboard-unavailable-card">
            <div class="dashboard-data-heading"><div><span class="widget-icon">◎</span><h3>Goals</h3></div><a href="<?= e(url('goals/')) ?>">Open Goals ↗</a></div>
            <div class="dashboard-empty-state"><strong>Goal tracking is not implemented</strong><p>No goal progress is available to summarize.</p></div>
        </article>
        <article class="dashboard-data-card dashboard-unavailable-card">
            <div class="dashboard-data-heading"><div><span class="widget-icon">◇</span><h3>Projects</h3></div><a href="<?= e(url('projects/')) ?>">Open Projects ↗</a></div>
            <div class="dashboard-empty-state"><strong>Project tracking is not implemented</strong><p>No project status or progress data is available.</p></div>
        </article>
        <article class="dashboard-data-card dashboard-unavailable-card">
            <div class="dashboard-data-heading"><div><span class="widget-icon">▥</span><h3>Reports</h3></div><a href="<?= e(url('reports/')) ?>">Open Reports ↗</a></div>
            <div class="dashboard-empty-state"><strong>Reports are not implemented</strong><p>No report summaries are currently available.</p></div>
        </article>
    </div>
</section>

<section class="dashboard-bottom">
    <div class="local-status"><span class="local-status-indicator"></span><div><strong>Private by design</strong><span>Your workspace runs locally on this device.</span></div></div>
    <span class="database-status"><?= $dbConnected ? 'Database connected' : 'Database unavailable' ?></span>
</section>

<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
