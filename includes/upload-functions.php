<?php
/**
 * Secure file upload handling, shared by every upload form in the app
 * (payment screenshots here; resource files/thumbnails in a later stage).
 *
 * Never trusts the client-supplied filename or MIME type: the extension
 * must be in the allow-list AND the actual file content (sniffed via
 * fileinfo) must match one of the allowed MIME types for that extension.
 */

/**
 * @param array $file One entry from $_FILES, e.g. $_FILES['screenshot']
 * @param string $destDir Absolute filesystem directory to save into
 * @param array $allowedMimeMap ['ext' => ['allowed/mime', ...], ...]
 * @param int $maxBytes Maximum allowed file size
 * @return array ['success' => bool, 'filename' => ?string, 'error' => ?string]
 *               A missing/optional file (no file chosen) is treated as a
 *               non-error with filename === null; callers enforce "required".
 */
function handle_upload(array $file, string $destDir, array $allowedMimeMap, int $maxBytes): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'filename' => null, 'error' => 'Invalid upload.'];
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'filename' => null, 'error' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'filename' => null, 'error' => 'The file failed to upload. Please try again.'];
    }

    if ((int)$file['size'] > $maxBytes) {
        return ['success' => false, 'filename' => null, 'error' => 'That file is too large.'];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'filename' => null, 'error' => 'Invalid upload.'];
    }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));

    if ($ext === '' || !isset($allowedMimeMap[$ext])) {
        return ['success' => false, 'filename' => null, 'error' => 'That file type is not allowed.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $actualMime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
    if ($finfo) {
        finfo_close($finfo);
    }

    if (!$actualMime || !in_array($actualMime, $allowedMimeMap[$ext], true)) {
        error_log("Upload rejected: extension '.{$ext}' but finfo detected MIME '" . ($actualMime ?: '(none)') . "' for original filename '{$file['name']}'");
        return ['success' => false, 'filename' => null, 'error' => 'The file content does not match its extension.'];
    }

    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        return ['success' => false, 'filename' => null, 'error' => 'Server storage error. Please try again later.'];
    }

    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destPath = rtrim($destDir, '/') . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['success' => false, 'filename' => null, 'error' => 'Could not save the uploaded file.'];
    }

    chmod($destPath, 0644);

    return ['success' => true, 'filename' => $safeName, 'error' => null];
}
