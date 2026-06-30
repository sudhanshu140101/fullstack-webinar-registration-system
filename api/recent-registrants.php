<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $repo = new RegistrationRepository();
    $registrants = $repo->getRecentRegistrantsPublic(10);
} catch (Throwable $exception) {
    log_app('Recent registrants fetch failed', ['error' => $exception->getMessage()]);
    json_response(['success' => true, 'data' => ['registrants' => []]]);
}

json_response([
    'success' => true,
    'data' => [
        'registrants' => $registrants,
    ],
]);
