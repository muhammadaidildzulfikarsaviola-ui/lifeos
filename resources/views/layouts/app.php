<?php declare(strict_types=1); ?>
<!doctype html><html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="<?= e($csrfToken ?? csrf_token()) ?>"><meta name="theme-color" content="#f5f4f0">
<title><?= e($appName ?? app_config('name','LIFEOS')) ?></title>
<link rel="stylesheet" href="/assets/css/app.css"><script src="/assets/js/app.js" defer></script></head>
<body><div class="app-shell"><aside class="sidebar">
<a class="brand" href="/"><span class="brand-mark">L</span><span>LIFEOS</span></a><div class="sidebar-label">WORKSPACE</div><nav aria-label="Main navigation">
<a class="nav-link active" href="/">⌂　Overview</a><a class="nav-link" href="/finance">↗　Finance</a><a class="nav-link" href="/schedule">▦　Schedule</a><a class="nav-link" href="/tasks">☷　Tasks</a><a class="nav-link" href="/habits">◷　Habits</a><a class="nav-link" href="/journal">▤　Journal</a><a class="nav-link" href="/notes">▧　Notes</a><a class="nav-link" href="/goals">◎　Goals</a><a class="nav-link" href="/projects">◇　Projects</a><a class="nav-link" href="/reports">▥　Reports</a>
</nav><div class="sidebar-footer"><span class="local-indicator"></span> Local workspace</div></aside>
<main class="main-area"><header class="topbar"><div><span class="topbar-kicker">PERSONAL WORKSPACE</span><strong>Overview</strong></div><div class="topbar-right"><span class="date-label"><?= e((new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta')))->format('D, d M Y')) ?></span><span class="avatar">K</span></div></header><div class="page-content"><?= $content ?></div><footer class="app-footer">LIFEOS · Local-first by design</footer></main></div></body></html>
