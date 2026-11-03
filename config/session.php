<?php
declare(strict_types=1);

/*
 * LIFEOS tidak memakai login. Session hanya untuk CSRF dan state sementara.
 * Session cookie tetap diperketat untuk mencegah session fixation dan pencurian cookie.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', '1800');

    session_name('LIFEOSSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();

    if (!isset($_SESSION['_lifeos_started'])) {
        session_regenerate_id(true);
        $_SESSION['_lifeos_started'] = time();
    }

    $idleLimit = 1800;
    $lastActivity = (int) ($_SESSION['_lifeos_last_activity'] ?? time());
    if (time() - $lastActivity > $idleLimit) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['_lifeos_started'] = time();
    }
    $_SESSION['_lifeos_last_activity'] = time();
}
