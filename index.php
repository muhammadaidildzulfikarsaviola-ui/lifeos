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
} catch (Throwable $exception) {
    // Dashboard tetap bisa dibuka tanpa database.
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | LIFEOS</title>

<style>
:root {
    --bg: #f7f7f5;
    --surface: #ffffff;
    --ink: #242424;
    --muted: #737373;
    --line: #e5e5e0;
    --primary: #315c4c;
    --radius: 12px;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: var(--bg);
    color: var(--ink);
    font-family: Arial, sans-serif;
    font-size: 14px;
}

a {
    color: inherit;
    text-decoration: none;
}

.app-shell {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    width: 248px;
    flex-shrink: 0;
    background: var(--surface);
    border-right: 1px solid var(--line);
    padding: 28px 18px;
    display: flex;
    flex-direction: column;
}

.brand {
    display: flex;
    align-items: center;
    gap: 11px;
    margin-bottom: 42px;
}

.brand-mark {
    width: 36px;
    height: 36px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: var(--primary);
    color: white;
    font-weight: 700;
}

.brand-name {
    font-size: 17px;
    font-weight: 700;
    letter-spacing: 1px;
}

.sidebar-label,
.eyebrow {
    color: var(--muted);
    font-size: 10px;
    letter-spacing: 1.5px;
    font-weight: 700;
}

.sidebar-label {
    margin: 0 0 12px 10px;
}

.sidebar-nav {
    display: grid;
    gap: 5px;
}

.sidebar-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 12px;
    border-radius: 8px;
    color: #626262;
    transition: .2s;
}

.sidebar-link:hover,
.sidebar-link.active {
    background: #edf2ef;
    color: var(--primary);
}

.sidebar-icon {
    width: 20px;
    text-align: center;
    font-size: 17px;
}

.sidebar-bottom {
    margin-top: auto;
    color: var(--muted);
    font-size: 11px;
}

.main-area {
    flex: 1;
    min-width: 0;
}

.topbar {
    height: 72px;
    background: white;
    border-bottom: 1px solid var(--line);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 42px;
}

.topbar-context {
    font-weight: 600;
}

.local-indicator {
    color: var(--muted);
    font-size: 12px;
}

.local-indicator span {
    display: inline-block;
    width: 7px;
    height: 7px;
    margin-right: 6px;
    border-radius: 50%;
    background: #4b9a70;
}

.page-content {
    max-width: 1440px;
    margin: 0 auto;
    padding: 42px;
}

.dashboard-intro {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 52px;
}

.dashboard-intro h1 {
    max-width: 650px;
    margin: 14px 0;
    font-size: clamp(30px, 4vw, 44px);
    line-height: 1.15;
    letter-spacing: -1.8px;
}

.dashboard-intro h1 span {
    color: var(--primary);
}

.intro-copy {
    color: var(--muted);
    font-size: 14px;
}

.intro-date {
    min-width: 130px;
    padding: 15px 18px;
    background: white;
    border: 1px solid var(--line);
    border-radius: var(--radius);
    text-align: center;
}

.date-day {
    display: block;
    color: var(--muted);
    font-size: 11px;
}

.date-number {
    display: block;
    margin: 5px 0;
    font-size: 30px;
    font-weight: 700;
}

.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 20px;
}

.section-heading h2 {
    margin: 6px 0 0;
    font-size: 23px;
}

.section-note {
    color: var(--muted);
    font-size: 12px;
}

.workspace-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.workspace-card {
    display: flex;
    flex-direction: column;
    min-height: 155px;
    padding: 20px;
    background: white;
    border: 1px solid var(--line);
    border-radius: var(--radius);
    transition: .2s;
}

.workspace-card:hover {
    transform: translateY(-3px);
    border-color: #b8c9bf;
    box-shadow: 0 8px 24px #00000008;
}

.workspace-card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
}

.workspace-icon {
    width: 35px;
    height: 35px;
    display: grid;
    place-items: center;
    background: #edf2ef;
    color: var(--primary);
    border-radius: 9px;
    font-size: 18px;
}

.card-open {
    color: #a0a0a0;
}

.workspace-card-title {
    font-weight: 700;
    font-size: 15px;
    margin-bottom: 6px;
}

.workspace-card-description {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.5;
}

.dashboard-section {
    margin-bottom: 48px;
}

.overview-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.overview-card {
    padding: 21px;
    background: white;
    border: 1px solid var(--line);
    border-radius: var(--radius);
}

.widget-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.widget-heading h3 {
    margin: 0;
    font-size: 14px;
}

.widget-heading a {
    color: var(--primary);
    font-size: 11px;
}

.widget-icon {
    margin-right: 8px;
    color: var(--primary);
}

.empty-widget {
    min-height: 115px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: var(--muted);
}

.empty-symbol {
    margin-bottom: 9px;
    font-size: 23px;
    color: #a0b2a8;
}

.empty-widget strong {
    color: var(--ink);
    font-size: 13px;
}

.empty-widget p {
    margin: 6px 0 0;
    font-size: 12px;
}

.dashboard-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    padding: 18px 20px;
    background: white;
    border: 1px solid var(--line);
    border-radius: var(--radius);
}

.local-status {
    display: flex;
    align-items: center;
    gap: 11px;
}

.local-status-indicator {
    width: 9px;
    height: 9px;
    background: #4b9a70;
    border-radius: 50%;
}

.local-status strong,
.local-status span {
    display: block;
}

.local-status strong {
    font-size: 12px;
}

.local-status div span {
    margin-top: 3px;
    color: var(--muted);
    font-size: 11px;
}

.database-status {
    color: var(--muted);
    font-size: 11px;
}

@media (max-width: 900px) {
    .sidebar {
        width: 205px;
    }

    .page-content {
        padding: 28px;
    }

    .workspace-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .sidebar {
        display: none;
    }

    .topbar {
        padding: 0 20px;
    }

    .page-content {
        padding: 24px 18px;
    }

    .dashboard-intro {
        align-items: flex-start;
        flex-direction: column;
    }

    .workspace-grid,
    .overview-grid {
        grid-template-columns: 1fr;
    }

    .section-note {
        display: none;
    }

    .dashboard-bottom {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
</head>

<body>
<div class="app-shell">

<aside class="sidebar">
    <a href="<?= e(url('')) ?>" class="brand">
        <span class="brand-mark">L</span>
        <span class="brand-name">LIFEOS</span>
    </a>

    <p class="sidebar-label">WORKSPACE</p>

    <nav class="sidebar-nav">
        <a href="<?= e(url('')) ?>" class="sidebar-link active">
            <span class="sidebar-icon">⌂</span> Dashboard
        </a>

        <?php foreach ($modules as $module): ?>
            <a href="<?= e(url($module[0] . '/')) ?>" class="sidebar-link">
                <span class="sidebar-icon"><?= e($module[3]) ?></span>
                <?= e($module[1]) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-bottom">LIFEOS · LOCAL WORKSPACE</div>
</aside>

<main class="main-area">

<header class="topbar">
    <span class="topbar-context">Personal workspace</span>
    <span class="local-indicator"><span></span>Local</span>
</header>

<div class="page-content">

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
            ['▥', 'Upcoming bills', 'finance/', 'Manage bills', 'No reminders to show', 'Bill and installment reminders will appear here.'],
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

</div>

<footer style="padding:20px 42px;color:#888;font-size:11px;">
    LIFEOS · Your life, in one place.
</footer>

</main>
</div>
</body>
</html>