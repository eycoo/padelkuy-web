<?php
// Storing admin-uploaded venue images (#51). Validation is factored out so it
// is unit-testable without a real HTTP upload; the move is injectable so tests
// can copy a fixture instead of using move_uploaded_file.

class UploadException extends RuntimeException {}

const MAX_UPLOAD_BYTES = 5 * 1024 * 1024; // 5 MB

// Accepted image mime types mapped to the extension we store them under.
const ALLOWED_IMAGE_MIME = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

// Validate one entry from $_FILES. Returns the stored extension, or throws
// UploadException for a failed/oversize/non-image upload.
function validateUploadedImage(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new UploadException('No file was uploaded or the upload failed');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > MAX_UPLOAD_BYTES) {
        throw new UploadException('Image must be between 1 byte and 5 MB');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    $mime = is_file($tmp) ? (finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmp) ?: '') : '';
    if (!isset(ALLOWED_IMAGE_MIME[$mime])) {
        throw new UploadException('Only JPEG, PNG, or WebP images are allowed');
    }

    return ALLOWED_IMAGE_MIME[$mime];
}

// Validate, then move the upload into $destDir under a random name. Returns the
// web-relative path ("$webPrefix/<hash>.<ext>"). $mover defaults to
// move_uploaded_file; tests pass 'copy' for a fixture file.
function storeUploadedImage(array $file, string $destDir, string $webPrefix, ?callable $mover = null): string
{
    $ext = validateUploadedImage($file);
    $mover ??= 'move_uploaded_file';

    if (!is_dir($destDir) && !mkdir($destDir, 0775, true) && !is_dir($destDir)) {
        throw new UploadException('Could not create the upload directory');
    }

    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = rtrim($destDir, '/\\') . '/' . $name;

    if (!$mover($file['tmp_name'], $dest)) {
        throw new UploadException('Could not store the uploaded file');
    }

    return rtrim($webPrefix, '/') . '/' . $name;
}
