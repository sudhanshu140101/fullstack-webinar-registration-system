<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $repo = new RegistrationRepository();
    $settings = $repo->getRegistrationSettings();
} catch (Throwable $exception) {
    log_app('Registration settings fetch failed', ['error' => $exception->getMessage()]);
    json_response(['success' => false, 'message' => 'Service temporarily unavailable.'], 503);
}

$amountPaise = (int) ($settings['amount_paise'] ?? 0);
$paymentEnabled = (bool) ($settings['payment_enabled'] ?? false);
$paymentUrl = registration_payment_url($settings);

json_response([
    'success' => true,
    'data' => [
        'payment_enabled' => $paymentEnabled,
        'amount_paise' => $amountPaise,
        'amount_display' => format_inr_paise($amountPaise),
        'currency' => (string) ($settings['currency'] ?? 'INR'),
        'fee_label' => (string) ($settings['fee_label'] ?? 'Registration Fee'),
        'payment_configured' => $paymentUrl !== '',
    ],
]);
