<?php
declare(strict_types=1);

namespace App\Modules\Schedule;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;

class ScheduleService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function getDb(): PDO { return $this->db; }

    public function isInstalled(): bool
    {
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'schedule_events'");
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            try { $this->db->query('SELECT 1 FROM schedule_events LIMIT 1'); return true; }
            catch (\Throwable) { return false; }
        }
    }

    /** Read-only check so a page visit never mutates the user's schema. */
    public function isSchemaReady(): bool
    {
        try {
            $columns = $this->db->query('SHOW COLUMNS FROM schedule_events')->fetchAll(PDO::FETCH_COLUMN);
            foreach (['status','recurrence','recurrence_until','reminder_minutes','task_id','shift_template_id'] as $column) {
                if (!in_array($column, $columns, true)) return false;
            }
            foreach (['schedule_holidays','schedule_holiday_sync','schedule_shift_templates','schedule_shift_occurrences'] as $table) {
                $this->db->query('SELECT 1 FROM ' . $table . ' LIMIT 0');
            }
            return true;
        } catch (\Throwable) { return false; }
    }

    public function listCategories(): array
    {
        return $this->db->query('SELECT id, name, color FROM schedule_categories ORDER BY name')->fetchAll();
    }

    public function listEvents(array $filters = []): array
    {
        $rangeStart = $filters['start'] ?? $filters['date'] ?? date('Y-m-d');
        $rangeEnd = $filters['end'] ?? $rangeStart;
        $stmt = $this->db->prepare(
            'SELECT e.*, c.name AS category_name, c.color AS category_color, t.title AS task_title
             FROM schedule_events e
             LEFT JOIN schedule_categories c ON c.id = e.category_id
             LEFT JOIN tasks t ON t.id = e.task_id AND t.deleted_at IS NULL
             WHERE e.deleted_at IS NULL AND e.start_date <= :range_end
             AND (e.end_date >= :range_start OR (e.recurrence <> \'none\' AND (e.recurrence_until IS NULL OR e.recurrence_until >= :recurrence_start)))
             ORDER BY e.all_day DESC, e.start_time, e.title'
        );
        try { $stmt->execute(['range_start' => $rangeStart, 'range_end' => $rangeEnd, 'recurrence_start' => $rangeStart]); }
        catch (\PDOException $e) {
            $stmt = $this->db->prepare(
                'SELECT e.*, c.name AS category_name, c.color AS category_color, NULL AS task_title
                 FROM schedule_events e LEFT JOIN schedule_categories c ON c.id = e.category_id
                 WHERE e.deleted_at IS NULL AND e.start_date <= :range_end AND (e.end_date >= :range_start OR (e.recurrence <> \'none\' AND (e.recurrence_until IS NULL OR e.recurrence_until >= :recurrence_start)))
                 ORDER BY e.all_day DESC, e.start_time, e.title'
            );
            $stmt->execute(['range_start' => $rangeStart, 'range_end' => $rangeEnd, 'recurrence_start' => $rangeStart]);
        }
        $events = [];
        $from = new DateTimeImmutable($rangeStart);
        $until = new DateTimeImmutable($rangeEnd);
        foreach ($stmt->fetchAll() as $event) {
            $baseStart = new DateTimeImmutable($event['start_date']);
            $baseEnd = new DateTimeImmutable($event['end_date']);
            $duration = $baseStart->diff($baseEnd)->days;
            $frequency = $event['recurrence'] ?? 'none';
            if ($frequency === 'none') {
                $events[] = $event + ['occurrence_date' => $event['start_date'], 'occurrence_end' => $event['end_date']];
                continue;
            }
            $ruleEnd = !empty($event['recurrence_until']) ? min($until->format('Y-m-d'), $event['recurrence_until']) : $until->format('Y-m-d');
            $cursor = max($from->modify('-' . $duration . ' days'), $baseStart);
            $limit = new DateTimeImmutable($ruleEnd);
            while ($cursor <= $limit) {
                $diffDays = (int) $baseStart->diff($cursor)->format('%r%a');
                $matches = match ($frequency) {
                    'daily' => $diffDays >= 0,
                    'weekly' => $diffDays >= 0 && intdiv($diffDays, 7) >= 0 && $cursor->format('N') === $baseStart->format('N'),
                    'monthly' => $diffDays >= 0 && $cursor->format('j') === $baseStart->format('j'),
                    'yearly' => $diffDays >= 0 && $cursor->format('m-d') === $baseStart->format('m-d'),
                    default => false,
                };
                if ($matches) {
                    $occurrenceEnd = $cursor->add(new DateInterval('P' . $duration . 'D'));
                    if ($occurrenceEnd >= $from) {
                        $copy = $event;
                        $copy['occurrence_date'] = $cursor->format('Y-m-d');
                        $copy['occurrence_end'] = $occurrenceEnd->format('Y-m-d');
                        $events[] = $copy;
                    }
                }
                $cursor = $cursor->add(new DateInterval('P1D'));
            }
        }
        return $events;
    }

    public function getEvent(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT e.*, c.name AS category_name, t.title AS task_title FROM schedule_events e LEFT JOIN schedule_categories c ON c.id=e.category_id LEFT JOIN tasks t ON t.id=e.task_id AND t.deleted_at IS NULL WHERE e.id=:id AND e.deleted_at IS NULL');
            $stmt->execute(['id'=>$id]);
        } catch (\PDOException) {
            $stmt = $this->db->prepare('SELECT e.*, c.name AS category_name, NULL AS task_title FROM schedule_events e LEFT JOIN schedule_categories c ON c.id=e.category_id WHERE e.id=:id AND e.deleted_at IS NULL');
            $stmt->execute(['id'=>$id]);
        }
        return $stmt->fetch() ?: null;
    }

    public function createEvent(array $data): int
    {
        $data = $this->validateEventData($data);
        $stmt = $this->db->prepare(
            'INSERT INTO schedule_events
            (title, description, start_date, start_time, end_date, end_time, all_day, category_id,
             status, recurrence, recurrence_until, reminder_minutes, task_id)
            VALUES (:title, :description, :start_date, :start_time, :end_date, :end_time, :all_day, :category_id,
             :status, :recurrence, :recurrence_until, :reminder_minutes, :task_id)'
        );
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function updateEvent(int $id, array $data): void
    {
        $data = $this->validateEventData($data);
        $data['id'] = $id;
        $stmt = $this->db->prepare(
            'UPDATE schedule_events SET title=:title, description=:description, start_date=:start_date,
             start_time=:start_time, end_date=:end_date, end_time=:end_time, all_day=:all_day,
             category_id=:category_id, status=:status, recurrence=:recurrence, recurrence_until=:recurrence_until,
             reminder_minutes=:reminder_minutes, task_id=:task_id WHERE id=:id AND deleted_at IS NULL'
        );
        $stmt->execute($data);
        if ($stmt->rowCount() === 0 && $this->getEvent($id) === null) throw new RuntimeException('Event tidak ditemukan.');
    }

    public function rescheduleEvent(int $id, string $startDate, ?string $startTime = null, ?string $occurrenceDate = null): void
    {
        $event = $this->getEvent($id);
        if (!$event) throw new RuntimeException('Event tidak ditemukan.');
        $oldStart = new DateTimeImmutable($this->validDate($occurrenceDate ?? $event['start_date'], 'Tanggal event'));
        $newStart = new DateTimeImmutable($this->validDate($startDate, 'Tanggal mulai'));
        $days = (int) $oldStart->diff($newStart)->format('%r%a');
        $newDateEnd = (new DateTimeImmutable($event['end_date']))->modify(($days < 0 ? '' : '+') . $days . ' days');
        $time = $event['all_day'] ? null : ($startTime !== null ? $this->validTime($startTime, 'Waktu mulai') : substr((string)$event['start_time'], 0, 5) . ':00');
        $endTime = $event['all_day'] ? null : $event['end_time'];
        if ($startTime !== null && !$event['all_day']) {
            $oldStartAt = new DateTimeImmutable($event['start_date'] . ' ' . $event['start_time']);
            $oldEndAt = new DateTimeImmutable($event['end_date'] . ' ' . $event['end_time']);
            $durationMinutes = (int)floor(($oldEndAt->getTimestamp() - $oldStartAt->getTimestamp()) / 60);
            $newStartAt = new DateTimeImmutable($newStart->format('Y-m-d') . ' ' . $startTime);
            $newEndAt = $newStartAt->modify('+' . max(1, $durationMinutes) . ' minutes');
            $newDateEnd = $newEndAt;
            $time = $newStartAt->format('H:i:s');
            $endTime = $newEndAt->format('H:i:s');
        }
        $stmt = $this->db->prepare('UPDATE schedule_events SET start_date=:start, end_date=:end, start_time=:time, end_time=:end_time WHERE id=:id');
        $stmt->execute(['start'=>$newStart->format('Y-m-d'), 'end'=>$newDateEnd->format('Y-m-d'), 'time'=>$time, 'end_time'=>$endTime, 'id'=>$id]);
        if (!empty($event['shift_template_id'])) {
            $occurrence = $this->db->prepare('UPDATE schedule_shift_occurrences SET shift_date=:new_date WHERE template_id=:template AND shift_date=:old_date');
            $occurrence->execute(['new_date'=>$newStart->format('Y-m-d'), 'template'=>$event['shift_template_id'], 'old_date'=>$event['start_date']]);
        }
    }

    public function softDeleteEvent(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE schedule_events SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
    }

    public function listTasks(): array
    {
        try { return $this->db->query("SELECT id, title FROM tasks WHERE deleted_at IS NULL ORDER BY title")->fetchAll(); }
        catch (\Throwable) { return []; }
    }

    public function listHolidays(string $start, string $end): array
    {
        $stmt = $this->db->prepare('SELECT holiday_date AS start_date, holiday_date AS end_date, name AS title, source_name FROM schedule_holidays WHERE holiday_date BETWEEN :start AND :end ORDER BY holiday_date, name');
        $stmt->execute(['start' => $start, 'end' => $end]);
        return $stmt->fetchAll();
    }

    /** Refresh a year's national holiday list from Nager.Date, a third-party data source. */
    public function syncHolidays(int $year, bool $force = false): int
    {
        if ($year < 2020 || $year > 2100) throw new InvalidArgumentException('Tahun hari libur di luar rentang yang didukung.');
        $check = $this->db->prepare('SELECT synced_at FROM schedule_holiday_sync WHERE year = :year');
        $check->execute(['year' => $year]);
        $lastSync = $check->fetchColumn();
        if (!$force && $lastSync && strtotime((string)$lastSync) > strtotime('-7 days')) return 0;
        $url = 'https://date.nager.at/api/v3/PublicHolidays/' . $year . '/ID';
        $context = stream_context_create(['http' => ['timeout' => 4, 'header' => "User-Agent: LIFEOS-local-calendar\r\n"]]);
        set_error_handler(static function (int $severity, string $message): never {
            throw new RuntimeException($message);
        });
        try {
            $json = file_get_contents($url, false, $context);
        } catch (\Throwable $e) {
            throw new RuntimeException('Sumber hari libur Nager.Date tidak dapat dihubungi. Hari libur tersimpan tetap ditampilkan.', 0, $e);
        } finally {
            restore_error_handler();
        }
        if ($json === false) throw new RuntimeException('Sumber hari libur Nager.Date tidak dapat dihubungi. Hari libur tersimpan tetap ditampilkan.');
        $holidays = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($holidays)) throw new RuntimeException('Format data hari libur tidak valid.');
        $this->db->beginTransaction();
        try {
            $delete = $this->db->prepare("DELETE FROM schedule_holidays WHERE year = :year AND source_code = 'nager-date'");
            $delete->execute(['year' => $year]);
            $insert = $this->db->prepare("INSERT INTO schedule_holidays (holiday_date, name, source_code, source_name, year) VALUES (:date, :name, 'nager-date', 'Nager.Date', :year)");
            foreach ($holidays as $holiday) {
                if (empty($holiday['global'])) continue;
                $name = trim((string)($holiday['localName'] ?? $holiday['name'] ?? ''));
                if ($name !== '') $insert->execute(['date' => $holiday['date'], 'name' => $name, 'year' => $year]);
            }
            $sync = $this->db->prepare('INSERT INTO schedule_holiday_sync (year, synced_at) VALUES (:year, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE synced_at = CURRENT_TIMESTAMP');
            $sync->execute(['year' => $year]);
            $count = $this->db->query("SELECT COUNT(*) FROM schedule_holidays WHERE year = {$year} AND source_code = 'nager-date'")->fetchColumn();
            $this->db->commit();
            return (int)$count;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function listShiftTemplates(): array
    {
        return $this->db->query('SELECT * FROM schedule_shift_templates ORDER BY name')->fetchAll();
    }

    public function saveShiftTemplate(array $data, ?int $id = null): int
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) throw new InvalidArgumentException('Nama template wajib diisi (maksimal 120 karakter).');
        $start = $this->validTime((string)($data['start_time'] ?? ''), 'Jam mulai');
        $end = $this->validTime((string)($data['end_time'] ?? ''), 'Jam selesai');
        if ($end <= $start) throw new InvalidArgumentException('Jam selesai harus setelah jam mulai. Shift lintas tengah malam belum didukung.');
        $days = array_values(array_unique(array_filter(array_map('intval', (array)($data['weekdays'] ?? [])), static fn($d) => $d >= 1 && $d <= 7)));
        sort($days);
        if (!$days) throw new InvalidArgumentException('Pilih minimal satu hari kerja.');
        $until = !empty($data['repeat_until']) ? $this->validDate((string)$data['repeat_until'], 'Tanggal akhir') : null;
        $params = ['name'=>$name, 'start'=>$start, 'end'=>$end, 'days'=>implode(',', $days), 'until'=>$until];
        if ($id) {
            $params['id'] = $id;
            $stmt = $this->db->prepare('UPDATE schedule_shift_templates SET name=:name, start_time=:start, end_time=:end, weekdays=:days, repeat_until=:until WHERE id=:id');
            $stmt->execute($params);
            return $id;
        }
        $stmt = $this->db->prepare('INSERT INTO schedule_shift_templates (name, start_time, end_time, weekdays, repeat_until) VALUES (:name,:start,:end,:days,:until)');
        $stmt->execute($params);
        return (int)$this->db->lastInsertId();
    }

    public function deleteShiftTemplate(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM schedule_shift_templates WHERE id=:id');
        $stmt->execute(['id'=>$id]);
    }

    public function generateShiftEvents(int $templateId, string $from, string $to): int
    {
        $from = $this->validDate($from, 'Tanggal mulai');
        $to = $this->validDate($to, 'Tanggal selesai');
        if ($to < $from || (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days > 366) throw new InvalidArgumentException('Rentang pembuatan shift maksimal satu tahun.');
        $stmt = $this->db->prepare('SELECT * FROM schedule_shift_templates WHERE id=:id');
        $stmt->execute(['id'=>$templateId]);
        $template = $stmt->fetch();
        if (!$template) throw new RuntimeException('Template shift tidak ditemukan.');
        $days = array_map('intval', explode(',', $template['weekdays']));
        $cursor = new DateTimeImmutable($from);
        $limit = new DateTimeImmutable($to);
        $created = 0;
        $exists = $this->db->prepare('SELECT event_id FROM schedule_shift_occurrences WHERE template_id=:template AND shift_date=:date');
        $insert = $this->db->prepare('INSERT INTO schedule_events (title, description, start_date, start_time, end_date, end_time, all_day, status, recurrence, shift_template_id) VALUES (:title, NULL, :date, :start, :date, :end, 0, \'upcoming\', \'none\', :template)');
        $link = $this->db->prepare('INSERT INTO schedule_shift_occurrences (template_id, shift_date, event_id) VALUES (:template, :date, :event)');
        for (; $cursor <= $limit; $cursor = $cursor->add(new DateInterval('P1D'))) {
            $date = $cursor->format('Y-m-d');
            if (!in_array((int)$cursor->format('N'), $days, true)) continue;
            if ($template['repeat_until'] && $date > $template['repeat_until']) continue;
            $exists->execute(['template'=>$templateId, 'date'=>$date]);
            if ($exists->fetchColumn()) continue;
            $this->db->beginTransaction();
            try {
                $insert->execute(['title'=>$template['name'], 'date'=>$date, 'start'=>substr($template['start_time'],0,5), 'end'=>substr($template['end_time'],0,5), 'template'=>$templateId]);
                $link->execute(['template'=>$templateId, 'date'=>$date, 'event'=>(int)$this->db->lastInsertId()]);
                $this->db->commit();
                $created++;
            } catch (\Throwable $e) {
                if ($this->db->inTransaction()) $this->db->rollBack();
                throw $e;
            }
        }
        return $created;
    }

    private function validateEventData(array $data): array
    {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 200) throw new InvalidArgumentException('Judul wajib diisi (maksimal 200 karakter).');
        $start = $this->validDate((string)($data['start_date'] ?? ''), 'Tanggal mulai');
        $end = $this->validDate((string)($data['end_date'] ?? $start), 'Tanggal selesai');
        if ($end < $start) throw new InvalidArgumentException('Tanggal selesai tidak boleh sebelum tanggal mulai.');
        $allDay = !empty($data['all_day']) ? 1 : 0;
        $startTime = $allDay ? null : $this->validTime((string)($data['start_time'] ?? ''), 'Waktu mulai');
        $endTime = $allDay ? null : $this->validTime((string)($data['end_time'] ?? ''), 'Waktu selesai');
        if (!$allDay && $start === $end && $endTime <= $startTime) throw new InvalidArgumentException('Waktu selesai harus setelah waktu mulai.');
        $recurrence = $data['recurrence'] ?? 'none';
        if (!in_array($recurrence, ['none','daily','weekly','monthly','yearly'], true)) throw new InvalidArgumentException('Pola pengulangan tidak valid.');
        $until = !empty($data['recurrence_until']) ? $this->validDate((string)$data['recurrence_until'], 'Tanggal akhir pengulangan') : null;
        if ($recurrence !== 'none' && $until !== null && $until < $start) throw new InvalidArgumentException('Pengulangan berakhir sebelum tanggal mulai.');
        $status = $data['status'] ?? 'upcoming';
        if (!in_array($status, ['upcoming','completed','cancelled'], true)) throw new InvalidArgumentException('Status event tidak valid.');
        $reminder = ($data['reminder_minutes'] ?? '') === '' ? null : (int)$data['reminder_minutes'];
        if ($reminder !== null && ($reminder < 0 || $reminder > 525600)) throw new InvalidArgumentException('Pengingat harus antara 0 menit dan 365 hari.');
        $categoryId = !empty($data['category_id']) ? (int)$data['category_id'] : null;
        if ($categoryId !== null) {
            $categoryCheck = $this->db->prepare('SELECT id FROM schedule_categories WHERE id=:id');
            $categoryCheck->execute(['id'=>$categoryId]);
            if (!$categoryCheck->fetchColumn()) throw new InvalidArgumentException('Kategori tidak ditemukan.');
        }
        $taskId = !empty($data['task_id']) ? (int)$data['task_id'] : null;
        if ($taskId !== null) {
            $taskCheck = $this->db->prepare('SELECT id FROM tasks WHERE id=:id AND deleted_at IS NULL');
            $taskCheck->execute(['id'=>$taskId]);
            if (!$taskCheck->fetchColumn()) throw new InvalidArgumentException('Task yang dipilih tidak ditemukan atau telah dihapus.');
        }
        return [
            'title'=>$title, 'description'=>trim((string)($data['description'] ?? '')) ?: null,
            'start_date'=>$start, 'start_time'=>$startTime, 'end_date'=>$end, 'end_time'=>$endTime,
            'all_day'=>$allDay, 'category_id'=>$categoryId,
            'status'=>$status, 'recurrence'=>$recurrence, 'recurrence_until'=>$until,
            'reminder_minutes'=>$reminder, 'task_id'=>$taskId,
        ];
    }

    private function validDate(string $value, string $label): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException($label . ' tidak valid.');
        return $value;
    }

    private function validTime(string $value, string $label): string
    {
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) throw new InvalidArgumentException($label . ' tidak valid.');
        return $value . ':00';
    }
}
