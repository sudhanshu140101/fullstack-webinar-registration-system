<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$auth = new Auth();

if ($auth->isLoggedIn()) {
    redirect('dashboard.php');
}

$setupRequired = !$auth->adminAccountExists();
$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$setupRequired) {
    $username = Security::sanitizeString((string) ($_POST['username'] ?? ''), 64);
    $password = (string) ($_POST['password'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';

    if (!Security::validateCsrf(is_string($csrf) ? $csrf : null)) {
        $error = 'Invalid security token. Please try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $result = $auth->attemptLogin($username, $password);
        if ($result['success']) {
            redirect('dashboard.php');
        }
        $error = $result['message'];
    }
}

$csrfToken = Security::getCsrfToken();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Login — <?= e(app_config()['app_name']) ?></title>
  <link rel="stylesheet" href="../assets/admin/admin.css" />
</head>
<body class="login-page">
  <div class="login-card">
    <div class="login-brand">
        <img src="../images/logo.png" alt="CIMSME" class="admin-brand-mark" width="42" height="42" />
      <div>
        <h1>Admin Login</h1>
        <p><?= e(app_config()['app_name']) ?></p>
      </div>
    </div>

    <?php if ($setupRequired): ?>
      <div class="alert alert-error" role="alert">
        No admin account exists yet. Create one from the server terminal before signing in.
      </div>
      <div class="login-form">
        <p><strong>From the project root, run:</strong></p>
        <pre class="setup-command">php database/seed_admin.php admin admin@yourdomain.com "YourStrongPassword123!" "Admin Name"</pre>
        <p class="setup-hint">On GoDaddy: cPanel → Terminal, then <code>cd public_html</code> and run the command above with your details.</p>
      </div>
    <?php else: ?>
      <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" class="login-form" autocomplete="off" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" />
        <label>
          <span>Username</span>
          <input type="text" name="username" value="<?= e($username) ?>" required autofocus />
        </label>
        <label>
          <span>Password</span>
          <input type="password" name="password" required />
        </label>
        <button type="submit" class="btn btn-primary btn-block">Sign In</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
