<?php
/**
 * TeachLuma — Central Configuration
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
define('SITE_NAME', 'TeachLuma');
define('SITE_TAGLINE', 'Save Time. Teach Better.');
define('SITE_DESCRIPTION', 'Ready-to-teach ESL, Math, and Science lesson plans, worksheets, PowerPoints, games and classroom resources for primary and secondary school teachers.');

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
define('MEMBERSHIP_EXPIRY_REMINDER_DAYS', 3); // how many days before expiry_date to send the renewal reminder

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

// docx/pptx/xlsx are ZIP archives internally, and not every server's libmagic
// build has the rules to tell an Office file apart from a generic ZIP — some
// report plain application/zip, and some (confirmed on live FastComet
// hosting) don't recognize the ZIP signature at all and fall back to the
// generic application/octet-stream. Both are harmless here (uploads are
// stored under random filenames, never executed, and always served as
// forced downloads), so both are accepted as fallbacks for the Office
// extensions rather than rejecting valid uploads.
define('ALLOWED_RESOURCE_MIME_TYPES', [
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
    'ppt'  => ['application/vnd.ms-powerpoint'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
    'xls'  => ['application/vnd.ms-excel'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
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
// Full range across all subjects — each subject's actual valid range (e.g.
// Math is Grade 1-6, not K or Grade 7-9) is sliced from this list via
// get_subject_grade_levels() rather than duplicated here.
define('GRADE_LEVELS', [
    'Kindergarten', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9',
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
// CRON
// cron/expire-memberships.php runs fine unauthenticated from the CLI
// (php cron/expire-memberships.php). If your host only offers URL-based
// cron jobs, set a random secret here and call the URL with ?token=...
// so the endpoint isn't publicly triggerable by anyone who finds it.
// -----------------------------------------------------------------------
define('CRON_SECRET', '');

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
