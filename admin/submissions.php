<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';

$repo = new RegistrationRepository();

$query = Security::sanitizeString((string) ($_GET['q'] ?? ''), 120);
$seat = strtolower(Security::sanitizeString((string) ($_GET['seat'] ?? ''), 32));
$payment = strtolower(Security::sanitizeString((string) ($_GET['payment'] ?? ''), 16));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$allowedSeats = ['', 'micro', 'small', 'medium', 'startup', 'professionals', 'other'];
$allowedPayment = ['', 'pending', 'paid', 'failed'];

if (!in_array($seat, $allowedSeats, true)) {
    $seat = '';
}
if (!in_array($payment, $allowedPayment, true)) {
    $payment = '';
}

$result = $repo->search(
    $query,
    $seat !== '' ? $seat : null,
    $payment !== '' ? $payment : null,
    $page,
    $perPage
);

$total = $result['total'];
$totalPages = max(1, (int) ceil($total / $perPage));
$rows = $result['rows'];

$statusMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Security::validateCsrf(is_string($csrf) ? $csrf : null)) {
        $statusMessage = 'Invalid security token.';
    } elseif (isset($_POST['update_payment_id'])) {
        $updateId = (int) $_POST['update_payment_id'];
        $newStatus = strtolower(Security::sanitizeString((string) ($_POST['payment_status'] ?? ''), 16));
        $newStatus = $newStatus === '' ? null : $newStatus;

        if ($repo->updatePaymentStatus($updateId, $newStatus)) {
            redirect('submissions.php?' . http_build_query(array_filter([
                'q' => $query,
                'seat' => $seat,
                'payment' => $payment,
                'page' => $page,
                'payment_updated' => '1',
            ])));
        }
        $statusMessage = 'Could not update payment status.';
    } elseif (isset($_POST['update_verification_id'])) {
        $updateId = (int) $_POST['update_verification_id'];
        $verified = ((string) ($_POST['is_verified'] ?? '0')) === '1';

        if ($repo->updateVerificationStatus($updateId, $verified)) {
            redirect('submissions.php?' . http_build_query(array_filter([
                'q' => $query,
                'seat' => $seat,
                'payment' => $payment,
                'page' => $page,
                'verification_updated' => '1',
            ])));
        }
        $statusMessage = 'Could not update verification status.';
    } elseif (isset($_POST['delete_id'])) {
        $deleteId = (int) $_POST['delete_id'];
        if ($repo->delete($deleteId)) {
            redirect('submissions.php?' . http_build_query(array_filter([
                'q' => $query,
                'seat' => $seat,
                'payment' => $payment,
                'page' => $page,
                'deleted' => '1',
            ])));
        }
        $statusMessage = 'Could not delete record.';
    }
}

$csrfToken = Security::getCsrfToken();
$pageTitle = 'Submissions';
$activeNav = 'submissions';

require __DIR__ . '/includes/header.php';
?>

<?php if ($statusMessage !== ''): ?>
  <div class="alert alert-error" role="alert"><?= e($statusMessage) ?></div>
<?php endif; ?>

<section class="panel">
  <div class="panel-head">
    <h2>All Submissions</h2>
    <a href="export.php?<?= e(http_build_query(array_filter(['seat' => $seat, 'payment' => $payment]))) ?>" class="btn btn-secondary btn-small">Export CSV</a>
  </div>

  <form class="filters-form" method="get">
    <label>
      <span>Search</span>
      <input type="search" name="q" value="<?= e($query) ?>" placeholder="Name, company, email, mobile, pincode…" />
    </label>
    <label>
      <span>Category</span>
      <select name="seat">
        <option value="">All</option>
        <?php foreach (['micro', 'small', 'medium', 'startup', 'professionals', 'other'] as $opt): ?>
          <option value="<?= e($opt) ?>" <?= $seat === $opt ? 'selected' : '' ?>><?= e(seat_label($opt)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      <span>Payment</span>
      <select name="payment">
        <option value="">All</option>
        <option value="pending" <?= $payment === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="paid" <?= $payment === 'paid' ? 'selected' : '' ?>>Payment Done</option>
        <option value="failed" <?= $payment === 'failed' ? 'selected' : '' ?>>Not Done</option>
      </select>
    </label>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="submissions.php" class="btn btn-ghost">Reset</a>
  </form>

  <p class="results-meta"><?= number_format($total) ?> result<?= $total === 1 ? '' : 's' ?></p>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Company</th>
          <th>Email</th>
          <th>Mobile</th>
          <th>Category</th>
          <th>Location</th>
          <th>Payment</th>
          <th>Verified</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows === []): ?>
          <tr><td colspan="11" class="empty-cell">No matching submissions.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td>#<?= (int) $row['id'] ?></td>
              <td><?= e($row['name']) ?></td>
              <td><?= e((string) ($row['company_name'] ?? '')) ?: '—' ?></td>
              <td><?= e($row['email']) ?></td>
              <td><?= e($row['mobile']) ?></td>
              <td><?= e(seat_label((string) $row['seat'])) ?></td>
              <td><?= e($row['district']) ?>, <?= e($row['state']) ?></td>
              <td><?php require __DIR__ . '/includes/payment_status_field.php'; ?></td>
              <td><?php require __DIR__ . '/includes/verification_status_field.php'; ?></td>
              <td><?= e(format_datetime($row['created_at'])) ?></td>
              <td class="actions-cell">
                <a href="view.php?id=<?= (int) $row['id'] ?>">View</a>
                <form method="post" class="inline-form" onsubmit="return confirm('Delete this submission permanently?');">
                  <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" />
                  <input type="hidden" name="delete_id" value="<?= (int) $row['id'] ?>" />
                  <button type="submit" class="link-danger">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
    <nav class="pagination" aria-label="Pagination">
      <?php
      $baseQuery = array_filter([
          'q' => $query,
          'seat' => $seat,
          'payment' => $payment,
      ]);
      for ($i = 1; $i <= $totalPages; $i++):
          $baseQuery['page'] = (string) $i;
          $url = '?' . http_build_query($baseQuery);
          ?>
        <a href="<?= e($url) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
