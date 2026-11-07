<?php
declare(strict_types=1);

$moduleName = $moduleName ?? $pageTitle;
$moduleDescription = $moduleDescription ?? '';
$moduleEmptyTitle = $moduleEmptyTitle ?? 'Nothing here yet';
$moduleEmptyMessage = $moduleEmptyMessage ?? 'This space is ready for future entries.';
?>
<section class="module-placeholder" aria-labelledby="module-title">
    <p class="eyebrow">PERSONAL WORKSPACE</p>
    <h1 id="module-title"><?= e($moduleName) ?></h1>
    <p class="module-description"><?= e($moduleDescription) ?></p>
    <div class="module-empty-state" role="status">
        <span class="module-empty-mark" aria-hidden="true">○</span>
        <div>
            <h2><?= e($moduleEmptyTitle) ?></h2>
            <p><?= e($moduleEmptyMessage) ?></p>
        </div>
    </div>
</section>
