<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Modules/Tasks/init.php';

$pageTitle = 'Tasks';
$currentPage = 'tasks';
$action = request_string($_GET['action'] ?? '');
$taskId = max(0, request_integer($_GET['id'] ?? 0));
$formError = null;
if (in_array($action, ['edit', 'view'], true) && $taskId < 1) {
    http_response_code(400);
    $formError = 'Task id tidak valid.';
    $action = '';
}
$formValues = [
    'title' => '', 'description' => '', 'status' => 'todo',
    'priority' => 'medium', 'due_date' => '', 'category_id' => '',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $postAction = request_string($_POST['action'] ?? '');
    try {
        if ($tasksService === null || $dbError !== null) {
            throw new RuntimeException('Layanan Tasks belum siap.');
        }
        if ($postAction === 'delete') {
            $tasksService->softDeleteTask(max(0, request_integer($_POST['id'] ?? 0)));
            set_flash('success', 'Task dipindahkan ke arsip. Jadwal terkait tetap tersimpan.');
            redirect('tasks/');
        }
        if ($postAction === 'status') {
            $tasksService->setTaskStatus(
                max(0, request_integer($_POST['id'] ?? 0)),
                request_string($_POST['status'] ?? '')
            );
            set_flash('success', 'Status task diperbarui.');
            redirect('tasks/');
        }
        if (!in_array($postAction, ['create', 'update'], true)) {
            http_response_code(400);
            throw new InvalidArgumentException('Tindakan Tasks tidak dikenali.');
        }
        $formValues = [
            'title' => request_string($_POST['title'] ?? ''),
            'description' => request_string($_POST['description'] ?? ''),
            'status' => request_string($_POST['status'] ?? 'todo'),
            'priority' => request_string($_POST['priority'] ?? 'medium'),
            'due_date' => request_string($_POST['due_date'] ?? ''),
            'category_id' => request_string($_POST['category_id'] ?? ''),
        ];
        if ($postAction === 'update') {
            $taskId = max(0, request_integer($_POST['id'] ?? 0));
            $tasksService->updateTask($taskId, $formValues);
            set_flash('success', 'Task berhasil diperbarui.');
        } else {
            $tasksService->createTask($formValues);
            set_flash('success', 'Task berhasil dibuat.');
        }
        redirect('tasks/');
    } catch (InvalidArgumentException | RuntimeException $e) {
        $formError = $e->getMessage();
    } catch (Throwable $e) {
        $formError = 'Task tidak dapat disimpan saat ini. Periksa koneksi database lalu coba kembali.';
        error_log('[Tasks] ' . $e->getMessage());
    }
}

$editing = in_array($action, ['create', 'edit'], true);
$viewing = $action === 'view';
$task = null;
$scheduleEvents = [];
$scheduleLoadError = null;
$tasks = [];
$summary = ['total' => 0, 'pending' => 0, 'completed' => 0, 'overdue' => 0];
$filters = [
    'status' => request_string($_GET['status'] ?? ''),
    'priority' => request_string($_GET['priority'] ?? ''),
    'due' => request_string($_GET['due'] ?? ''),
    'search' => trim(request_string($_GET['search'] ?? '')),
];
if (!in_array($filters['status'], ['', 'pending', 'todo', 'in_progress', 'done', 'cancelled'], true)) $filters['status'] = '';
if (!in_array($filters['priority'], ['', 'low', 'medium', 'high', 'critical'], true)) $filters['priority'] = '';
if (!in_array($filters['due'], ['', 'overdue', 'upcoming', 'no_due'], true)) $filters['due'] = '';
$sort = request_string($_GET['sort'] ?? 'due_date');
if (!in_array($sort, ['due_date', 'priority', 'created', 'updated'], true)) $sort = 'due_date';

if ($tasksService !== null && $dbError === null) {
    try {
        $summary = $tasksService->getSummary();
        if ($taskId > 0 && ($editing || $viewing)) {
            $task = $tasksService->getTask($taskId);
            if ($task === null) {
                http_response_code(404);
                $formError = 'Task tidak ditemukan atau sudah diarsipkan.';
                $editing = false;
                $viewing = false;
            } elseif ($editing && $formError === null) {
                $formValues = [
                    'title' => (string) $task['title'],
                    'description' => (string) ($task['description'] ?? ''),
                    'status' => (string) $task['status'],
                    'priority' => (string) $task['priority'],
                    'due_date' => (string) ($task['due_date'] ?? ''),
                    'category_id' => (string) ($task['category_id'] ?? ''),
                ];
            }
            if ($viewing && $task !== null) {
                try {
                    $scheduleEvents = $tasksService->getScheduleEvents($taskId);
                } catch (Throwable $e) {
                    $scheduleLoadError = 'Informasi Schedule belum dapat dimuat.';
                    error_log('[TasksSchedule] ' . $e->getMessage());
                }
            }
        }
        $tasks = $tasksService->listTasks($filters, $sort);
    } catch (Throwable $e) {
        $dbError = 'Gagal memuat data Tasks: ' . $e->getMessage();
        error_log('[Tasks] ' . $e->getMessage());
    }
}

$statusLabels = ['todo' => 'To do', 'in_progress' => 'In progress', 'done' => 'Completed', 'cancelled' => 'Cancelled'];
$priorityLabels = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];
require LIFEOS_ROOT . '/includes/header.php';
?>
<div class="tasks-page">
    <header class="tasks-heading">
        <div>
            <p class="eyebrow">PERSONAL PLANNING</p>
            <h1><?= $editing ? ($task ? 'Edit task' : 'Create task') : ($viewing && $task ? e($task['title']) : 'Tasks') ?></h1>
            <p class="tasks-subtitle">Plan priorities, track progress, and keep deadlines visible.</p>
        </div>
        <?php if (!$editing && !$viewing && $dbError === null): ?>
            <a class="tasks-button tasks-button-primary" href="<?= e(url('tasks/index.php?action=create')) ?>">＋ Add task</a>
        <?php endif; ?>
    </header>

    <?php render_tasks_alerts($dbError); ?>

    <?php if ($formError !== null): ?>
        <div class="tasks-alert alert-error" role="alert"><?= e($formError) ?></div>
    <?php endif; ?>

    <?php if ($tasksService !== null && $dbError === null && !$editing && !$viewing): ?>
        <section class="tasks-summary" aria-label="Task summary">
            <article><span>Total tasks</span><strong><?= $summary['total'] ?></strong></article>
            <article><span>Pending</span><strong><?= $summary['pending'] ?></strong></article>
            <article><span>Completed</span><strong><?= $summary['completed'] ?></strong></article>
            <article><span>Overdue</span><strong><?= $summary['overdue'] ?></strong></article>
        </section>
    <?php endif; ?>

    <?php if ($editing): ?>
        <form class="tasks-form" method="post" action="<?= e(url('tasks/index.php' . ($task ? '?action=edit&id=' . (int) $task['id'] : '?action=create'))) ?>">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $task ? 'update' : 'create' ?>">
            <?php if ($task): ?><input type="hidden" name="id" value="<?= (int) $task['id'] ?>"><?php endif; ?>
            <label class="field-wide">Title *<input name="title" maxlength="200" required value="<?= e($formValues['title']) ?>" autofocus></label>
            <label class="field-wide">Description<textarea name="description" rows="5" maxlength="10000"><?= e($formValues['description']) ?></textarea></label>
            <label>Status<select name="status"><?php foreach ($statusLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $formValues['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label>Priority<select name="priority"><?php foreach ($priorityLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $formValues['priority'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label>Due date<input type="date" name="due_date" value="<?= e($formValues['due_date']) ?>"></label>
            <label>Category<select name="category_id"><option value="">No category</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (string) $category['id'] === $formValues['category_id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
            <div class="tasks-form-actions field-wide">
                <a class="tasks-button tasks-button-secondary" href="<?= e(url('tasks/')) ?>">Cancel</a>
                <button class="tasks-button tasks-button-primary" type="submit"><?= $task ? 'Save changes' : 'Create task' ?></button>
            </div>
        </form>
    <?php elseif ($viewing && $task !== null): ?>
        <article class="task-detail-card">
            <div class="task-detail-actions">
                <a class="tasks-button tasks-button-secondary" href="<?= e(url('tasks/index.php?action=edit&id=' . (int) $task['id'])) ?>">Edit</a>
                <a class="tasks-button tasks-button-secondary" href="<?= e(url('tasks/')) ?>">Back to tasks</a>
            </div>
            <dl class="task-detail-grid">
                <dt>Status</dt><dd><span class="task-badge status-<?= e($task['status']) ?>"><?= e($statusLabels[$task['status']] ?? $task['status']) ?></span></dd>
                <dt>Priority</dt><dd><span class="task-badge priority-<?= e($task['priority']) ?>"><?= e($priorityLabels[$task['priority']] ?? $task['priority']) ?></span></dd>
                <dt>Due date</dt><dd><?= $task['due_date'] ? e($task['due_date']) : 'No due date' ?><?= !empty($task['is_overdue']) ? ' · Overdue' : '' ?></dd>
                <dt>Category</dt><dd><?= e($task['category_name'] ?? 'No category') ?></dd>
                <dt>Created</dt><dd><?= e($task['created_at']) ?></dd>
                <dt>Updated</dt><dd><?= e($task['updated_at']) ?></dd>
                <dt>Description</dt><dd class="task-description"><?= $task['description'] !== null ? nl2br(e($task['description'])) : 'No description' ?></dd>
            </dl>
            <section class="task-schedule-section">
                <h2>Schedule events</h2>
                <?php if ($scheduleLoadError !== null): ?>
                    <p class="task-muted"><?= e($scheduleLoadError) ?></p>
                <?php elseif (!$scheduleEvents): ?>
                    <p class="task-muted">No active Schedule events are linked to this task.</p>
                <?php else: ?>
                    <ul class="task-event-list">
                        <?php foreach ($scheduleEvents as $event): ?>
                            <li><a href="<?= e(url('schedule/events.php?id=' . (int) $event['id'])) ?>"><strong><?= e($event['title']) ?></strong><span><?= e($event['start_date']) ?><?= empty($event['all_day']) && $event['start_time'] ? ' · ' . e(substr((string) $event['start_time'], 0, 5)) : ' · All day' ?></span></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </article>
    <?php else: ?>
        <form class="tasks-filter-form" method="get" action="<?= e(url('tasks/')) ?>">
            <label class="task-search-field">Search<input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Task title or description"></label>
            <label>Status<select name="status"><option value="">All statuses</option><option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending</option><?php foreach ($statusLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label>Priority<select name="priority"><option value="">All priorities</option><?php foreach ($priorityLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $filters['priority'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label>Due date<select name="due"><option value="">Any date</option><option value="overdue" <?= $filters['due'] === 'overdue' ? 'selected' : '' ?>>Overdue</option><option value="upcoming" <?= $filters['due'] === 'upcoming' ? 'selected' : '' ?>>Upcoming</option><option value="no_due" <?= $filters['due'] === 'no_due' ? 'selected' : '' ?>>No due date</option></select></label>
            <label>Sort by<select name="sort"><option value="due_date" <?= $sort === 'due_date' ? 'selected' : '' ?>>Due date</option><option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>Priority</option><option value="created" <?= $sort === 'created' ? 'selected' : '' ?>>Recently created</option><option value="updated" <?= $sort === 'updated' ? 'selected' : '' ?>>Recently updated</option></select></label>
            <button class="tasks-button tasks-button-secondary" type="submit">Apply filters</button>
            <?php if ($filters['search'] !== '' || $filters['status'] !== '' || $filters['priority'] !== '' || $filters['due'] !== '' || $sort !== 'due_date'): ?><a class="tasks-clear-link" href="<?= e(url('tasks/')) ?>">Clear</a><?php endif; ?>
        </form>

        <section id="task-list" class="task-list" aria-label="Task list">
            <?php if ($dbError !== null): ?>
                <div class="tasks-empty-state"><h2>Tasks are unavailable</h2><p><?= e($dbError) ?></p></div>
            <?php elseif (!$tasks): ?>
                <div class="tasks-empty-state"><span aria-hidden="true">☷</span><h2><?= $filters['search'] !== '' || $filters['status'] !== '' || $filters['priority'] !== '' || $filters['due'] !== '' ? 'No matching tasks' : 'No tasks yet' ?></h2><p><?= $filters['search'] !== '' || $filters['status'] !== '' || $filters['priority'] !== '' || $filters['due'] !== '' ? 'Try changing or clearing your filters.' : 'Create a task to start organizing your priorities and deadlines.' ?></p><?php if (!$filters['search'] && !$filters['status'] && !$filters['priority'] && !$filters['due']): ?><a class="tasks-button tasks-button-primary" href="<?= e(url('tasks/index.php?action=create')) ?>">＋ Add your first task</a><?php endif; ?></div>
            <?php else: ?>
                <?php foreach ($tasks as $item): ?>
                    <article class="task-card <?= !empty($item['is_overdue']) ? 'is-overdue' : '' ?>">
                        <div class="task-card-main">
                            <div class="task-card-heading"><a href="<?= e(url('tasks/index.php?action=view&id=' . (int) $item['id'])) ?>"><?= e($item['title']) ?></a><?php if (!empty($item['is_overdue'])): ?><span class="task-overdue-label">Overdue</span><?php endif; ?></div>
                            <?php if (!empty($item['description'])): ?><p class="task-card-description"><?= e(mb_strimwidth($item['description'], 0, 180, '…')) ?></p><?php endif; ?>
                            <div class="task-card-meta"><span class="task-badge status-<?= e($item['status']) ?>"><?= e($statusLabels[$item['status']] ?? $item['status']) ?></span><span class="task-badge priority-<?= e($item['priority']) ?>"><?= e($priorityLabels[$item['priority']] ?? $item['priority']) ?></span><?php if ($item['due_date']): ?><span class="task-due-date">Due <?= e($item['due_date']) ?></span><?php else: ?><span class="task-muted">No due date</span><?php endif; ?><?php if ($item['category_name']): ?><span class="task-category"><?= e($item['category_name']) ?></span><?php endif; ?></div>
                        </div>
                        <div class="task-card-actions">
                            <?php if (!in_array($item['status'], ['done', 'cancelled'], true)): ?><form method="post" action="<?= e(url('tasks/')) ?>"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="status" value="done"><button class="tasks-button tasks-button-small tasks-button-primary" type="submit">Complete</button></form><?php else: ?><form method="post" action="<?= e(url('tasks/')) ?>"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="status" value="todo"><button class="tasks-button tasks-button-small tasks-button-secondary" type="submit">Reopen</button></form><?php endif; ?>
                            <a class="tasks-button tasks-button-small tasks-button-secondary" href="<?= e(url('tasks/index.php?action=edit&id=' . (int) $item['id'])) ?>">Edit</a>
                            <form method="post" action="<?= e(url('tasks/')) ?>" data-confirm="Archive this task? Linked Schedule events will be retained."><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="tasks-button tasks-button-small tasks-button-danger" type="submit">Archive</button></form>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
