# Pemetaan Requirement (RQ) → Kode → Test (untuk White Box Testing)

Menjalankan semua test requirement:

    php artisan test --testsuite=Feature --filter=Requirement

Migration baru harus dijalankan: `php artisan migrate` (mulai.bat sudah melakukannya otomatis).
Test upload foto memakai `UploadedFile::fake()->image()` sehingga butuh ekstensi PHP **GD** aktif.

| RQ | Lokasi kode utama | Test |
|---|---|---|
| RQ-001 | `Auth/AuthenticatedSessionController::store` (admin → `admin.dashboard`), `Admin/DashboardController` | LoginRequirementTest::test_rq001_* |
| RQ-002 | `Http/Requests/Auth/LoginRequest::authenticate` + `lang/id/auth.php` | test_rq002_* |
| RQ-003 | `LoginRequest::ensureIsNotRateLimited` (MAX_ATTEMPTS=5, LOCKOUT_SECONDS=300) | test_rq003_* |
| RQ-004 | `Http/Middleware/AdminMiddleware::handle` (guest → login, customer → 403) | test_rq004_* |
| RQ-005–007 | `Auth/RegisteredUserController::store` (validasi nama, email unik, no. HP, password) | RegisterRequirementTest |
| RQ-008 | `RegisteredUserController::store` (role dipaksa `customer`) | test_rq008_* |
| RQ-009–010 | `Auth/PasswordResetLinkController::store` + `lang/id/passwords.php` | ForgotPasswordRequirementTest |
| RQ-011–012 | `Auth/NewPasswordController::store`, `config/auth.php` (`expire` = 60 menit) | test_rq011_*, test_rq012_* |
| RQ-013–015 | `Admin/ProductController::store/update` (`rules()`, `messages()`) | ManageProductRequirementTest |
| RQ-016–017 | `ProductController::destroy`, `toggleActive`, `Product::hasOrderHistory` | test_rq016_*, test_rq017_* |
| RQ-018–019 | `Admin/StockController::adjust`, `Product::adjustStock` (DomainException bila negatif) | ManageStockRequirementTest |
| RQ-020 | `Product::scopeLowStock`, `Admin/DashboardController`, `admin/dashboard.blade.php` | test_rq020_* |
| RQ-021–022 | `Order::nextStatus`, `Order::canTransitionTo`, `Admin/OrderController::update` | UpdateOrderStatusRequirementTest |
| RQ-023 | `Admin/OrderController::update` (`cancel_reason` required_if cancelled), `Order::changeStatus` | test_rq023_* |
| RQ-024 | `Order::changeStatus` → tabel `order_status_logs` (kolom `reason` baru) | test_rq024_* |
| RQ-025–026 | `Admin/PromoController::store` / `validated` | ManagePromoRequirementTest |
| RQ-027 | `Admin/PromoController::update` | test_rq027_* |
| RQ-028 | `Admin/PromoController::toggle` | test_rq028_* |
| RQ-029–031 | `Services/SalesReport::generate`, `Admin/ReportController::sales` | SalesReportRequirementTest |

## Akun contoh (php artisan db:seed)
- Admin: `admin@tokoroti.com` / `password123`
- Customer: `customer@gmail.com` / `password123`

## Aturan bisnis yang dipilih
- Penjualan pada laporan = pesanan berstatus Dibayar, Diproses, Siap Diambil, Dikirim, Selesai (Menunggu Pembayaran & Dibatalkan tidak dihitung). Total sudah termasuk ongkir.
- Pesanan boleh dibatalkan (dengan alasan) selama belum Selesai/Dibatalkan; stok otomatis kembali.
- Alur: Menunggu Pembayaran → Dibayar → Diproses → Siap Diambil (pickup) / Dikirim (delivery) → Selesai.
- Stok menipis = `stock <= min_stock` (default min_stock = 5).
