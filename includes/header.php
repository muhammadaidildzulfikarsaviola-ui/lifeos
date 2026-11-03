<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? 'Dashboard';
$currentPage = $currentPage ?? '';
$navItems = [
    'dashboard' => ['Overview', ''],
    'finance' => ['Finance', 'finance/'],
    'schedule' => ['Schedule', 'schedule/'],
    'tasks' => ['Tasks', 'tasks/'],
    'habits' => ['Habits', 'habits/'],
    'journal' => ['Journal', 'journal/'],
    'notes' => ['Notes', 'notes/'],
    'goals' => ['Goals', 'goals/'],
    'projects' => ['Projects', 'projects/'],
    'reports' => ['Reports', 'reports/'],
];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="theme-color" content="#f6f7f8">
    <title><?= e($pageTitle) ?> · LIFEOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet">
    <script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="<?= e(url()) ?>"><span class="brand-mark">L</span><span>LIFEOS</span></a>
        <p class="sidebar-label">WORKSPACE</p>
        <nav class="nav-list" aria-label="Main navigation">
            <?php foreach ($navItems as $key => [$label, $path]): ?>
                <a class="nav-link <?= $currentPage === $key ? 'active' : '' ?>" href="<?= e(url($path)) ?>" <?= $currentPage === $key ? 'aria-current="page"' : '' ?>>
                    <span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer"><span class="local-dot"></span> Local workspace</div>
    </aside>
    <main class="main-area">
        <header class="topbar">
            <div><span class="topbar-kicker">PERSONAL WORKSPACE</span><strong><?= e($pageTitle) ?></strong></div>
            <div class="topbar-right">
                <label class="topbar-search"><span aria-hidden="true">⌕</span><input type="search" placeholder="Search your workspace" aria-label="Search your workspace" disabled></label>
                <span class="date-label"><?= e(date('D, d M Y')) ?></span>
                <span class="topbar-avatar" aria-label="Local profile">K</span>
            </div>
        </header>
        <div class="page-content">
