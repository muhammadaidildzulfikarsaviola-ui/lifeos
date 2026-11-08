<?php
declare(strict_types=1);

namespace App\Modules\Habits;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

class HabitsService
{
    private PDO $db;

    private const FREQUENCIES = ['daily', 'weekdays', 'weekly_target', 'monthly_target'];
    private const COLORS = ['green', 'blue', 'amber', 'rose', 'violet'];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function getDb(): PDO
    {
        return $this->db;
    }

    public function isSchemaReady(): bool
    {
        try {
            foreach (['habits', 'habit_entries'] as $table) {
                $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND ENGINE=\'InnoDB\'');
                $stmt->execute(['table' => $table]);
                if ((int) $stmt->fetchColumn() !== 1) return false;
            }
            $columns = [
                'habits' => ['id', 'name', 'description', 'frequency', 'weekday_mask', 'period_target', 'daily_target', 'unit', 'start_date', 'end_date', 'color_key', 'archived_at', 'created_at', 'updated_at'],
                'habit_entries' => ['id', 'habit_id', 'entry_date', 'progress', 'completed', 'notes', 'created_at', 'updated_at'],
            ];
            foreach ($columns as $table => $expected) {
                $actual = $this->db->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($expected as $column) if (!in_array($column, $actual, true)) return false;
            }
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function listHabits(bool $archived = false): array
    {
        $sql = $archived
            ? 'SELECT * FROM habits WHERE archived_at IS NOT NULL ORDER BY archived_at DESC, name, id'
            : 'SELECT * FROM habits WHERE archived_at IS NULL ORDER BY name, id';
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getHabit(int $id, bool $includeArchived = false): ?array
    {
        $sql = 'SELECT * FROM habits WHERE id=:id' . ($includeArchived ? '' : ' AND archived_at IS NULL');
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $habit = $stmt->fetch(PDO::FETCH_ASSOC);
        return $habit ?: null;
    }

    public function createHabit(array $data): int
    {
        $habit = $this->validateHabit($data);
        $stmt = $this->db->prepare(
            'INSERT INTO habits (name,description,frequency,weekday_mask,period_target,daily_target,unit,start_date,end_date,color_key)
             VALUES (:name,:description,:frequency,:weekday_mask,:period_target,:daily_target,:unit,:start_date,:end_date,:color_key)'
        );
        $stmt->execute($habit);
        return (int) $this->db->lastInsertId();
    }

    public function updateHabit(int $id, array $data): void
    {
        if ($id < 1 || $this->getHabit($id, true) === null) throw new RuntimeException('Habit tidak ditemukan.');
        $habit = $this->validateHabit($data);
        $stmt = $this->db->prepare(
            'UPDATE habits SET name=:name,description=:description,frequency=:frequency,weekday_mask=:weekday_mask,
             period_target=:period_target,daily_target=:daily_target,unit=:unit,start_date=:start_date,end_date=:end_date,color_key=:color_key
             WHERE id=:id'
        );
        $stmt->execute($habit + ['id' => $id]);
    }

    public function archiveHabit(int $id): void
    {
        $this->changeArchivedState($id, true);
    }

    public function restoreHabit(int $id): void
    {
        $this->changeArchivedState($id, false);
    }

    public function permanentlyDeleteHabit(int $id): void
    {
        if ($id < 1 || $this->getHabit($id, true) === null) throw new RuntimeException('Habit tidak ditemukan.');
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM habit_entries WHERE habit_id=:id');
        $stmt->execute(['id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) throw new RuntimeException('Habit memiliki riwayat dan tidak dapat dihapus permanen.');
        $delete = $this->db->prepare('DELETE FROM habits WHERE id=:id');
        $delete->execute(['id' => $id]);
    }

    public function getEntry(int $habitId, string $date): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM habit_entries WHERE habit_id=:id AND entry_date=:date');
        $stmt->execute(['id' => $habitId, 'date' => $this->validateDate($date)->format('Y-m-d')]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        return $entry ?: null;
    }

    public function saveEntry(int $habitId, array $data): int
    {
        $habit = $this->getHabit($habitId);
        if ($habit === null) throw new RuntimeException('Habit tidak ditemukan atau sudah diarsipkan.');
        $date = $this->validateDate($data['entry_date'] ?? null);
        $today = $this->today();
        if ($date > $today) throw new InvalidArgumentException('Progress untuk tanggal masa depan tidak dapat dicatat.');
        $start = $this->date((string) $habit['start_date']);
        $end = !empty($habit['end_date']) ? $this->date((string) $habit['end_date']) : null;
        if ($date < $start || ($end !== null && $date > $end)) throw new InvalidArgumentException('Tanggal berada di luar masa aktif habit.');
        if (!$this->isTrackingDay($habit, $date)) throw new InvalidArgumentException('Habit ini tidak dijadwalkan pada tanggal tersebut.');

        $existing = $this->getEntry($habitId, $date->format('Y-m-d'));
        $progress = $this->validateProgress($data['progress'] ?? ($existing['progress'] ?? 0), $habit['daily_target'] !== null);
        $notes = $data['notes'] ?? ($existing['notes'] ?? '');
        if (!is_string($notes) || mb_strlen($notes) > 2000) throw new InvalidArgumentException('Catatan terlalu panjang atau tidak valid.');
        if (array_key_exists('completed', $data)) {
            $completed = $this->parseBoolean($data['completed']);
        } elseif ($habit['daily_target'] !== null && (float) $progress >= (float) $habit['daily_target']) {
            $completed = true;
        } elseif ($existing !== null) {
            $completed = (bool) $existing['completed'];
        } else {
            $completed = false;
        }
        if ($habit['daily_target'] === null) $progress = $completed ? '1.00' : '0.00';

        $stmt = $this->db->prepare(
            'INSERT INTO habit_entries (habit_id,entry_date,progress,completed,notes)
             VALUES (:habit_id,:entry_date,:progress,:completed,:notes)
             ON DUPLICATE KEY UPDATE progress=VALUES(progress),completed=VALUES(completed),notes=VALUES(notes),updated_at=CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'habit_id' => $habitId,
            'entry_date' => $date->format('Y-m-d'),
            'progress' => $progress,
            'completed' => $completed ? 1 : 0,
            'notes' => trim($notes) !== '' ? trim($notes) : null,
        ]);
        return $existing ? (int) $existing['id'] : (int) $this->db->lastInsertId();
    }

    public function listEntries(int $habitId, string $from, string $to): array
    {
        $fromDate = $this->validateDate($from)->format('Y-m-d');
        $toDate = $this->validateDate($to)->format('Y-m-d');
        if ($fromDate > $toDate) throw new InvalidArgumentException('Rentang tanggal tidak valid.');
        $stmt = $this->db->prepare('SELECT * FROM habit_entries WHERE habit_id=:id AND entry_date BETWEEN :from_date AND :to_date ORDER BY entry_date');
        $stmt->execute(['id' => $habitId, 'from_date' => $fromDate, 'to_date' => $toDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStats(int $habitId, ?string $asOf = null): array
    {
        $habit = $this->getHabit($habitId, true);
        if ($habit === null) throw new RuntimeException('Habit tidak ditemukan.');
        $date = $asOf === null ? $this->today() : $this->validateDate($asOf);
        if ($date > $this->today()) $date = $this->today();
        $entries = $this->entriesMap($habitId, $date->format('Y-m-d'));
        return $this->calculateStats($habit, $entries, $date);
    }

    public function getOverviewData(?string $asOf = null, bool $archived = false): array
    {
        $date = $asOf === null ? $this->today() : $this->validateDate($asOf);
        if ($date > $this->today()) $date = $this->today();
        $habits = $this->listHabits($archived);
        $entriesByHabit = $this->entriesMapForHabits($habits, $date->format('Y-m-d'));
        $items = [];
        foreach ($habits as $habit) {
            $entries = $entriesByHabit[(int) $habit['id']] ?? [];
            $stats = $this->calculateStats($habit, $entries, $date);
            $entry = $entries[$date->format('Y-m-d')] ?? null;
            $items[] = $habit + [
                'stats' => $stats,
                'today_entry' => $entry,
                'is_scheduled' => $this->isTrackingDay($habit, $date),
                'progress_percent' => $this->progressPercent($habit, $entry),
            ];
        }
        return ['date' => $date->format('Y-m-d'), 'habits' => $items];
    }

    public function getDashboardData(?string $asOf = null): array
    {
        $date = $asOf === null ? $this->today() : $this->validateDate($asOf);
        if ($date > $this->today()) $date = $this->today();
        $habits = $this->listHabits(false);
        $entriesByHabit = $this->entriesMapForHabits($habits, $date->format('Y-m-d'));
        $scheduledToday = [];
        $periodTargets = [];
        $streaks = [];
        $completed = 0;
        foreach ($habits as $habit) {
            $id = (int) $habit['id'];
            $entries = $entriesByHabit[$id] ?? [];
            $stats = $this->calculateStats($habit, $entries, $date);
            $entry = $entries[$date->format('Y-m-d')] ?? null;
            $item = [
                'id' => $id,
                'name' => $habit['name'],
                'color_key' => $habit['color_key'],
                'frequency' => $habit['frequency'],
                'entry' => $entry,
                'completed' => (bool) ($entry['completed'] ?? false),
                'progress_percent' => $this->progressPercent($habit, $entry),
                'current_streak' => $stats['current_streak'],
            ];
            if (in_array($habit['frequency'], ['daily', 'weekdays'], true) && $this->isTrackingDay($habit, $date)) {
                $scheduledToday[] = $item;
                if ($item['completed']) $completed++;
            } elseif (in_array($habit['frequency'], ['weekly_target', 'monthly_target'], true)) {
                $periodTargets[] = $item + [
                    'period_progress' => $habit['frequency'] === 'weekly_target' ? $stats['weekly_progress'] : $stats['monthly_progress'],
                ];
            }
            if ($stats['current_streak'] > 0) $streaks[] = ['id' => $id, 'name' => $habit['name'], 'current_streak' => $stats['current_streak'], 'frequency' => $habit['frequency']];
        }
        usort($streaks, static fn(array $a, array $b): int => $b['current_streak'] <=> $a['current_streak']);
        return [
            'date' => $date->format('Y-m-d'),
            'scheduled_total' => count($scheduledToday),
            'completed_today' => $completed,
            'completion_percent' => count($scheduledToday) > 0 ? round($completed / count($scheduledToday) * 100, 1) : 0.0,
            'scheduled_today' => $scheduledToday,
            'period_targets' => $periodTargets,
            'streak_highlights' => array_slice($streaks, 0, 3),
            'active_total' => count($habits),
        ];
    }

    public function isTrackingDay(array $habit, DateTimeImmutable $date): bool
    {
        $start = $this->date((string) $habit['start_date']);
        $end = !empty($habit['end_date']) ? $this->date((string) $habit['end_date']) : null;
        if ($date < $start || ($end !== null && $date > $end)) return false;
        if ($habit['frequency'] === 'daily') return true;
        if ($habit['frequency'] === 'weekdays') {
            $mask = (int) $habit['weekday_mask'];
            return ($mask & (1 << ((int) $date->format('N') - 1))) !== 0;
        }
        return in_array($habit['frequency'], ['weekly_target', 'monthly_target'], true);
    }

    private function changeArchivedState(int $id, bool $archive): void
    {
        if ($id < 1 || $this->getHabit($id, true) === null) throw new RuntimeException('Habit tidak ditemukan.');
        $stmt = $this->db->prepare($archive
            ? 'UPDATE habits SET archived_at=CURRENT_TIMESTAMP WHERE id=:id AND archived_at IS NULL'
            : 'UPDATE habits SET archived_at=NULL WHERE id=:id AND archived_at IS NOT NULL');
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException($archive ? 'Habit sudah diarsipkan.' : 'Habit tidak sedang diarsipkan.');
    }

    private function validateHabit(array $data): array
    {
        $name = $data['name'] ?? null;
        if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 160) throw new InvalidArgumentException('Nama habit wajib diisi, maksimal 160 karakter.');
        $description = $data['description'] ?? '';
        if (!is_string($description) || mb_strlen(trim($description)) > 10000) throw new InvalidArgumentException('Deskripsi tidak valid atau terlalu panjang.');
        $frequency = $data['frequency'] ?? '';
        if (!is_string($frequency) || !in_array($frequency, self::FREQUENCIES, true)) throw new InvalidArgumentException('Frekuensi habit tidak valid.');

        $weekdayMask = 0;
        $periodTarget = null;
        if ($frequency === 'weekdays') {
            $days = $data['weekdays'] ?? [];
            if (!is_array($days) || !$days) throw new InvalidArgumentException('Pilih setidaknya satu hari untuk frekuensi weekday.');
            foreach ($days as $day) {
                if ((!is_int($day) && !is_string($day)) || !ctype_digit((string) $day) || (int) $day < 1 || (int) $day > 7) throw new InvalidArgumentException('Pilihan hari tidak valid.');
                $weekdayMask |= 1 << ((int) $day - 1);
            }
        } elseif (in_array($frequency, ['weekly_target', 'monthly_target'], true)) {
            $raw = $data['period_target'] ?? null;
            $max = $frequency === 'weekly_target' ? 7 : 31;
            if ((!is_int($raw) && !is_string($raw)) || !ctype_digit((string) $raw) || (int) $raw < 1 || (int) $raw > $max) {
                throw new InvalidArgumentException('Target frekuensi harus berada dalam rentang 1 sampai ' . $max . '.');
            }
            $periodTarget = (int) $raw;
        }
        if ($frequency !== 'weekdays') $weekdayMask = null;

        $dailyTargetRaw = $data['daily_target'] ?? '';
        $dailyTarget = null;
        if ($dailyTargetRaw !== '' && $dailyTargetRaw !== null) {
            $dailyTarget = $this->validateProgress($dailyTargetRaw, true);
            if ((float) $dailyTarget <= 0) throw new InvalidArgumentException('Target harian harus lebih besar dari nol.');
        }
        $unit = $data['unit'] ?? '';
        if (!is_string($unit) || mb_strlen(trim($unit)) > 32) throw new InvalidArgumentException('Satuan maksimal 32 karakter.');
        if ($dailyTarget === null && trim($unit) !== '') throw new InvalidArgumentException('Satuan hanya dapat digunakan bersama target harian.');
        $startDate = $this->validateDate($data['start_date'] ?? null)->format('Y-m-d');
        $endValue = $data['end_date'] ?? '';
        $endDate = $endValue === '' || $endValue === null ? null : $this->validateDate($endValue)->format('Y-m-d');
        if ($endDate !== null && $endDate < $startDate) throw new InvalidArgumentException('Tanggal akhir tidak boleh sebelum tanggal mulai.');
        $color = $data['color_key'] ?? 'green';
        if (!is_string($color) || !in_array($color, self::COLORS, true)) throw new InvalidArgumentException('Warna habit tidak valid.');

        return [
            'name' => trim($name),
            'description' => trim($description) !== '' ? trim($description) : null,
            'frequency' => $frequency,
            'weekday_mask' => $weekdayMask,
            'period_target' => $periodTarget,
            'daily_target' => $dailyTarget,
            'unit' => trim($unit) !== '' ? trim($unit) : null,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'color_key' => $color,
        ];
    }

    private function validateProgress(mixed $value, bool $quantitative): string
    {
        if (is_float($value) || is_int($value)) $value = (string) $value;
        if (!is_string($value) || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', trim($value))) {
            throw new InvalidArgumentException('Progress harus berupa angka non-negatif dengan maksimal dua desimal.');
        }
        $number = (float) $value;
        if (!is_finite($number) || $number > 99999999.99) throw new InvalidArgumentException('Nilai progress berada di luar batas.');
        if (!$quantitative && !in_array($number, [0.0, 1.0], true)) throw new InvalidArgumentException('Habit tanpa target ukur hanya menerima progress 0 atau 1.');
        return number_format($number, 2, '.', '');
    }

    private function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) return $value;
        if ($value === 1 || $value === '1') return true;
        if ($value === 0 || $value === '0') return false;
        throw new InvalidArgumentException('Status completion tidak valid.');
    }

    private function validateDate(mixed $value): DateTimeImmutable
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) throw new InvalidArgumentException('Tanggal tidak valid.');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $this->timezone());
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Tanggal tidak valid.');
        }
        return $date;
    }

    private function date(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' 00:00:00', $this->timezone());
    }

    private function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today', $this->timezone());
    }

    private function timezone(): DateTimeZone
    {
        return new DateTimeZone(date_default_timezone_get());
    }

    private function entriesMap(int $habitId, string $asOf): array
    {
        $stmt = $this->db->prepare('SELECT * FROM habit_entries WHERE habit_id=:id AND entry_date<=:as_of ORDER BY entry_date');
        $stmt->execute(['id' => $habitId, 'as_of' => $asOf]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $entry) $map[$entry['entry_date']] = $entry;
        return $map;
    }

    private function entriesMapForHabits(array $habits, string $asOf): array
    {
        if (!$habits) return [];
        $ids = array_map(static fn(array $habit): int => (int) $habit['id'], $habits);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT * FROM habit_entries WHERE habit_id IN ({$placeholders}) AND entry_date<=? ORDER BY entry_date");
        $stmt->execute([...$ids, $asOf]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $entry) $map[(int) $entry['habit_id']][$entry['entry_date']] = $entry;
        return $map;
    }

    private function calculateStats(array $habit, array $entries, DateTimeImmutable $asOf): array
    {
        if (!empty($habit['end_date'])) $asOf = min($asOf, $this->date((string) $habit['end_date']));
        if (!empty($habit['archived_at'])) $asOf = min($asOf, $this->date(substr((string) $habit['archived_at'], 0, 10)));
        $start = $this->date((string) $habit['start_date']);
        $end = !empty($habit['end_date']) ? min($asOf, $this->date((string) $habit['end_date'])) : $asOf;
        if ($end < $start) $end = $start->modify('-1 day');
        $completedDates = [];
        foreach ($entries as $date => $entry) if ((int) $entry['completed'] === 1) $completedDates[$date] = true;
        $weekly = $this->periodSummary($habit, $entries, $asOf, 'week');
        $monthly = $this->periodSummary($habit, $entries, $asOf, 'month');
        if (in_array($habit['frequency'], ['daily', 'weekdays'], true)) {
            [$current, $longest] = $this->dayStreaks($habit, $completedDates, $start, $end, $asOf);
            $scheduled = $this->scheduledDates($habit, $start, $end);
            $completedCount = 0;
            $eligibleCount = 0;
            foreach ($scheduled as $scheduledDate) {
                $key = $scheduledDate->format('Y-m-d');
                if ($key === $asOf->format('Y-m-d') && !isset($completedDates[$key]) && $asOf == $this->today()) continue;
                $eligibleCount++;
                if (isset($completedDates[$key])) $completedCount++;
            }
            $rate = $eligibleCount > 0 ? round($completedCount / $eligibleCount * 100, 1) : 0.0;
        } else {
            [$current, $longest] = $this->periodStreaks($habit, $completedDates, $start, $asOf);
            [$completedCount, $eligibleCount] = $this->periodCompletionTotals($habit, $completedDates, $start, $asOf);
            $rate = $eligibleCount > 0 ? round($completedCount / $eligibleCount * 100, 1) : 0.0;
        }
        return [
            'current_streak' => $current,
            'longest_streak' => $longest,
            'total_completed_days' => count($completedDates),
            'completion_rate' => $rate,
            'weekly_progress' => $weekly,
            'monthly_progress' => $monthly,
            'week_starts_on' => 'Monday',
            'as_of' => $asOf->format('Y-m-d'),
        ];
    }


    private function dayStreaks(array $habit, array $completed, DateTimeImmutable $start, DateTimeImmutable $end, DateTimeImmutable $asOf): array
    {
        $scheduled = $this->scheduledDates($habit, $start, $end);
        $longest = 0;
        $run = 0;
        foreach ($scheduled as $date) {
            if (isset($completed[$date->format('Y-m-d')])) {
                $run++;
                $longest = max($longest, $run);
            } else {
                $run = 0;
            }
        }
        $current = 0;
        for ($i = count($scheduled) - 1; $i >= 0; $i--) {
            $key = $scheduled[$i]->format('Y-m-d');
            if ($key === $asOf->format('Y-m-d') && $asOf == $this->today() && !isset($completed[$key])) continue;
            if (!isset($completed[$key])) break;
            $current++;
        }
        return [$current, $longest];
    }

    private function scheduledDates(array $habit, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $dates = [];
        if ($end < $start) return $dates;
        for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 day')) {
            if ($this->isTrackingDay($habit, $cursor)) $dates[] = $cursor;
        }
        return $dates;
    }


    private function periodStreaks(array $habit, array $completed, DateTimeImmutable $start, DateTimeImmutable $asOf): array
    {
        $type = $habit['frequency'] === 'weekly_target' ? 'week' : 'month';
        $periods = $this->periodsBetween($start, $asOf, $type);
        $target = (int) $habit['period_target'];
        $successful = [];
        foreach ($periods as $key => [$periodStart, $periodEnd]) {
            $count = $this->completedDaysInPeriod($completed, $periodStart, min($periodEnd, $asOf), $start, !empty($habit['end_date']) ? $this->date((string) $habit['end_date']) : null);
            $successful[$key] = $count >= $target;
        }
        $longest = 0;
        $run = 0;
        foreach ($successful as $ok) {
            if ($ok) { $run++; $longest = max($longest, $run); }
            else $run = 0;
        }
        $keys = array_keys($periods);
        $current = 0;
        for ($i = count($keys) - 1; $i >= 0; $i--) {
            $key = $keys[$i];
            [$periodStart, $periodEnd] = $periods[$key];
            $isCurrentPeriod = $periodStart <= $asOf && $asOf <= $periodEnd;
            $periodStillOpen = $asOf < $periodEnd || $asOf == $this->today();
            if ($isCurrentPeriod && !$successful[$key] && $periodStillOpen) continue;
            if (!$successful[$key]) break;
            $current++;
        }
        return [$current, $longest];
    }

    private function periodCompletionTotals(array $habit, array $completed, DateTimeImmutable $start, DateTimeImmutable $asOf): array
    {
        $type = $habit['frequency'] === 'weekly_target' ? 'week' : 'month';
        $periods = $this->periodsBetween($start, $asOf, $type);
        $target = (int) $habit['period_target'];
        $done = 0;
        $eligible = 0;
        foreach ($periods as [$periodStart, $periodEnd]) {
            $count = $this->completedDaysInPeriod($completed, $periodStart, min($periodEnd, $asOf), $start, !empty($habit['end_date']) ? $this->date((string) $habit['end_date']) : null);
            if ($count >= $target) $done++;
            $periodClosed = $periodEnd < $asOf || ($periodEnd == $asOf && $asOf < $this->today());
            if ($periodClosed || $count >= $target) $eligible++;
        }
        return [$done, $eligible];
    }

    private function completedDaysInPeriod(array $completed, DateTimeImmutable $from, DateTimeImmutable $to, DateTimeImmutable $habitStart, ?DateTimeImmutable $habitEnd): int
    {
        $count = 0;
        foreach ($completed as $date => $_) {
            $day = $this->date($date);
            if ($day >= $from && $day <= $to && $day >= $habitStart && ($habitEnd === null || $day <= $habitEnd)) $count++;
        }
        return $count;
    }

    private function periodsBetween(DateTimeImmutable $start, DateTimeImmutable $asOf, string $type): array
    {
        $cursor = $type === 'week' ? $start->modify('monday this week') : $start->modify('first day of this month');
        $periods = [];
        while ($cursor <= $asOf) {
            $periodStart = $cursor;
            $periodEnd = $type === 'week' ? $cursor->modify('+6 days') : $cursor->modify('last day of this month');
            $key = $type === 'week' ? $periodStart->format('o-\WW') : $periodStart->format('Y-m');
            $periods[$key] = [$periodStart, $periodEnd];
            $cursor = $type === 'week' ? $cursor->modify('+7 days') : $cursor->modify('first day of next month');
        }
        return $periods;
    }

    private function periodSummary(array $habit, array $entries, DateTimeImmutable $asOf, string $type): array
    {
        $start = $type === 'week' ? $asOf->modify('monday this week') : $asOf->modify('first day of this month');
        $end = $type === 'week' ? $start->modify('+6 days') : $start->modify('last day of this month');
        $completed = 0;
        foreach ($entries as $date => $entry) if ($date >= $start->format('Y-m-d') && $date <= $asOf->format('Y-m-d') && (int) $entry['completed'] === 1) $completed++;
        $target = in_array($habit['frequency'], ['weekly_target', 'monthly_target'], true)
            ? (int) $habit['period_target']
            : count($this->scheduledDates($habit, max($start, $this->date((string) $habit['start_date'])), min($end, $asOf)));
        $percent = $target > 0 ? min(100.0, round($completed / $target * 100, 1)) : 0.0;
        return ['completed_days' => $completed, 'target' => $target, 'percent' => $percent, 'period_start' => $start->format('Y-m-d'), 'period_end' => $end->format('Y-m-d')];
    }


    private function progressPercent(array $habit, ?array $entry): float
    {
        if ($entry === null) return 0.0;
        if ($habit['daily_target'] === null) return (int) $entry['completed'] === 1 ? 100.0 : 0.0;
        return min(100.0, round((float) $entry['progress'] / (float) $habit['daily_target'] * 100, 1));
    }
}
