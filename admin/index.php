<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$auth = new Auth();
redirect($auth->isLoggedIn() ? 'dashboard.php' : 'login.php');
