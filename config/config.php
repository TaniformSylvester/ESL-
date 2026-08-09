<?php
/**
 * ESL Teacher Hub — Central Configuration
 *
 * Every brand, pricing, and environment value the app uses lives here.
 * Nothing else in the codebase should hardcode the site name, price,
 * contact email, or file paths — always reference these constants.
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// -----------------------------------------------------------------------
// ENVIRONMENT
// Set to 'production' before going live. In production, PHP errors are
// hidden from visitors and logged instead of displayed.
// -----------------------------------------------------------------------
define('ENVIRONMENT', 'development'); // 'development' | 'production'

if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', ROOT_PATH . '/logs/php-error.log');
}

// -----------------------------------------------------------------------
// SITE / BRANDING
// Change these to rebrand the site without touching any other file.
// -----------------------------------------------------------------------
define('SITE_NAME', 'ESL Teacher Hub');
define('SITE_TAGLINE', 'Save Time. Teach Better.');
define('SITE_DESCRIPTION', 'Ready-to-teach ESL lesson plans, worksheets, PowerPoints, games and classroom resources for primary school teachers.');

// IMPORTANT: change this to your real domain before going live, no trailing slash.
define('SITE_URL', 'http://localhost/esl-teacher-hub');

define('CONTACT_EMAIL', 'contact@example.com');
define('ADMIN_EMAIL', 'admin@example.com');

// -----------------------------------------------------------------------
// LOCALE / CURRENCY / SUBSCRIPTION
// -----------------------------------------------------------------------
define('TIMEZONE', 'Asia/Bangkok');
date_default_timezone_set(TIMEZONE);

define('CURRENCY', 'THB');
define('SITE_CURRENCY_SYMBOL', '฿');
define('SUBSCRIPTION_PRICE', 200);       // amount per billing period
define('SUBSCRIPTION_PERIOD_LABEL', 'month');

// -----------------------------------------------------------------------
// SESSION
// -----------------------------------------------------------------------
define('SESSION_NAME', 'esl_teacher_hub_session');

// -----------------------------------------------------------------------
// UPLOADS / FILE STORAGE
// Resource files are stored under uploads/protected and are NEVER served
// directly — always through member/download.php. See uploads/protected/.htaccess.
// -----------------------------------------------------------------------
define('UPLOAD_BASE_PATH', ROOT_PATH . '/uploads');
define('UPLOAD_PROTECTED_PATH', UPLOAD_BASE_PATH . '/protected');
define('UPLOAD_THUMBNAIL_PATH', UPLOAD_BASE_PATH . '/thumbnails');
define('UPLOAD_PREVIEW_PATH', UPLOAD_BASE_PATH . '/previews');

define('UPLOAD_BASE_URL', rtrim(SITE_URL, '/') . '/uploads');
define('UPLOAD_THUMBNAIL_URL', UPLOAD_BASE_URL . '/thumbnails');
define('UPLOAD_PREVIEW_URL', UPLOAD_BASE_URL . '/previews');

define('MAX_UPLOAD_SIZE_MB', 25);
define('MAX_UPLOAD_SIZE_BYTES', MAX_UPLOAD_SIZE_MB * 1024 * 1024);
define('MAX_IMAGE_SIZE_MB', 3);
define('MAX_IMAGE_SIZE_BYTES', MAX_IMAGE_SIZE_MB * 1024 * 1024);

define('ALLOWED_RESOURCE_MIME_TYPES', [
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'ppt'  => ['application/vnd.ms-powerpoint'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
    'xls'  => ['application/vnd.ms-excel'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'zip'  => ['application/zip', 'application/x-zip-compressed'],
]);

define('ALLOWED_IMAGE_MIME_TYPES', [
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'webp' => ['image/webp'],
]);

// -----------------------------------------------------------------------
// TEACHING DATA (configurable lists used across forms and filters)
// -----------------------------------------------------------------------
define('GRADE_LEVELS', [
    'Kindergarten', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6',
]);

define('RESOURCE_TYPES', [
    'Lesson Plan', 'Worksheet', 'PowerPoint', 'Flashcards',
    'Classroom Activity', 'Game', 'Test', 'Assessment', 'Poster', 'Teacher Resource',
]);

// -----------------------------------------------------------------------
// PAGINATION
// -----------------------------------------------------------------------
define('RESOURCES_PER_PAGE', 12);
define('ADMIN_ROWS_PER_PAGE', 20);

// -----------------------------------------------------------------------
// AUTHENTICATION / ACCOUNT SECURITY
// -----------------------------------------------------------------------
define('PASSWORD_MIN_LENGTH', 8);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);
define('PASSWORD_RESET_TTL_MINUTES', 60);

// -----------------------------------------------------------------------
// EMAIL / SMTP
// Leave SMTP_ENABLED false to use PHP's built-in mail() function.
// Fill these in later if your host requires SMTP for reliable delivery.
// -----------------------------------------------------------------------
define('SMTP_ENABLED', false);
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_ENCRYPTION', 'tls'); // 'tls' | 'ssl'
define('SMTP_FROM_EMAIL', ADMIN_EMAIL);
define('SMTP_FROM_NAME', SITE_NAME);
