<?php
/**
 * Bootstrap file. Every page includes this once, near the top, before
 * any output or logic:
 *
 *   require_once __DIR__ . '/includes/init.php';          // root-level pages
 *   require_once __DIR__ . '/../includes/init.php';       // member/ and admin/ pages
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/stripe.php';
require_once ROOT_PATH . '/config/google.php';
require_once ROOT_PATH . '/config/adsense.php';
require_once ROOT_PATH . '/config/analytics.php';
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/security.php';
require_once ROOT_PATH . '/includes/functions.php';

secure_session_start();

/**
 * In production, an uncaught exception or fatal error would otherwise
 * leave the visitor looking at a blank page (display_errors is off).
 * Catch both and render the friendly 500 page instead. Deliberately
 * skipped in development so real error output stays visible for
 * debugging. This does not affect our own intentional die()/exit calls
 * (e.g. db.php's connection-failure message, require_csrf()) — those
 * aren't PHP errors and never reach these handlers.
 */
if (ENVIRONMENT !== 'development') {
    $renderFatalErrorPage = function (): void {
        if (!headers_sent()) {
            http_response_code(500);
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        require ROOT_PATH . '/500.php';
        exit;
    };

    set_exception_handler(function (Throwable $e) use ($renderFatalErrorPage) {
        error_log('Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $renderFatalErrorPage();
    });

    register_shutdown_function(function () use ($renderFatalErrorPage) {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            error_log('Fatal error: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
            $renderFatalErrorPage();
        }
    });
}
