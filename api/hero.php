<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $repo = new HeroRepository();
    $data = $repo->getPublic();
} catch (Throwable $exception) {
    log_app('Hero fetch failed', ['error' => $exception->getMessage()]);
    json_response(['success' => true, 'data' => null]);
}

json_response([
    'success' => true,
    'data' => $data,
]);
