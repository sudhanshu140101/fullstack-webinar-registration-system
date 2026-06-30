<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';

$pageTitle = 'SMS Test';
$activeNav = 'sms-test';

$result = null;
$configCheck = (new SmsGateway())->validateConfig(app_config()['sms'] ?? []);
$testMobile = '';
$csrfToken = Security::getCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Security::validateCsrf(is_string($csrf) ? $csrf : null)) {
        $result = ['success' => false, 'message' => 'Invalid security token.'];
    } else {
        $testMobile = Security::sanitizeString((string) ($_POST['mobile'] ?? ''), 16);
        $otp = (string) random_int(100000, 999999);
        $result = (new SmsGateway())->sendOtp($testMobile, $otp);
        $result['test_otp'] = $otp;
    }
}

$logs = [];
try {
    $stmt = Database::getConnection()->query(
        'SELECT * FROM sms_send_logs ORDER BY created_at DESC LIMIT 10'
    );
    $logs = $stmt->fetchAll() ?: [];
} catch (PDOException) {
    $logs = [];
}

require __DIR__ . '/includes/header.php';
?>
<section class="admin-panel">
  <p>Use this page to verify ConnectBind SMS delivery. A test OTP is sent to the mobile number below.</p>

  <?php if (!$configCheck['valid']): ?>
    <div class="admin-alert admin-alert-error">
      <strong>SMS configuration errors:</strong>
      <ul>
        <?php foreach ($configCheck['errors'] as $error): ?>
          <li><?= e($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($result !== null): ?>
    <div class="admin-alert <?= !empty($result['success']) ? 'admin-alert-success' : 'admin-alert-error' ?>">
      <p><?= e((string) ($result['message'] ?? 'Unknown result')) ?></p>
      <?php if (!empty($result['response'])): ?>
        <p><strong>Gateway response:</strong> <code><?= e((string) $result['response']) ?></code></p>
      <?php endif; ?>
      <?php if (!empty($result['message_id'])): ?>
        <p><strong>Message ID:</strong> <code><?= e((string) $result['message_id']) ?></code></p>
      <?php endif; ?>
      <?php if (!empty($result['test_otp'])): ?>
        <p><strong>Test OTP:</strong> <code><?= e((string) $result['test_otp']) ?></code> (admin only)</p>
      <?php endif; ?>
      <?php if (!empty($result['success'])): ?>
        <p>If the phone did not receive SMS but response is <code>1701</code>, contact ConnectBind with the Message ID. This is a DLT/operator delivery issue.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <form method="post" class="admin-form-grid">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" />
    <label class="admin-field">
      <span>Test mobile number</span>
      <input type="tel" name="mobile" value="<?= e($testMobile) ?>" placeholder="10-digit mobile" maxlength="10" required />
    </label>
    <div class="admin-form-actions">
      <button type="submit" class="btn btn-primary">Send test OTP SMS</button>
    </div>
  </form>
</section>

<section class="admin-panel">
  <h2>Recent SMS logs</h2>
  <?php if ($logs === []): ?>
    <p>No SMS attempts logged yet.</p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Time</th>
            <th>Mobile</th>
            <th>Destination</th>
            <th>OK</th>
            <th>Message ID</th>
            <th>Response</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td><?= e(format_datetime((string) ($log['created_at'] ?? ''))) ?></td>
              <td><?= e((string) ($log['mobile'] ?? '')) ?></td>
              <td><?= e((string) ($log['destination'] ?? '')) ?></td>
              <td><?= !empty($log['success']) ? 'Yes' : 'No' ?></td>
              <td><?= e((string) ($log['message_id'] ?? '—')) ?></td>
              <td><code><?= e((string) ($log['gateway_response'] ?? '')) ?></code></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
