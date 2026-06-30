<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';

$repo = new RegistrationRepository();
$seat = strtolower(Security::sanitizeString((string) ($_GET['seat'] ?? ''), 32));
$payment = strtolower(Security::sanitizeString((string) ($_GET['payment'] ?? ''), 16));

$allowedSeats = ['', 'micro', 'small', 'medium', 'startup', 'professionals', 'other'];
$allowedPayment = ['', 'pending', 'paid', 'failed'];

if (!in_array($seat, $allowedSeats, true)) {
    $seat = '';
}
if (!in_array($payment, $allowedPayment, true)) {
    $payment = '';
}

$rows = $repo->exportAll(
    $seat !== '' ? $seat : null,
    $payment !== '' ? $payment : null
);

$filename = 'msme_registrations_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
if ($out === false) {
    exit('Unable to export.');
}

fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, [
    'ID',
    'Name',
    'Company Name',
    'Category',
    'Mobile',
    'Email',
    'Pincode',
    'State',
    'District',
    'Payment Status',
    'Verified',
    'IP Address',
    'Created At',
    'Updated At',
]);

foreach ($rows as $row) {
    fputcsv($out, [
        $row['id'],
        $row['name'],
        $row['company_name'] ?? '',
        seat_label((string) $row['seat']),
        $row['mobile'],
        $row['email'],
        $row['pincode'],
        $row['state'],
        $row['district'],
        payment_status_label(isset($row['payment_status']) ? (string) $row['payment_status'] : null),
        verification_status_label($row),
        $row['ip_address'] ?? '',
        $row['created_at'],
        $row['updated_at'],
    ]);
}

fclose($out);
exit;
