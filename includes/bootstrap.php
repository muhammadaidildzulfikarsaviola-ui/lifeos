<?php
declare(strict_types=1);

if (defined('LIFEOS_BOOTSTRAP_LOADED')) {
    return;
}

define('LIFEOS_BOOTSTRAP_LOADED', true);
if (!defined('LIFEOS_ROOT')) {
    define('LIFEOS_ROOT', dirname(__DIR__));
}
date_default_timezone_set('Asia/Jakarta');

require_once LIFEOS_ROOT . '/config/session.php';
require_once LIFEOS_ROOT . '/function.php';
