<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$config = app_config();
$tokenName = $config['csrf']['token_name'];
$csrfToken = $_POST[$tokenName] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

if (!Security::validateCsrf(is_string($csrfToken) ? $csrfToken : null)) {
    json_response(['success' => false, 'message' => 'Invalid or expired security token. Please refresh and try again.'], 403);
}

$mobile = Security::sanitizeString((string) ($_POST['mobile'] ?? ''), 16);
$otp = Security::sanitizeString((string) ($_POST['otp'] ?? ''), 8);
$result = (new UserAuth())->verifyLoginOtp($mobile, $otp);

$status = $result['success'] ? 200 : 422;
if (!$result['success'] && str_contains($result['message'], 'Too many failed attempts')) {
    $status = 429;
}

json_response($result, $status);
