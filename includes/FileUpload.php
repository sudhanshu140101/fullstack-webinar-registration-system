<?php

declare(strict_types=1);

final class FileUpload
{
    /**
     * @return array{ok: bool, stored_name?: string, original_name?: string, mime?: string, size?: int, error?: string}
     */
    public static function handleOptional(array $file, string $uploadDir, bool $publicReadable = false): array
    {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => self::uploadErrorMessage((int) $file['error'])];
        }

        $config = app_config()['upload'];
        $maxSize = (int) $config['max_size'];

        if ((int) $file['size'] > $maxSize) {
            return ['ok' => false, 'error' => 'File is too large. Maximum allowed size is 5 MB.'];
        }

        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return ['ok' => false, 'error' => 'Invalid upload payload. Please try again.'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        $allowedMime = $config['allowed_mime'];

        if (!in_array($mime, $allowedMime, true)) {
            return ['ok' => false, 'error' => 'Invalid file type. Allowed: PDF, JPG, PNG, WEBP.'];
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $allowedExt = $config['allowed_extensions'];

        if (!in_array($extension, $allowedExt, true)) {
            return ['ok' => false, 'error' => 'Invalid file extension.'];
        }

        $dirMode = $publicReadable ? 0755 : 0750;
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, $dirMode, true) && !is_dir($uploadDir)) {
                return [
                    'ok' => false,
                    'error' => 'Could not create upload folder. Ensure uploads/ is writable by the web server.',
                ];
            }
        }

        if (!is_writable($uploadDir)) {
            return [
                'ok' => false,
                'error' => 'Upload folder is not writable. Check permissions on uploads/ on the server.',
            ];
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $uploadDir . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            log_app('move_uploaded_file failed', [
                'destination' => $destination,
                'writable' => is_writable($uploadDir),
            ]);

            return ['ok' => false, 'error' => 'Could not save uploaded file. Check uploads/ folder permissions on the server.'];
        }

        @chmod($destination, $publicReadable ? 0644 : 0640);

        return [
            'ok' => true,
            'stored_name' => $storedName,
            'original_name' => Security::sanitizeString((string) $file['name'], 255),
            'mime' => $mime,
            'size' => (int) $file['size'],
        ];
    }

    private static function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large. Maximum allowed size is 5 MB.',
            UPLOAD_ERR_PARTIAL => 'Upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server cannot store uploads (missing temp folder). Contact your hosting provider.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write the file. Check uploads/ folder permissions.',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by server configuration.',
            default => 'File upload failed. Please try again.',
        };
    }
}
