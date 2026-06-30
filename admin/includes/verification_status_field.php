<?php

declare(strict_types=1);

/** @var array<string, mixed> $row */
/** @var string $csrfToken */

$registrationId = (int) $row['id'];
$isVerified = is_registration_verified($row);
$currentValue = $isVerified ? '1' : '0';
?>
<form method="post" class="inline-form payment-status-form">
  <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" />
  <input type="hidden" name="update_verification_id" value="<?= $registrationId ?>" />
  <select
    name="is_verified"
    class="payment-status-select"
    aria-label="Verification status for submission #<?= $registrationId ?>"
    onchange="this.form.submit()"
  >
    <?php foreach (verification_status_options() as $optionValue => $label): ?>
      <?php $optionValue = (string) $optionValue; ?>
      <option value="<?= e($optionValue) ?>" <?= $currentValue === $optionValue ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
</form>
