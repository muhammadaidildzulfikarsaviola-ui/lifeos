<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'LIFEOS';
$currentPage = $currentPage ?? 'dashboard';

$navigation = [
    ['slug' => 'dashboard', 'label' => 'Dashboard', 'icon' => '⌂', 'path' => ''],
    ['slug' => 'finance', 'label' => 'Finance', 'icon' => '↗', 'path' => 'finance/'],
    ['slug' => 'schedule', 'label' => 'Schedule', 'icon' => '▦', 'path' => 'schedule/'],
    ['slug' => 'tasks', 'label' => 'Tasks', 'icon' => '☷', 'path' => 'tasks/'],
    ['slug' => 'habits', 'label' => 'Habits', 'icon' => '◷', 'path' => 'habits/'],
    ['slug' => 'journal', 'label' => 'Journal', 'icon' => '▤', 'path' => 'journal/'],
    ['slug' => 'notes', 'label' => 'Notes', 'icon' => '▧', 'path' => 'notes/'],
    ['slug' => 'goals', 'label' => 'Goals', 'icon' => '◎', 'path' => 'goals/'],
    ['slug' => 'projects', 'label' => 'Projects', 'icon' => '◇', 'path' => 'projects/'],
    ['slug' => 'reports', 'label' => 'Reports', 'icon' => '▥', 'path' => 'reports/'],
];

$moduleCss = [
    'finance' => 'finance.css',
    'schedule' => 'schedule.css',
    'tasks' => 'tasks.css',
    'habits' => 'habits.css',
];
$moduleJs = [
    'finance' => 'finance.js',
    'schedule' => 'schedule.js',
    'tasks' => 'tasks.js',
    'habits' => 'habits.js',
];

$globalCss = url('assets/css/app.css');
$moduleCssUrl = isset($moduleCss[$currentPage])
    ? url('assets/css/modules/' . $moduleCss[$currentPage])
    : null;

$pageCss = $pageCss ?? null;
$pageCssUrl = $pageCss !== null ? url('assets/css/' . ltrim($pageCss, '/')) : null;
$moduleJsUrl = isset($moduleJs[$currentPage]) ? url('assets/js/modules/' . $moduleJs[$currentPage]) : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?> | LIFEOS</title>

    <link rel="stylesheet" href="<?= e($globalCss) ?>">

    <?php if ($moduleCssUrl !== null): ?>
        <link rel="stylesheet" href="<?= e($moduleCssUrl) ?>">
    <?php endif; ?>

    <?php if ($pageCssUrl !== null): ?>
        <link rel="stylesheet" href="<?= e($pageCssUrl) ?>">
    <?php endif; ?>
</head>

<body>
<div class="app-shell">

    <aside class="sidebar">
        <a href="<?= e(url('')) ?>" class="sidebar-brand">
            <span class="brand-mark">L</span>
            <span class="brand-name">LIFEOS</span>
        </a>

        <div class="sidebar-section">
            <p class="sidebar-label">WORKSPACE</p>

            <nav class="sidebar-nav">
                <?php foreach ($navigation as $item): ?>
                    <a
                        href="<?= e(url($item['path'])) ?>"
                        class="sidebar-link <?= $currentPage === $item['slug'] ? 'active' : '' ?>"
                    >
                        <span class="sidebar-icon"><?= e($item['icon']) ?></span>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="sidebar-bottom">
            <span class="sidebar-version">LIFEOS · LOCAL WORKSPACE</span>
        </div>
    </aside>

    <main class="main-area">
        <header class="topbar">
            <div class="topbar-left">
                <span class="topbar-context">Personal workspace</span>
            </div>

            <div class="topbar-right">
                <span class="local-indicator">
                    <span></span>
                    Local
                </span>
            </div>
        </header>

        <div class="page-content">
