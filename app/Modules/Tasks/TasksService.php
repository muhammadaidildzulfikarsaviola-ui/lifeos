<?php
declare(strict_types=1);

namespace App\Modules\Tasks;

use PDO;
use Throwable;
use RuntimeException;
use InvalidArgumentException;

class TasksService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function getDb(): PDO
    {
        return $this->db;
    }

    /** Check if tasks tables exist */
    public function isInstalled(): bool
    {
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'tasks'");
            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    /** CRUD */
    public function listTasks(array $filters = []): array
    {
        $sql = "SELECT t.*, c.name AS category_name, c.color AS category_color
                FROM tasks t
                LEFT JOIN task_categories c ON c.id = t.category_id
                WHERE t.deleted_at IS NULL";
        $params = [];
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = :priority";
            $params['priority'] = $filters['priority'];
        }
        if (!empty($filters['due_date'])) {
            $sql .= " AND t.due_date = :due_date";
            $params['due_date'] = $filters['due_date'];
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getTask(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT t.*, c.name AS category_name, c.color AS category_color
             FROM tasks t
             LEFT JOIN task_categories c ON c.id = t.category_id
             WHERE t.id = :id AND t.deleted_at IS NULL"
        );
        $stmt->execute(['id' => $id]);
        $task = $stmt->fetch();
        return $task ?: null;
    }

    public function createTask(array $data): int
    {
        $this->validateTaskData($data);
        $stmt = $this->db->prepare(
            "INSERT INTO tasks (title, description, status, priority, due_date, category_id)
             VALUES (:title, :description, :status, :priority, :due_date, :category_id)"
        );
        $stmt->execute([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'todo',
            'priority' => $data['priority'] ?? 'medium',
            'due_date' => $data['due_date'] ?? null,
            'category_id' => $data['category_id'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateTask(int $id, array $data): void
    {
        $this->validateTaskData($data, $id);
        $stmt = $this->db->prepare(
            "UPDATE tasks SET title = :title, description = :description, status = :status,
                priority = :priority, due_date = :due_date, category_id = :category_id WHERE id = :id"
        );
        $stmt->execute([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'todo',
            'priority' => $data['priority'] ?? 'medium',
            'due_date' => $data['due_date'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'id' => $id,
        ]);
    }

    public function softDeleteTask(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE tasks SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    private function validateTaskData(array $data, ?int $id = null): void
    {
        if (trim($data['title'] ?? '') === '') {
            throw new InvalidArgumentException('Judul task wajib diisi');
        }
        // status validation
        $validStatus = ['todo', 'in_progress', 'done', 'cancelled'];
        if (!empty($data['status']) && !in_array($data['status'], $validStatus, true)) {
            throw new InvalidArgumentException('Status task tidak valid');
        }
        $validPriority = ['low', 'medium', 'high', 'critical'];
        if (!empty($data['priority']) && !in_array($data['priority'], $validPriority, true)) {
            throw new InvalidArgumentException('Prioritas task tidak valid');
        }
        // due_date optional but if present must be valid date
        if (!empty($data['due_date']) && strtotime($data['due_date']) === false) {
            throw new InvalidArgumentException('Tanggal jatuh tempo tidak valid');
        }
    }
}
?>
