<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';

$repo = new RegistrationRepository();
$total = $repo->countAll();
$today = $repo->countToday();
$latest = $repo->getLatest(8);
$statusMessage = '';
$statusType = 'error';
$registrationSettings = $repo->getRegistrationSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_registration_fee'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Security::validateCsrf(is_string($csrf) ? $csrf : null)) {
        $statusMessage = 'Invalid security token.';
    } else {
        $feeRupees = (float) str_replace(',', '', (string) ($_POST['registration_fee'] ?? '0'));
        $paymentUrlRaw = Security::sanitizeString((string) ($_POST['payment_url'] ?? ''), 500);
        $paymentEnabled = isset($_POST['payment_enabled']);

        if ($feeRupees < 0 || $feeRupees > 999999) {
            $statusMessage = 'Registration fee must be between 0 and 999999.';
        } elseif ($paymentUrlRaw !== '' && sanitize_safe_external_url($paymentUrlRaw) === null) {
            $statusMessage = 'Payment link must be a valid http(s) URL.';
        } elseif ($paymentEnabled && $paymentUrlRaw === '') {
            $statusMessage = 'Payment link is required when payment is enabled.';
        } else {
            $repo->updateRegistrationSettings([
                'amount_paise' => rupees_to_paise($feeRupees),
                'currency' => 'INR',
                'fee_label' => Security::sanitizeString((string) ($_POST['fee_label'] ?? ''), 120),
                'payment_enabled' => $paymentEnabled,
                'payment_url' => $paymentUrlRaw,
                'workshop_url' => Security::sanitizeString((string) ($_POST['workshop_url'] ?? ''), 500),
            ]);
            redirect('dashboard.php?fee_saved=1');
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment_id'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Security::validateCsrf(is_string($csrf) ? $csrf : null)) {
        $statusMessage = 'Invalid security token.';
    } else {
        $updateId = (int) $_POST['update_payment_id'];
        $newStatus = strtolower(Security::sanitizeString((string) ($_POST['payment_status'] ?? ''), 16));
        $newStatus = $newStatus === '' ? null : $newStatus;

        if ($repo->updatePaymentStatus($updateId, $newStatus)) {
            redirect('dashboard.php?payment_updated=1');
        }
        $statusMessage = 'Could not update payment status.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_verification_id'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Security::validateCsrf(is_string($csrf) ? $csrf : null)) {
        $statusMessage = 'Invalid security token.';
    } else {
        $updateId = (int) $_POST['update_verification_id'];
        $verified = ((string) ($_POST['is_verified'] ?? '0')) === '1';

        if ($repo->updateVerificationStatus($updateId, $verified)) {
            redirect('dashboard.php?verification_updated=1');
        }
        $statusMessage = 'Could not update verification status.';
    }
}

if (isset($_GET['payment_updated']) || isset($_GET['verification_updated'])) {
    $latest = $repo->getLatest(8);
}

if (isset($_GET['fee_saved'])) {
    $registrationSettings = $repo->getRegistrationSettings();
}

$feeRupees = number_format(((int) ($registrationSettings['amount_paise'] ?? 0)) / 100, 2, '.', '');
$csrfToken = Security::getCsrfToken();
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

require __DIR__ . '/includes/header.php';
?>

<?php if ($statusMessage !== ''): ?>
  <div class="alert alert-<?= e($statusType) ?>" role="alert"><?= e($statusMessage) ?></div>
<?php endif; ?>

<section class="panel">
  <div class="panel-head">
    <h2>Registration Fee</h2>
    <p class="panel-sub">Fee and payment link shown on the registration form.</p>
  </div>
  <form method="post" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" />
    <input type="hidden" name="save_registration_fee" value="1" />
    <label class="field">
      <span>Fee amount (₹)</span>
      <input
        type="number"
        name="registration_fee"
        min="0"
        max="999999"
        step="0.01"
        value="<?= e($feeRupees) ?>"
        required
      />
    </label>
    <label class="field">
      <span>Fee label</span>
      <input
        type="text"
        name="fee_label"
        maxlength="120"
        value="<?= e((string) ($registrationSettings['fee_label'] ?? 'Registration Fee')) ?>"
        placeholder="Summit Registration Fee"
      />
    </label>
    <label class="field">
      <span>Payment link</span>
      <input
        type="url"
        name="payment_url"
        maxlength="500"
        value="<?= e((string) ($registrationSettings['payment_url'] ?? '')) ?>"
        placeholder="https://rzp.io/rzp/your-link"
      />
    </label>
    <label class="field field-checkbox">
      <input
        type="checkbox"
        name="payment_enabled"
        value="1"
        <?= !empty($registrationSettings['payment_enabled']) ? 'checked' : '' ?>
      />
      <span>Enable payment redirect after registration</span>
    </label>
    <label class="field">
      <span>Workshop link (user dashboard)</span>
      <input
        type="url"
        name="workshop_url"
        maxlength="500"
        value="<?= e((string) ($registrationSettings['workshop_url'] ?? '')) ?>"
        placeholder="https://example.com/workshop"
      />
    </label>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Save fee settings</button>
    </div>
  </form>
</section>

<section class="stats-grid">
  <article class="stat-card">
    <span class="stat-label">Total Submissions</span>
    <strong class="stat-value"><?= number_format($total) ?></strong>
  </article>
  <article class="stat-card">
    <span class="stat-label">Today</span>
    <strong class="stat-value"><?= number_format($today) ?></strong>
  </article>
  <article class="stat-card stat-card-action">
    <a href="submissions.php" class="btn btn-primary">View All Submissions</a>
    <a href="export.php" class="btn btn-secondary">Export CSV</a>
  </article>
</section>

<section class="panel">
  <div class="panel-head">
    <h2>Latest Submissions</h2>
    <a href="submissions.php">See all →</a>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Company</th>
          <th>Category</th>
          <th>Mobile</th>
          <th>State</th>
          <th>Payment</th>
          <th>Verified</th>
          <th>Submitted</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($latest === []): ?>
          <tr><td colspan="10" class="empty-cell">No submissions yet.</td></tr>
        <?php else: ?>
          <?php foreach ($latest as $row): ?>
            <tr>
              <td>#<?= (int) $row['id'] ?></td>
              <td><?= e($row['name']) ?></td>
              <td><?= e((string) ($row['company_name'] ?? '')) ?: '—' ?></td>
              <td><?= e(seat_label((string) $row['seat'])) ?></td>
              <td><?= e($row['mobile']) ?></td>
              <td><?= e($row['state']) ?></td>
              <td><?php require __DIR__ . '/includes/payment_status_field.php'; ?></td>
              <td><?php require __DIR__ . '/includes/verification_status_field.php'; ?></td>
              <td><?= e(format_datetime($row['created_at'])) ?></td>
              <td><a href="view.php?id=<?= (int) $row['id'] ?>">View</a></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
