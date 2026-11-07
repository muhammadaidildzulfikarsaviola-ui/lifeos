<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$pageTitle = 'Reports';
$currentPage = 'reports';
$moduleName = 'Reports';
$moduleDescription = 'Review patterns, progress, and useful summaries.';
$moduleEmptyTitle = 'Reports are not available yet';
$moduleEmptyMessage = 'No report views or insights are currently implemented.';

require LIFEOS_ROOT . '/includes/header.php';
?>
<?php require LIFEOS_ROOT . '/includes/module_placeholder.php'; ?>
<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
