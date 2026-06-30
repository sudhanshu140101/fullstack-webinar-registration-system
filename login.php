<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$userAuth = new UserAuth();

if ($userAuth->isLoggedIn()) {
    $registrationId = $userAuth->registrationId();
    $registration = $registrationId !== null
        ? (new RegistrationRepository())->findById($registrationId)
        : null;

    if ($registration !== null && is_registration_verified($registration)) {
        redirect('dashboard.php');
    }

    $userAuth->logout();
}

$notice = '';

if (!empty($_SESSION['login_notice'])) {
    $notice = (string) $_SESSION['login_notice'];
    unset($_SESSION['login_notice']);
}

$csrfToken = Security::getCsrfToken();
$appName = app_config()['app_name'];
$otpValidityMinutes = (int) (app_config()['otp']['validity_minutes'] ?? 5);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <title>Login — <?= e($appName) ?></title>
    <meta name="description" content="Login to access your MSME CONNECT Summit registration dashboard and workshop link." />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="register.css?v=20260623c" />
  </head>
  <body class="registration-page login-page">
    <header class="registration-topbar">
      <a class="registration-back" href="index.html" aria-label="Back to home">
        <svg viewBox="0 0 24 24" aria-hidden="true" width="20" height="20">
          <path d="M14 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span>Back</span>
      </a>
    </header>

    <main class="registration-shell">
      <div class="registration-intro" aria-labelledby="login-title">
        <h1 id="login-title">Login</h1>
        <p>Sign in with your verified mobile number. We will send a one-time password (OTP) valid for <?= e((string) $otpValidityMinutes) ?> minutes.</p>
        <p class="login-verification-note">Only registrations approved by the admin can receive a login OTP.</p>
      </div>

      <?php if ($notice !== ''): ?>
        <div class="user-alert user-alert-error" role="alert"><?= e($notice) ?></div>
      <?php endif; ?>

      <div id="login-alert" class="user-alert user-alert-error" role="alert" hidden></div>

      <form id="login-form" class="registration-form" data-otp-validity-minutes="<?= e((string) $otpValidityMinutes) ?>" autocomplete="on" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" />

        <div id="login-step-mobile" class="login-step">
          <div class="registration-fields">
            <label class="form-field form-field--full" for="login-mobile">
              <span class="form-field-label">Mobile number</span>
              <input
                id="login-mobile"
                type="tel"
                name="mobile"
                placeholder="10-digit mobile number"
                autocomplete="tel"
                inputmode="numeric"
                pattern="[6-9][0-9]{9}"
                maxlength="10"
                required
                autofocus
              />
            </label>
          </div>
          <div class="registration-action">
            <button id="login-send-otp" class="registration-submit" type="button">
              <span>Send OTP</span>
              <svg class="registration-submit-icon" viewBox="0 0 20 20" aria-hidden="true">
                <path d="M4 10h12M11 5l5 5-5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
          </div>
        </div>

        <div id="login-step-otp" class="login-step" hidden>
          <p class="login-otp-hint">
            An OTP has been sent to your registered mobile number. Please check your SMS inbox. The OTP is valid for <?= e((string) $otpValidityMinutes) ?> minutes.
            <button type="button" class="login-change-mobile" id="login-change-mobile">Change</button>
          </p>
          <div class="registration-fields">
            <label class="form-field form-field--full" for="login-otp">
              <span class="form-field-label">Enter OTP</span>
              <input
                id="login-otp"
                type="text"
                name="otp"
                placeholder="6-digit OTP"
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                autocomplete="one-time-code"
                required
              />
            </label>
          </div>
          <div class="registration-action">
            <button id="login-verify-otp" class="registration-submit" type="button">
              <span>Verify &amp; Login</span>
              <svg class="registration-submit-icon" viewBox="0 0 20 20" aria-hidden="true">
                <path d="M4 10h12M11 5l5 5-5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
          </div>
          <p class="login-resend-row">
            <button type="button" class="login-resend-btn" id="login-resend-otp" disabled>Resend OTP</button>
            <span id="login-resend-timer" class="login-resend-timer" hidden></span>
          </p>
        </div>
      </form>

      <p class="user-login-note">Not registered yet? <a href="register.html">Register now</a></p>
    </main>

    <script src="assets/login.js?v=20260623f" defer></script>
  </body>
</html>
