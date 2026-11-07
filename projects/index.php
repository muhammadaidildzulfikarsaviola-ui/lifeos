<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$pageTitle = 'Projects';
$currentPage = 'projects';
$moduleName = 'Projects';
$moduleDescription = 'Organize the work you are building and moving forward.';
$moduleEmptyTitle = 'Project tracking is not available yet';
$moduleEmptyMessage = 'Project creation, task boards, and progress tracking have not been implemented yet.';

require LIFEOS_ROOT . '/includes/header.php';
?>
<?php require LIFEOS_ROOT . '/includes/module_placeholder.php'; ?>
<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
