<?php
declare(strict_types=1);

const EVENT_POSTER_MAX_BYTES = 5 * 1024 * 1024;

function saveEventPoster(array $file, string $uploadDirectory): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return ['filename' => null, 'error' => null];
    }
    if ($error !== UPLOAD_ERR_OK) {
        return ['filename' => null, 'error' => 'The poster upload did not complete.'];
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > EVENT_POSTER_MAX_BYTES) {
        return ['filename' => null, 'error' => 'Poster images must be no larger than 5 MB.'];
    }
    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    if ($temporaryPath === '' || !is_file($temporaryPath)) {
        return ['filename' => null, 'error' => 'The uploaded poster could not be read.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        return ['filename' => null, 'error' => 'Use a JPEG, PNG, or WebP poster image.'];
    }
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
        return ['filename' => null, 'error' => 'The poster storage directory is unavailable.'];
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $target = rtrim($uploadDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
    $stored = is_uploaded_file($temporaryPath)
        ? move_uploaded_file($temporaryPath, $target)
        : (PHP_SAPI === 'cli' && rename($temporaryPath, $target));
    if (!$stored) {
        return ['filename' => null, 'error' => 'The poster image could not be stored.'];
    }
    return ['filename' => $filename, 'error' => null];
}
