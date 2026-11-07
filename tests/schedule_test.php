<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Modules/Schedule/ScheduleService.php';

use App\Modules\Schedule\ScheduleService;

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE schedule_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, color TEXT);");
$db->exec("CREATE TABLE tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, deleted_at TEXT NULL);");
$db->exec("INSERT INTO tasks (title) VALUES ('Review roadmap');");
$db->exec("CREATE TABLE schedule_events (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, description TEXT, start_date TEXT NOT NULL, start_time TEXT, end_date TEXT NOT NULL, end_time TEXT, all_day INTEGER NOT NULL DEFAULT 0, category_id INTEGER, status TEXT NOT NULL DEFAULT 'upcoming', recurrence TEXT NOT NULL DEFAULT 'none', recurrence_until TEXT, reminder_minutes INTEGER, task_id INTEGER, shift_template_id INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, deleted_at TEXT);");
$db->exec("CREATE TABLE schedule_shift_templates (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE, start_time TEXT NOT NULL, end_time TEXT NOT NULL, weekdays TEXT NOT NULL, repeat_until TEXT);");
$db->exec("CREATE TABLE schedule_shift_occurrences (template_id INTEGER NOT NULL, shift_date TEXT NOT NULL, event_id INTEGER NOT NULL, PRIMARY KEY(template_id, shift_date), UNIQUE(event_id));");
$service = new ScheduleService($db);
$checks = 0;
$assert = static function(bool $condition, string $message) use (&$checks): void { if (!$condition) throw new RuntimeException('FAIL: ' . $message); $checks++; echo "PASS: {$message}\n"; };
$eventId = $service->createEvent(['title'=>'Daily standup','start_date'=>'2026-11-02','start_time'=>'09:00','end_date'=>'2026-11-02','end_time'=>'09:30','recurrence'=>'daily','recurrence_until'=>'2026-11-05','reminder_minutes'=>15,'task_id'=>1]);
$assert($eventId > 0, 'event create persists an id');
$assert($service->getEvent($eventId)['task_title'] === 'Review roadmap', 'event links to an existing task without copying task data');
$assert(count($service->listEvents(['start'=>'2026-11-02','end'=>'2026-11-06'])) === 4, 'daily recurrence expands in range without inserting duplicate event rows');
$service->updateEvent($eventId, ['title'=>'Daily standup updated','start_date'=>'2026-11-02','start_time'=>'09:30','end_date'=>'2026-11-02','end_time'=>'10:00','recurrence'=>'daily','recurrence_until'=>'2026-11-05','reminder_minutes'=>30,'task_id'=>1,'status'=>'upcoming']);
$assert((int)$service->getEvent($eventId)['reminder_minutes'] === 30, 'event update retains configurable reminder');
$service->rescheduleEvent($eventId, '2026-11-03', null, '2026-11-02');
$assert($service->getEvent($eventId)['start_date'] === '2026-11-03', 'rescheduling persists to storage');
$service->rescheduleEvent($eventId, '2026-11-03', '11:00', '2026-11-03');
$assert(substr($service->getEvent($eventId)['start_time'],0,5) === '11:00' && substr($service->getEvent($eventId)['end_time'],0,5) === '11:30', 'time-slot rescheduling preserves event duration');
$service->softDeleteEvent($eventId);
$assert($service->getEvent($eventId) === null, 'delete removes event from active reads');
$templateId = $service->saveShiftTemplate(['name'=>'Shift pagi','start_time'=>'08:00','end_time'=>'16:00','weekdays'=>[1,2,3,4,5]]);
$first = $service->generateShiftEvents($templateId,'2026-11-02','2026-11-06');
$second = $service->generateShiftEvents($templateId,'2026-11-02','2026-11-06');
$assert($first === 5 && $second === 0, 'weekday shift templates generate events idempotently');
$assert(count($service->listEvents(['start'=>'2026-11-02','end'=>'2026-11-06'])) === 5, 'generated shift events appear in calendar range');
$service->deleteShiftTemplate($templateId);
$assert(count($service->listEvents(['start'=>'2026-11-02','end'=>'2026-11-06'])) === 5, 'deleting a template retains its generated event history');
try { $service->createEvent(['title'=>'','start_date'=>'2026-11-01','end_date'=>'2026-11-01','all_day'=>1]); $assert(false,'invalid event data is rejected'); }
catch (InvalidArgumentException) { $assert(true,'invalid event data is rejected'); }
echo "Schedule checks: {$checks} passed.\n";
