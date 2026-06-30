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

$ip = client_ip();

try {
    $repo = new RegistrationRepository();
} catch (Throwable $exception) {
    log_app('Database unavailable', ['error' => $exception->getMessage(), 'ip' => $ip]);
    json_response([
        'success' => false,
        'message' => 'Service temporarily unavailable. Please try again shortly.',
    ], 503);
}

$rateLimit = (int) $config['security']['rate_limit_submissions_per_hour'];

if ($repo->countSubmissionsSince($ip, 1) >= $rateLimit) {
    json_response([
        'success' => false,
        'message' => 'Too many submissions from your network. Please try again later.',
    ], 429);
}

$validator = new Validator();
$result = $validator->validateRegistration($_POST);

if (!$result['valid']) {
    json_response([
        'success' => false,
        'message' => 'Please correct the highlighted fields.',
        'errors' => $result['errors'],
    ], 422);
}

$otpService = new OtpService();
if (!$otpService->isRegistrationMobileVerified($result['data']['mobile'])) {
    json_response([
        'success' => false,
        'message' => 'Please verify your mobile number with OTP before submitting.',
    ], 422);
}

$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null;

try {
    $registrationId = $repo->create($result['data'], $ip, $userAgent);
    $otpService->clearRegistrationMobileVerification($result['data']['mobile']);
    log_app('Registration created', ['id' => $registrationId, 'ip' => $ip]);

    $settings = $repo->getRegistrationSettings();
    $paymentUrl = registration_payment_url($settings);
    $paymentEnabled = (bool) ($settings['payment_enabled'] ?? false);
    $paymentRequired = $paymentEnabled && $paymentUrl !== '';

    if (!$paymentRequired) {
        json_response([
            'success' => true,
            'message' => 'Thank you. Your registration is complete.',
            'registration_id' => $registrationId,
            'payment_required' => false,
        ]);
    }

    $repo->updatePaymentStatus($registrationId, 'pending');

    json_response([
        'success' => true,
        'message' => 'Proceed For The Payment',
        'registration_id' => $registrationId,
        'payment_required' => true,
        'payment_url' => $paymentUrl,
    ]);
} catch (Throwable $exception) {
    log_app('Registration failed', ['error' => $exception->getMessage(), 'ip' => $ip]);
    json_response([
        'success' => false,
        'message' => 'Unable to save registration. Please try again shortly.',
    ], 500);
}
