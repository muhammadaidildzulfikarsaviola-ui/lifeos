<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$pageTitle = 'Notes';
$currentPage = 'notes';
$moduleName = 'Notes';
$moduleDescription = 'Capture ideas, references, and quick reminders.';
$moduleEmptyTitle = 'Notes are not available yet';
$moduleEmptyMessage = 'Note creation, editing, and search have not been implemented yet.';

require LIFEOS_ROOT . '/includes/header.php';
?>
<?php require LIFEOS_ROOT . '/includes/module_placeholder.php'; ?>
<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
