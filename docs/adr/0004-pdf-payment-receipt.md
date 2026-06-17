# PDF payment receipt (kuitansi), generated on demand

A customer can download a PDF receipt (kuitansi) for a paid booking from their order history. The receipt is **derived on demand** from the booking and its payment — there is no stored file and no schema change. This extends ADR-0003 (the payment lifecycle) and follows the derive-don't-store stance of ADR-0001 (availability).

- **Generate on demand, don't store.** `GET /api/receipt.php?booking_id=N` builds the PDF fresh from booking + payment data each request. No `receipt_path` column, no files on disk to serve, secure, or clean up, and the receipt can never drift from the booking it describes. A receipt exists exactly when a payment row exists (`paid` or `refunded`); an unpaid/pending booking has none (422).
- **No PDF dependency.** The course bans build tools (no Node, no composer). `lib/receipt.php` carries a minimal single-page PDF writer (Helvetica, one text line per row) instead of vendoring FPDF. The content stream is left **uncompressed** so the receipt's data (booking code, amount) is present as literal bytes — which also lets a unit test assert the code and amount appear in the output without parsing PDF.
- **Owner-only, reuses payment guards.** The generator throws the same `NotBookingOwnerException` (403) as the payment path, plus `ReceiptNotAvailableException` (422) when no payment exists and `InvalidArgumentException` (404) when the booking is missing.

## Considered Options

*Store a generated file* (a `payments.receipt_path` column + files under a non-web-root `storage/`) was rejected: it adds a migration, a file lifecycle (cleanup on cancel, orphan handling), and a second place for the data to live, for no gain over regenerating a small document. *User-uploaded proof-of-transfer PDFs* was rejected because payment here is simulated (ADR-0003) — there is no real transfer to evidence; the system itself is the source of truth, so it issues the receipt. *Vendoring FPDF* was rejected to keep the repo dependency-free under the no-build-tools constraint.

## Consequences

A new read-only endpoint and `lib/receipt.php`; no schema or seed change, so `tests/bootstrap.php` is unaffected. The PDF writer is deliberately minimal (single page, ASCII/WinAnsi text, no images or wrapping) — long venue names are not wrapped. This also satisfies the project's "reporting PDF" deliverable.
