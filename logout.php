<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$userAuth = new UserAuth();
$userAuth->logout();

redirect('login.php');
