<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';

$fileId = (int) ($_GET['id'] ?? 0);

if ($fileId <= 0) {
    http_response_code(404);
    exit('File not found.');
}

$db = Database::getConnection();
$stmt = $db->prepare(
    'SELECT rf.*, r.id AS registration_id
     FROM registration_files rf
     INNER JOIN registrations r ON r.id = rf.registration_id
     WHERE rf.id = :id LIMIT 1'
);
$stmt->execute([':id' => $fileId]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    exit('File not found.');
}

$uploadPath = app_config()['upload']['path'];
$fullPath = $uploadPath . DIRECTORY_SEPARATOR . $file['stored_name'];

if (!is_file($fullPath)) {
    http_response_code(404);
    exit('File missing on server.');
}

$mime = (string) $file['mime_type'];
$original = (string) $file['original_name'];

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($original) . '"');
header('Content-Length: ' . (string) filesize($fullPath));
header('Cache-Control: no-store, no-cache, must-revalidate');

readfile($fullPath);
exit;
