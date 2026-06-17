<?php
// PDF payment receipt (kuitansi), ADR-0004. The receipt is derived on demand
// from a booking and its payment — nothing is stored (consistent with ADR-0001).
// The PDF is produced by a tiny dependency-free writer below (no composer/FPDF,
// course constraint: no build tools).
require_once __DIR__ . '/bookings.php';
require_once __DIR__ . '/payments.php';

class ReceiptNotAvailableException extends RuntimeException {} // 422 (no payment yet)

// Booking + payment data behind a receipt, or null if the booking is missing.
function getReceiptData(PDO $pdo, int $booking_id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT b.id, b.user_id, b.date, b.start_hour, b.end_hour, b.status, b.code,
                c.label AS court_label, v.name AS venue_name, v.price_per_hour,
                u.name AS user_name,
                p.amount, p.status AS payment_status, p.paid_at
         FROM bookings b
         JOIN courts c ON c.id = b.court_id
         JOIN venues v ON v.id = c.venue_id
         JOIN users  u ON u.id = b.user_id
         LEFT JOIN payments p ON p.booking_id = b.id
         WHERE b.id = ?'
    );
    $stmt->execute([$booking_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// Build the PDF receipt for a booking the caller owns. Returns the PDF bytes.
// Throws InvalidArgumentException if the booking is missing,
// NotBookingOwnerException if it belongs to someone else, and
// ReceiptNotAvailableException if the booking has no payment yet.
function generateReceiptForUser(PDO $pdo, int $user_id, int $booking_id): string
{
    $d = getReceiptData($pdo, $booking_id);
    if (!$d) {
        throw new InvalidArgumentException('booking not found');
    }
    if ((int) $d['user_id'] !== $user_id) {
        throw new NotBookingOwnerException('not your booking');
    }
    if ($d['amount'] === null) {
        throw new ReceiptNotAvailableException('no payment for this booking');
    }

    $hours  = (int) $d['end_hour'] - (int) $d['start_hour'];
    $jam    = sprintf('%02d:00 - %02d:00 (%d jam)', $d['start_hour'], $d['end_hour'], $hours);
    $status = $d['payment_status'] === 'refunded' ? 'DIKEMBALIKAN (refund)' : 'LUNAS';

    $lines = [
        'PadelKuy - Kuitansi Pembayaran',
        '',
        'Kode Booking : ' . $d['code'],
        'Status       : ' . $status,
        'Atas Nama    : ' . $d['user_name'],
        'Venue        : ' . $d['venue_name'] . ' - Court ' . $d['court_label'],
        'Tanggal      : ' . $d['date'],
        'Jam          : ' . $jam,
        'Jumlah       : ' . rupiah((int) $d['amount']),
        'Dibayar      : ' . ($d['paid_at'] ?? '-'),
        '',
        'Terima kasih telah bermain di PadelKuy.',
    ];

    return renderPdf($lines);
}

// Format an integer rupiah amount as "Rp 200.000".
function rupiah(int $amount): string
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Minimal single-page PDF writer: one text line per row, top to bottom, in
// Helvetica. The content stream is left uncompressed so the data (booking code,
// amount) is present as literal bytes — both human- and test-readable.
function renderPdf(array $lines): string
{
    $content = "BT\n/F1 12 Tf\n";
    $y = 800;
    foreach ($lines as $line) {
        $esc = strtr($line, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
        $content .= sprintf("1 0 0 1 50 %d Tm\n(%s) Tj\n", $y, $esc);
        $y -= 22;
    }
    $content .= 'ET';

    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
           . '/Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        5 => '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream",
    ];

    $pdf     = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $num => $body) {
        $offsets[$num] = strlen($pdf);
        $pdf .= "$num 0 obj\n$body\nendobj\n";
    }

    $xref  = strlen($pdf);
    $count = count($objects) + 1; // including the free object 0
    $pdf  .= "xref\n0 $count\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size $count /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";

    return $pdf;
}
