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
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/security.php';
require_once ROOT_PATH . '/includes/functions.php';

secure_session_start();
