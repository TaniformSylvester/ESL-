<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/request-functions.php';

require_admin();

stream_requests_csv([
    'status'        => trim((string)($_GET['status'] ?? '')),
    'subject_id'    => (int)($_GET['subject_id'] ?? 0),
    'grade'         => trim((string)($_GET['grade'] ?? '')),
    'resource_type' => trim((string)($_GET['resource_type'] ?? '')),
    'difficulty'    => trim((string)($_GET['difficulty'] ?? '')),
    'date_from'     => trim((string)($_GET['date_from'] ?? '')),
    'date_to'       => trim((string)($_GET['date_to'] ?? '')),
    'search'        => trim((string)($_GET['search'] ?? '')),
]);
