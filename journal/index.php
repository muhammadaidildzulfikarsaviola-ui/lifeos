<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$pageTitle = 'Journal';
$currentPage = 'journal';
$moduleName = 'Journal';
$moduleDescription = 'A private place for daily thoughts and reflections.';
$moduleEmptyTitle = 'Journal entries are not available yet';
$moduleEmptyMessage = 'Journal writing and entry history have not been implemented yet.';

require LIFEOS_ROOT . '/includes/header.php';
?>
<?php require LIFEOS_ROOT . '/includes/module_placeholder.php'; ?>
<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
