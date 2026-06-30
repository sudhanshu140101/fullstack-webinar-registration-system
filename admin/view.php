<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';

$id = (int) ($_GET['id'] ?? 0);
$repo = new RegistrationRepository();
$row = $id > 0 ? $repo->findById($id) : null;

if ($row === null) {
    $pageTitle = 'Not Found';
    $activeNav = 'submissions';
    require __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-error">Submission not found.</div>';
    echo '<a href="submissions.php" class="btn btn-secondary">Back to list</a>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$files = $repo->getFilesForRegistration($id);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Security::validateCsrf(is_string($csrf) ? $csrf : null)) {
        $message = 'Invalid security token.';
    } elseif (isset($_POST['update_payment_id'])) {
        $newStatus = strtolower(Security::sanitizeString((string) ($_POST['payment_status'] ?? ''), 16));
        $newStatus = $newStatus === '' ? null : $newStatus;

        if ($repo->updatePaymentStatus($id, $newStatus)) {
            redirect('view.php?id=' . $id . '&payment_updated=1');
        }
        $message = 'Could not update payment status.';
    } elseif (isset($_POST['update_verification_id'])) {
        $verified = ((string) ($_POST['is_verified'] ?? '0')) === '1';

        if ($repo->updateVerificationStatus($id, $verified)) {
            redirect('view.php?id=' . $id . '&verification_updated=1');
        }
        $message = 'Could not update verification status.';
    } elseif (isset($_POST['delete_id'])) {
        if ($repo->delete($id)) {
            redirect('submissions.php?deleted=1');
        }
        $message = 'Could not delete record.';
    }
}

if (isset($_GET['payment_updated']) || isset($_GET['verification_updated'])) {
    $row = $repo->findById($id) ?? $row;
}

$csrfToken = Security::getCsrfToken();
$pageTitle = 'Submission #' . $id;
$activeNav = 'submissions';

require __DIR__ . '/includes/header.php';
?>

<?php if ($message !== ''): ?>
  <div class="alert alert-error" role="alert"><?= e($message) ?></div>
<?php endif; ?>

<div class="detail-actions">
  <a href="submissions.php" class="btn btn-ghost">← Back</a>
  <form method="post" onsubmit="return confirm('Delete this submission permanently?');">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" />
    <input type="hidden" name="delete_id" value="<?= $id ?>" />
    <button type="submit" class="btn btn-danger">Delete</button>
  </form>
</div>

<section class="detail-grid">
  <article class="detail-card">
    <h2>Personal Details</h2>
    <dl>
      <dt>Full Name</dt><dd><?= e($row['name']) ?></dd>
      <dt>Company Name</dt><dd><?= e((string) ($row['company_name'] ?? '')) ?: '—' ?></dd>
      <dt>Category</dt><dd><?= e(seat_label((string) $row['seat'])) ?></dd>
      <dt>Mobile</dt><dd><a href="tel:+91<?= e($row['mobile']) ?>"><?= e($row['mobile']) ?></a></dd>
      <dt>Email</dt><dd><a href="mailto:<?= e($row['email']) ?>"><?= e($row['email']) ?></a></dd>
    </dl>
  </article>

  <article class="detail-card">
    <h2>Location</h2>
    <dl>
      <dt>Pincode</dt><dd><?= e($row['pincode']) ?></dd>
      <dt>State</dt><dd><?= e($row['state']) ?></dd>
      <dt>District</dt><dd><?= e($row['district']) ?></dd>
    </dl>
  </article>

  <article class="detail-card">
    <h2>Meta</h2>
    <dl>
      <dt>Payment Status</dt>
      <dd><?php require __DIR__ . '/includes/payment_status_field.php'; ?></dd>
      <dt>Dashboard Access</dt>
      <dd><?php require __DIR__ . '/includes/verification_status_field.php'; ?></dd>
      <dt>Submitted</dt><dd><?= e(format_datetime($row['created_at'])) ?></dd>
      <dt>Updated</dt><dd><?= e(format_datetime($row['updated_at'])) ?></dd>
      <dt>IP Address</dt><dd><?= e((string) ($row['ip_address'] ?? '—')) ?></dd>
    </dl>
  </article>
</section>

<?php if ($files !== []): ?>
  <section class="panel">
    <h2>Uploaded Files</h2>
    <ul class="file-list">
      <?php foreach ($files as $file): ?>
        <li>
          <span><?= e($file['original_name']) ?> (<?= number_format((int) $file['file_size'] / 1024, 1) ?> KB)</span>
          <a href="download.php?id=<?= (int) $file['id'] ?>" class="btn btn-secondary btn-small">Download</a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
