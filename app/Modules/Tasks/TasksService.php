<?php
declare(strict_types=1);

namespace App\Modules\Tasks;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

class TasksService
{
    private PDO $db;

    private const STATUSES = ['todo', 'in_progress', 'done', 'cancelled'];
    private const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function getDb(): PDO
    {
        return $this->db;
    }

    public function isInstalled(): bool
    {
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'tasks'");
            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    public function isSchemaReady(): bool
    {
        try {
            foreach (['tasks', 'task_categories'] as $table) {
                $stmt = $this->db->prepare(
                    'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
                );
                $stmt->execute(['table' => $table]);
                if ((int) $stmt->fetchColumn() !== 1) {
                    return false;
                }
            }
            $columns = $this->db->query('SHOW COLUMNS FROM tasks')->fetchAll(PDO::FETCH_COLUMN);
            foreach (['id', 'title', 'description', 'status', 'priority', 'due_date', 'completed_at', 'category_id', 'created_at', 'updated_at', 'deleted_at'] as $column) {
                if (!in_array($column, $columns, true)) {
                    return false;
                }
            }
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function listCategories(): array
    {
        return $this->db->query('SELECT id, name, color FROM task_categories ORDER BY name')->fetchAll();
    }

    public function listTasks(array $filters = [], string $sort = 'due_date'): array
    {
        [$where, $params] = $this->buildFilters($filters);
        $orderBy = match ($sort) {
            'priority' => "FIELD(t.priority, 'critical', 'high', 'medium', 'low'), t.due_date IS NULL, t.due_date ASC, t.created_at DESC",
            'created' => 't.created_at DESC, t.id DESC',
            'updated' => 't.updated_at DESC, t.id DESC',
            default => "t.due_date IS NULL, t.due_date ASC, FIELD(t.priority, 'critical', 'high', 'medium', 'low'), t.created_at DESC",
        };
        $stmt = $this->db->prepare(
            "SELECT t.*, c.name AS category_name, c.color AS category_color,
                    (t.due_date IS NOT NULL AND t.due_date < :today AND t.status NOT IN ('done','cancelled')) AS is_overdue
             FROM tasks t
             LEFT JOIN task_categories c ON c.id = t.category_id
             WHERE {$where}
             ORDER BY {$orderBy}"
        );
        $stmt->execute($params + ['today' => date('Y-m-d')]);
        return $stmt->fetchAll();
    }

    public function countTasks(array $filters = []): int
    {
        [$where, $params] = $this->buildFilters($filters);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM tasks t WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getSummary(): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(status IN ('todo','in_progress')) AS pending,
                    SUM(status = 'done') AS completed,
                    SUM(due_date < :today AND status NOT IN ('done','cancelled')) AS overdue
             FROM tasks WHERE deleted_at IS NULL"
        );
        $stmt->execute(['today' => date('Y-m-d')]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int) ($summary['total'] ?? 0),
            'pending' => (int) ($summary['pending'] ?? 0),
            'completed' => (int) ($summary['completed'] ?? 0),
            'overdue' => (int) ($summary['overdue'] ?? 0),
        ];
    }

    public function getTask(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, c.name AS category_name, c.color AS category_color,
                    (t.due_date IS NOT NULL AND t.due_date < :today AND t.status NOT IN (\'done\',\'cancelled\')) AS is_overdue
             FROM tasks t
             LEFT JOIN task_categories c ON c.id = t.category_id
             WHERE t.id = :id AND t.deleted_at IS NULL'
        );
        $stmt->execute(['today' => date('Y-m-d'), 'id' => $id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        return $task ?: null;
    }

    public function getScheduleEvents(int $taskId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, title, start_date, start_time, end_date, end_time, all_day, status, recurrence
             FROM schedule_events WHERE task_id = :task_id AND deleted_at IS NULL
             ORDER BY start_date, start_time, id'
        );
        $stmt->execute(['task_id' => $taskId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createTask(array $data): int
    {
        $data = $this->validateTaskData($data);
        $stmt = $this->db->prepare(
            "INSERT INTO tasks (title, description, status, priority, due_date, completed_at, category_id)
             VALUES (:title, :description, :status, :priority, :due_date,
                     CASE WHEN :completed_status = 'done' THEN CURRENT_TIMESTAMP ELSE NULL END, :category_id)"
        );
        $stmt->execute($data + ['completed_status' => $data['status']]);
        return (int) $this->db->lastInsertId();
    }

    public function updateTask(int $id, array $data): void
    {
        if ($id < 1 || $this->getTask($id) === null) {
            throw new RuntimeException('Task tidak ditemukan.');
        }
        $data = $this->validateTaskData($data);
        $stmt = $this->db->prepare(
            "UPDATE tasks SET title = :title, description = :description, status = :status,
                    priority = :priority, due_date = :due_date,
                    completed_at = CASE WHEN :completed_status = 'done' THEN COALESCE(completed_at, CURRENT_TIMESTAMP) ELSE NULL END,
                    category_id = :category_id
             WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute($data + ['completed_status' => $data['status'], 'id' => $id]);
    }

    public function setTaskStatus(int $id, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Status task tidak valid.');
        }
        if ($id < 1 || $this->getTask($id) === null) {
            throw new RuntimeException('Task tidak ditemukan.');
        }
        $stmt = $this->db->prepare(
            "UPDATE tasks SET status = :status,
                    completed_at = CASE WHEN :completed_status = 'done' THEN COALESCE(completed_at, CURRENT_TIMESTAMP) ELSE NULL END
             WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute(['status' => $status, 'completed_status' => $status, 'id' => $id]);
    }

    public function softDeleteTask(int $id): void
    {
        if ($id < 1) {
            throw new RuntimeException('Task tidak ditemukan.');
        }
        if ($this->getTask($id) === null) {
            throw new RuntimeException('Task tidak ditemukan.');
        }
        $stmt = $this->db->prepare('UPDATE tasks SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Task tidak dapat dihapus.');
        }
    }

    private function buildFilters(array $filters): array
    {
        $conditions = ['t.deleted_at IS NULL'];
        $params = [];
        $status = $filters['status'] ?? '';
        if ($status === 'pending') {
            $conditions[] = "t.status IN ('todo','in_progress')";
        } elseif (is_string($status) && in_array($status, self::STATUSES, true)) {
            $conditions[] = 't.status = :status';
            $params['status'] = $status;
        }
        $priority = $filters['priority'] ?? '';
        if (is_string($priority) && in_array($priority, self::PRIORITIES, true)) {
            $conditions[] = 't.priority = :priority';
            $params['priority'] = $priority;
        }
        $search = $filters['search'] ?? '';
        if (is_string($search) && trim($search) !== '') {
            $conditions[] = '(t.title LIKE :search_title OR t.description LIKE :search_description)';
            $term = '%' . trim($search) . '%';
            $params['search_title'] = $term;
            $params['search_description'] = $term;
        }
        $due = $filters['due'] ?? '';
        if ($due === 'overdue') {
            $conditions[] = "t.due_date < :due_today AND t.status NOT IN ('done','cancelled')";
            $params['due_today'] = date('Y-m-d');
        } elseif ($due === 'upcoming') {
            $conditions[] = 't.due_date >= :due_today';
            $params['due_today'] = date('Y-m-d');
        } elseif ($due === 'no_due') {
            $conditions[] = 't.due_date IS NULL';
        }
        return [implode(' AND ', $conditions), $params];
    }

    private function validateTaskData(array $data): array
    {
        $titleValue = $data['title'] ?? null;
        if (!is_string($titleValue)) {
            throw new InvalidArgumentException('Judul task wajib diisi.');
        }
        $title = trim($titleValue);
        if ($title === '' || mb_strlen($title) > 200) {
            throw new InvalidArgumentException('Judul task wajib diisi (maksimal 200 karakter).');
        }
        $descriptionValue = $data['description'] ?? '';
        if (!is_string($descriptionValue)) {
            throw new InvalidArgumentException('Deskripsi task tidak valid.');
        }
        $description = trim($descriptionValue);
        if (mb_strlen($description) > 10000) {
            throw new InvalidArgumentException('Deskripsi task terlalu panjang.');
        }
        $status = $data['status'] ?? 'todo';
        if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Status task tidak valid.');
        }
        $priority = $data['priority'] ?? 'medium';
        if (!is_string($priority) || !in_array($priority, self::PRIORITIES, true)) {
            throw new InvalidArgumentException('Prioritas task tidak valid.');
        }
        $dueDateValue = $data['due_date'] ?? '';
        $dueDate = null;
        if ($dueDateValue !== '' && $dueDateValue !== null) {
            if (!is_string($dueDateValue) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $dueDateValue)) {
                throw new InvalidArgumentException('Tanggal jatuh tempo tidak valid.');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dueDateValue);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date === false
                || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
                || $date->format('Y-m-d') !== $dueDateValue) {
                throw new InvalidArgumentException('Tanggal jatuh tempo tidak valid.');
            }
            $dueDate = $dueDateValue;
        }
        $categoryValue = $data['category_id'] ?? null;
        $categoryId = null;
        if ($categoryValue !== null && $categoryValue !== '') {
            if ((!is_int($categoryValue) && !is_string($categoryValue)) || !ctype_digit((string) $categoryValue) || (int) $categoryValue < 1) {
                throw new InvalidArgumentException('Kategori task tidak valid.');
            }
            $categoryId = (int) $categoryValue;
            $stmt = $this->db->prepare('SELECT id FROM task_categories WHERE id = :id');
            $stmt->execute(['id' => $categoryId]);
            if (!$stmt->fetchColumn()) {
                throw new InvalidArgumentException('Kategori task tidak ditemukan.');
            }
        }
        return [
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'status' => $status,
            'priority' => $priority,
            'due_date' => $dueDate,
            'category_id' => $categoryId,
        ];
    }
}
