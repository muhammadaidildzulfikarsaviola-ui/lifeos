<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

$dbConnected = false;
try {
    db()->query('SELECT 1');
    $dbConnected = true;
} catch (Throwable $exception) {
    // Dashboard remains usable as a visual workspace before the database is initialized.
}

require __DIR__ . '/includes/header.php';

$modules = [
    ['finance', 'Finance', 'Money flow, accounts and budgets', '↗', 'module-finance'],
    ['schedule', 'Schedule', 'Calendar, events and shifts', '▦', 'module-schedule'],
    ['tasks', 'Tasks', 'Your work, priorities and deadlines', '☷', 'module-tasks'],
    ['habits', 'Habits', 'Build routines, one day at a time', '◷', 'module-habits'],
    ['journal', 'Journal', 'Daily thoughts and reflections', '▤', 'module-journal'],
    ['notes', 'Notes', 'Ideas, references and quick captures', '▧', 'module-notes'],
    ['goals', 'Goals', 'Milestones and personal direction', '◎', 'module-goals'],
    ['projects', 'Projects', 'Things you are building and moving forward', '◇', 'module-projects'],
    ['reports', 'Reports', 'Patterns, progress and insights', '▥', 'module-reports'],
];
?>
<section class="dashboard-intro">
    <div>
        <p class="eyebrow">YOUR PERSONAL WORKSPACE</p>
        <h1>Make room for <span>what matters.</span></h1>
        <p class="intro-copy">A clear view of your day, your plans, and everything you're working toward.</p>
    </div>
    <div class="intro-date">
        <span class="date-day"><?= e(date('l')) ?></span>
        <strong><?= e(date('d')) ?></strong>
        <span><?= e(date('F Y')) ?></span>
    </div>
</section>

<section class="dashboard-section">
    <div class="section-heading">
        <div><p class="eyebrow">YOUR SPACE</p><h2>Workspace</h2></div>
        <span class="section-note">9 areas to keep life in motion</span>
    </div>
    <div class="workspace-grid">
        <?php foreach ($modules as [$slug, $name, $description, $icon, $tone]): ?>
            <a class="workspace-card <?= e($tone) ?>" href="<?= e(url($slug . '/')) ?>">
                <span class="workspace-card-top">
                    <span class="workspace-icon"><?= e($icon) ?></span>
                    <span class="card-open" aria-hidden="true">↗</span>
                </span>
                <span class="workspace-card-title"><?= e($name) ?></span>
                <span class="workspace-card-description"><?= e($description) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="dashboard-section">
    <div class="section-heading">
        <div><p class="eyebrow">AT A GLANCE</p><h2>Daily overview</h2></div>
        <span class="section-note">Your dashboard widgets</span>
    </div>
    <div class="overview-grid">
        <article class="overview-card overview-wide">
            <div class="widget-heading"><div><span class="widget-icon">◷</span><h3>Today's schedule</h3></div><a href="<?= e(url('schedule/')) ?>">Open schedule ↗</a></div>
            <div class="empty-widget"><span class="empty-symbol">▦</span><strong>Your day, at a glance</strong><p>Upcoming events and time blocks will appear here.</p></div>
        </article>
        <article class="overview-card">
            <div class="widget-heading"><div><span class="widget-icon">☷</span><h3>Tasks to focus on</h3></div><a href="<?= e(url('tasks/')) ?>">View tasks ↗</a></div>
            <div class="empty-widget"><span class="empty-symbol">✓</span><strong>Nothing on the list yet</strong><p>Your next priorities will live here.</p></div>
        </article>
        <article class="overview-card">
            <div class="widget-heading"><div><span class="widget-icon">◉</span><h3>Habit tracker</h3></div><a href="<?= e(url('habits/')) ?>">View habits ↗</a></div>
            <div class="empty-widget"><span class="empty-symbol">◷</span><strong>Small steps add up</strong><p>Your daily habit progress will show here.</p></div>
        </article>
        <article class="overview-card">
            <div class="widget-heading"><div><span class="widget-icon">↗</span><h3>Finance snapshot</h3></div><a href="<?= e(url('finance/')) ?>">Open finance ↗</a></div>
            <div class="empty-widget"><span class="empty-symbol">＄</span><strong>Your money overview</strong><p>Balances, spending and budgets will appear here.</p></div>
        </article>
        <article class="overview-card">
            <div class="widget-heading"><div><span class="widget-icon">◇</span><h3>Active projects</h3></div><a href="<?= e(url('projects/')) ?>">View projects ↗</a></div>
            <div class="empty-widget"><span class="empty-symbol">◇</span><strong>Build something meaningful</strong><p>Your active projects and progress will show here.</p></div>
        </article>
        <article class="overview-card">
            <div class="widget-heading"><div><span class="widget-icon">◎</span><h3>Goals & milestones</h3></div><a href="<?= e(url('goals/')) ?>">View goals ↗</a></div>
            <div class="empty-widget"><span class="empty-symbol">◎</span><strong>Keep your direction in sight</strong><p>Your goals and milestones will appear here.</p></div>
        </article>
        <article class="overview-card">
            <div class="widget-heading"><div><span class="widget-icon">▤</span><h3>Recent journal</h3></div><a href="<?= e(url('journal/')) ?>">Write an entry ↗</a></div>
            <div class="empty-widget"><span class="empty-symbol">✎</span><strong>A space for your thoughts</strong><p>Your latest reflections will appear here.</p></div>
        </article>
        <article class="overview-card">
            <div class="widget-heading"><div><span class="widget-icon">▧</span><h3>Quick notes</h3></div><a href="<?= e(url('notes/')) ?>">Open notes ↗</a></div>
            <div class="empty-widget"><span class="empty-symbol">＋</span><strong>Catch an idea before it goes</strong><p>Your pinned and recent notes will show here.</p></div>
        </article>
        <article class="overview-card">
            <div class="widget-heading"><div><span class="widget-icon">▥</span><h3>Upcoming bills</h3></div><a href="<?= e(url('finance/')) ?>">Manage bills ↗</a></div>
            <div class="empty-widget"><span class="empty-symbol">⌁</span><strong>No reminders to show</strong><p>Bill and installment reminders will appear here.</p></div>
        </article>
        <article class="overview-card">
            <div class="widget-heading"><div><span class="widget-icon">⌁</span><h3>Monthly insights</h3></div><a href="<?= e(url('reports/')) ?>">View reports ↗</a></div>
            <div class="empty-widget"><span class="empty-symbol">▥</span><strong>Your month in perspective</strong><p>Activity and financial summaries will appear here.</p></div>
        </article>
    </div>
</section>

<section class="dashboard-bottom">
    <div class="local-status"><span class="local-status-indicator"></span><div><strong>Private by design</strong><span>Your workspace runs locally on this device.</span></div></div>
    <span class="database-status"><?= $dbConnected ? 'Database connected' : 'Waiting for your first data' ?></span>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
