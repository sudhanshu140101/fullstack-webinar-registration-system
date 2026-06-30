<?php

declare(strict_types=1);

/** @var array<string, mixed> $row */
/** @var string $csrfToken */

$registrationId = (int) $row['id'];
$currentStatus = (string) ($row['payment_status'] ?? '');
?>
<form method="post" class="inline-form payment-status-form">
  <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" />
  <input type="hidden" name="update_payment_id" value="<?= $registrationId ?>" />
  <select
    name="payment_status"
    class="payment-status-select"
    aria-label="Payment status for submission #<?= $registrationId ?>"
    onchange="this.form.submit()"
  >
    <?php foreach (payment_status_options() as $value => $label): ?>
      <option value="<?= e($value) ?>" <?= $currentStatus === $value ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
</form>
