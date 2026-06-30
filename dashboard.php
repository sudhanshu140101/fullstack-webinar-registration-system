<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/user_auth_check.php';

$repo = new RegistrationRepository();
$registrationId = (new UserAuth())->registrationId();
$registration = $registrationId !== null ? $repo->findById($registrationId) : null;

if ($registration === null) {
    (new UserAuth())->logout();
    redirect('login.php');
}

$settings = $repo->getRegistrationSettings();
$paymentRequired = !empty($settings['payment_enabled']) && (int) ($settings['amount_paise'] ?? 0) > 0;
$paymentStatus = (string) ($registration['payment_status'] ?? '');
$isPaid = $paymentStatus === 'paid';
$workshopUrl = trim((string) ($settings['workshop_url'] ?? ''));
$canAccessWorkshop = $workshopUrl !== '' && (!$paymentRequired || $isPaid);
$appName = app_config()['app_name'];
$userName = (string) ($_SESSION['user_name'] ?? $registration['name']);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <title>My Dashboard — <?= e($appName) ?></title>
    <meta name="robots" content="noindex, nofollow" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="register.css?v=20260622b" />
  </head>
  <body class="registration-page">
    <header class="registration-topbar">
      <a class="registration-back" href="index.html" aria-label="Back to home">
        <svg viewBox="0 0 24 24" aria-hidden="true" width="20" height="20">
          <path d="M14 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span>Home</span>
      </a>
      <a class="user-logout-link" href="logout.php">Logout</a>
    </header>

    <main class="registration-shell user-dashboard">
      <div class="registration-intro" aria-labelledby="dashboard-title">
        <h1 id="dashboard-title">My Dashboard</h1>
        <p>Welcome, <?= e($userName) ?>.</p>
      </div>

      <section class="user-dashboard-card" aria-labelledby="registration-details-title">
        <h2 id="registration-details-title" class="user-dashboard-card-title">Registration Details</h2>
        <dl class="user-dashboard-list">
          <div>
            <dt>Name</dt>
            <dd><?= e((string) $registration['name']) ?></dd>
          </div>
          <div>
            <dt>Mobile</dt>
            <dd><?= e((string) $registration['mobile']) ?></dd>
          </div>
          <div>
            <dt>Email</dt>
            <dd><?= e((string) $registration['email']) ?></dd>
          </div>
          <div>
            <dt>State</dt>
            <dd><?= e((string) $registration['state']) ?></dd>
          </div>
          <div>
            <dt>District</dt>
            <dd><?= e((string) $registration['district']) ?></dd>
          </div>
          <div>
            <dt>Registered on</dt>
            <dd><?= e(format_datetime((string) ($registration['created_at'] ?? ''))) ?></dd>
          </div>
          <?php if ($paymentRequired): ?>
            <div>
              <dt>Payment status</dt>
              <dd>
                <span class="user-status user-status-<?= e($isPaid ? 'paid' : ($paymentStatus === 'failed' ? 'failed' : 'pending')) ?>">
                  <?= e(payment_status_label($paymentStatus !== '' ? $paymentStatus : 'pending')) ?>
                </span>
              </dd>
            </div>
          <?php endif; ?>
        </dl>
      </section>

      <section class="user-dashboard-card" aria-labelledby="workshop-access-title">
        <h2 id="workshop-access-title" class="user-dashboard-card-title">Workshop Access</h2>
        <?php if ($canAccessWorkshop): ?>
          <p class="user-dashboard-copy">Join the live workshop using the link below.</p>
          <a class="registration-submit user-dashboard-link" href="<?= e($workshopUrl) ?>" target="_blank" rel="noopener noreferrer">
            <span>Join Workshop</span>
            <svg class="registration-submit-icon" viewBox="0 0 20 20" aria-hidden="true">
              <path d="M4 10h12M11 5l5 5-5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </a>
        <?php elseif ($paymentRequired && !$isPaid): ?>
          <p class="user-dashboard-copy">Complete your registration payment to unlock the workshop link.</p>
          <a class="user-dashboard-text-link" href="register.html">Complete registration</a>
        <?php else: ?>
          <p class="user-dashboard-copy">The workshop link will appear here once it is published by the organisers.</p>
        <?php endif; ?>
      </section>
    </main>
  </body>
</html>
