<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$pageTitle = 'Goals';
$currentPage = 'goals';
$moduleName = 'Goals';
$moduleDescription = 'Keep your direction, milestones, and progress visible.';
$moduleEmptyTitle = 'Goal tracking is not available yet';
$moduleEmptyMessage = 'Goal creation, milestones, and progress tracking have not been implemented yet.';

require LIFEOS_ROOT . '/includes/header.php';
?>
<?php require LIFEOS_ROOT . '/includes/module_placeholder.php'; ?>
<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
