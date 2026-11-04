<?php
declare(strict_types=1);

namespace App\Modules\Schedule;

use PDO;
use Throwable;
use RuntimeException;
use InvalidArgumentException;

class ScheduleService
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

    /** Check if schedule tables exist */
    public function isInstalled(): bool
    {
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'schedule_events'");
            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    /** CRUD Operations */
    public function listEvents(array $filters = []): array
    {
        $sql = "SELECT e.*, c.name AS category_name, c.color AS category_color
                FROM schedule_events e
                LEFT JOIN schedule_categories c ON c.id = e.category_id
                WHERE e.deleted_at IS NULL";
        $params = [];
        // Apply optional filters
        if (!empty($filters['category_id'])) {
            $sql .= " AND e.category_id = :cat_id";
            $params['cat_id'] = $filters['category_id'];
        }
        if (!empty($filters['date'])) {
            $sql .= " AND :date BETWEEN e.start_date AND e.end_date";
            $params['date'] = $filters['date'];
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getEvent(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT e.*, c.name AS category_name, c.color AS category_color
             FROM schedule_events e
             LEFT JOIN schedule_categories c ON c.id = e.category_id
             WHERE e.id = :id AND e.deleted_at IS NULL"
        );
        $stmt->execute(['id' => $id]);
        $event = $stmt->fetch();
        return $event ?: null;
    }

    public function createEvent(array $data): int
    {
        $this->validateEventData($data);
        $stmt = $this->db->prepare(
            "INSERT INTO schedule_events (title, description, start_date, start_time, end_date, end_time, all_day, category_id)
             VALUES (:title, :description, :start_date, :start_time, :end_date, :end_time, :all_day, :category_id)"
        );
        $stmt->execute([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'],
            'start_time' => $data['start_time'] ?? null,
            'end_date' => $data['end_date'],
            'end_time' => $data['end_time'] ?? null,
            'all_day' => $data['all_day'] ?? 0,
            'category_id' => $data['category_id'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateEvent(int $id, array $data): void
    {
        $this->validateEventData($data, $id);
        $stmt = $this->db->prepare(
            "UPDATE schedule_events SET title = :title, description = :description, start_date = :start_date,
                start_time = :start_time, end_date = :end_date, end_time = :end_time,
                all_day = :all_day, category_id = :category_id WHERE id = :id"
        );
        $stmt->execute([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'],
            'start_time' => $data['start_time'] ?? null,
            'end_date' => $data['end_date'],
            'end_time' => $data['end_time'] ?? null,
            'all_day' => $data['all_day'] ?? 0,
            'category_id' => $data['category_id'] ?? null,
            'id' => $id,
        ]);
    }

    public function softDeleteEvent(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE schedule_events SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    private function validateEventData(array $data, ?int $id = null): void
    {
        if (trim($data['title'] ?? '') === '') {
            throw new InvalidArgumentException('Judul event wajib diisi');
        }
        // Dates must be Y-m-d
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;
        if (!$startDate || !$endDate) {
            throw new InvalidArgumentException('Tanggal mulai dan selesai wajib diisi');
        }
        if (strtotime($endDate) < strtotime($startDate)) {
            throw new InvalidArgumentException('Tanggal akhir tidak boleh sebelum tanggal mulai');
        }
        // If not all_day, times must be provided and start <= end
        $allDay = $data['all_day'] ?? 0;
        if (!$allDay) {
            $startTime = $data['start_time'] ?? null;
            $endTime = $data['end_time'] ?? null;
            if (!$startTime || !$endTime) {
                throw new InvalidArgumentException('Waktu mulai dan selesai wajib diisi untuk event non‑all‑day');
            }
            if (strtotime($endTime) < strtotime($startTime) && $startDate === $endDate) {
                throw new InvalidArgumentException('Waktu selesai tidak boleh sebelum waktu mulai pada hari yang sama');
            }
        }
    }
}
?>
