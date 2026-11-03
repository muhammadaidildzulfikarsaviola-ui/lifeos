<?php declare(strict_types=1); ?>
<section class="hero">
    <span class="eyebrow">PERSONAL OPERATING SYSTEM</span>
    <h1><?= e($appName) ?></h1>
    <p class="lead">Satu tempat untuk mengelola hal-hal yang penting dalam hidup.</p>
    <div class="status-line"><span class="status-dot"></span> Application core is running <span class="version">v<?= e($version) ?></span></div>
    <a class="button" href="/api/health" target="_blank" rel="noopener">View system health ↗</a>
</section>
