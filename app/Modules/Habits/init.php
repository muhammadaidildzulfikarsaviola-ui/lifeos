<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_once LIFEOS_ROOT . '/app/Modules/Habits/HabitsService.php';

use App\Modules\Habits\HabitsService;

$habitsService = null;
$habitsDbError = null;

try {
    $habitsService = new HabitsService();
    if (!$habitsService->isSchemaReady()) {
        $habitsDbError = 'Schema Habits belum lengkap. Database tidak diubah otomatis.';
    }
} catch (Throwable $e) {
    $habitsDbError = 'Database LIFEOS belum terhubung.';
    error_log('[Habits] ' . $e->getMessage());
}
