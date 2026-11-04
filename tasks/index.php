<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';

$pageTitle = 'Tasks · Ringkasan';
$currentPage = 'tasks';

require LIFEOS_ROOT . '/includes/header.php';

// Handle POST actions (create, update, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $tasksService->createTask([
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? null,
                'status' => $_POST['status'] ?? 'todo',
                'priority' => $_POST['priority'] ?? 'medium',
                'due_date' => $_POST['due_date'] ?: null,
                'category_id' => $_POST['category_id'] ?: null,
            ]);
            set_flash('success', 'Task berhasil dibuat.');
            redirect(url('tasks/index.php'));
        } elseif ($action === 'update' && isset($_POST['id'])) {
            $tasksService->updateTask((int)$_POST['id'], [
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? null,
                'status' => $_POST['status'] ?? 'todo',
                'priority' => $_POST['priority'] ?? 'medium',
                'due_date' => $_POST['due_date'] ?: null,
                'category_id' => $_POST['category_id'] ?: null,
            ]);
            set_flash('success', 'Task berhasil diperbarui.');
            redirect(url('tasks/index.php'));
        } elseif ($action === 'delete' && isset($_POST['id'])) {
            $tasksService->softDeleteTask((int)$_POST['id']);
            set_flash('success', 'Task berhasil dihapus.');
            redirect(url('tasks/index.php'));
        }
    } catch (Throwable $e) {
        set_flash('error', $e->getMessage());
        redirect(url('tasks/index.php'));
    }
}

// Fetch tasks (optional filters via GET)
$filters = [];
if (!empty($_GET['status'])) { $filters['status'] = $_GET['status']; }
if (!empty($_GET['priority'])) { $filters['priority'] = $_GET['priority']; }
if (!empty($_GET['due_date'])) { $filters['due_date'] = $_GET['due_date']; }
$tasks = $tasksService ? $tasksService->listTasks($filters) : [];
?>
<div class="tasks-page">
    <?php render_tasks_nav('overview'); ?>
    <?php render_tasks_alerts($dbError); ?>

    <section class="tasks-section">
        <h2>Daftar Task</h2>
        <a href="<?= e(url('tasks/tasks.php?action=create')) ?>" class="tasks-btn tasks-btn-primary">Tambah Task</a>
        <?php if (empty($tasks)): ?>
            <p class="tasks-empty-state">Tidak ada task.</p>
        <?php else: ?>
            <table class="tasks-table">
                <thead>
                    <tr>
                        <th>Judul</th>
                        <th>Status</th>
                        <th>Prioritas</th>
                        <th>Jatuh Tempo</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                        <tr>
                            <td><?= e($task['title']) ?></td>
                            <td><?= e($task['status']) ?></td>
                            <td><?= e($task['priority']) ?></td>
                            <td><?= e($task['due_date'] ?? '-') ?></td>
                            <td>
                                <a href="<?= e(url('tasks/tasks.php?action=edit&id=' . $task['id'])) ?>" class="tasks-link">Ubah</a>
                                <form method="post" action="<?= e(url('tasks/index.php')) ?>" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e($task['id']) ?>">
                                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                    <button type="submit" class="tasks-link tasks-delete" onclick="return confirm('Yakin hapus task?')">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>
<?php require LIFEOS_ROOT . '/includes/footer.php'; ?>
