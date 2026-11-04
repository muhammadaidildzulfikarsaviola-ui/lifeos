<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

$modules = [
    ['finance', 'Finance', 'Money flow, accounts and budgets', '↗'],
    ['schedule', 'Schedule', 'Calendar, events and shifts', '▦'],
    ['tasks', 'Tasks', 'Your work, priorities and deadlines', '☷'],
    ['habits', 'Habits', 'Build routines, one day at a time', '◷'],
    ['journal', 'Journal', 'Daily thoughts and reflections', '▤'],
    ['notes', 'Notes', 'Ideas, references and quick captures', '▧'],
    ['goals', 'Goals', 'Milestones and personal direction', '◎'],
    ['projects', 'Projects', 'Things you are building and moving forward', '◇'],
    ['reports', 'Reports', 'Patterns, progress and insights', '▥'],
];

$dbConnected = false;

try {
    db()->query('SELECT 1');
    $dbConnected = true;
} catch (Throwable) {
    // Dashboard tetap bisa dibuka tanpa database.
}

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

<section class="dashboard-section">
    <div class="section-heading">
        <div>
            <p class="eyebrow">YOUR SPACE</p>
            <h2>Workspace</h2>
        </div>
        <span class="section-note">9 areas to keep life in motion</span>
    </div>

    <div class="workspace-grid">
        <?php foreach ($modules as $module): ?>
            <a href="<?= e(url($module[0] . '/')) ?>" class="workspace-card">
                <span class="workspace-card-top">
                    <span class="workspace-icon"><?= e($module[3]) ?></span>
                    <span class="card-open">↗</span>
                </span>
                <span class="workspace-card-title"><?= e($module[1]) ?></span>
                <span class="workspace-card-description"><?= e($module[2]) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="dashboard-section">
    <div class="section-heading">
        <div>
            <p class="eyebrow">AT A GLANCE</p>
            <h2>Daily overview</h2>
        </div>
        <span class="section-note">Your dashboard widgets</span>
    </div>

    <div class="overview-grid">
        <?php
        $widgets = [
            ['◷', "Today's schedule", 'schedule/', 'Open schedule', 'Your day, at a glance', 'Upcoming events and time blocks will appear here.'],
            ['☷', 'Tasks to focus on', 'tasks/', 'View tasks', 'Nothing on the list yet', 'Your next priorities will live here.'],
            ['◉', 'Habit tracker', 'habits/', 'View habits', 'Small steps add up', 'Your daily habit progress will show here.'],
            ['↗', 'Finance snapshot', 'finance/', 'Open finance', 'Your money overview', 'Balances, spending and budgets will appear here.'],
            ['◇', 'Active projects', 'projects/', 'View projects', 'Build something meaningful', 'Your active projects and progress will show here.'],
            ['◎', 'Goals & milestones', 'goals/', 'View goals', 'Keep your direction in sight', 'Your goals and milestones will appear here.'],
            ['▤', 'Recent journal', 'journal/', 'Write an entry', 'A space for your thoughts', 'Your latest reflections will appear here.'],
            ['▧', 'Quick notes', 'notes/', 'Open notes', 'Catch an idea before it goes', 'Your pinned and recent notes will show here.'],
            ['▥', 'Upcoming bills', 'finance/bills.php', 'Manage bills', 'No reminders to show', 'Bill and installment reminders will appear here.'],
            ['⌁', 'Monthly insights', 'reports/', 'View reports', 'Your month in perspective', 'Activity and financial summaries will appear here.'],
        ];
        ?>

        <?php foreach ($widgets as $widget): ?>
            <article class="overview-card">
                <div class="widget-heading">
                    <h3><span class="widget-icon"><?= e($widget[0]) ?></span><?= e($widget[1]) ?></h3>
                    <a href="<?= e(url($widget[2])) ?>"><?= e($widget[3]) ?> ↗</a>
                </div>

                <div class="empty-widget">
                    <span class="empty-symbol"><?= e($widget[0]) ?></span>
                    <strong><?= e($widget[4]) ?></strong>
                    <p><?= e($widget[5]) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="dashboard-bottom">
    <div class="local-status">
        <span class="local-status-indicator"></span>
        <div>
            <strong>Private by design</strong>
            <span>Your workspace runs locally on this device.</span>
        </div>
    </div>

    <span class="database-status">
        <?= $dbConnected ? 'Database connected' : 'Waiting for your first data' ?>
    </span>
</section>

<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
