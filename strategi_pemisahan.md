Berikut blueprint arsitektur lengkap dan strategi pemisahan backend–frontend untuk HELMEPPO, disusun sistematis agar bisa diverifikasi dan langsung dieksekusi tim.

Bagian A – Klasifikasi File: Backend, Frontend, Hibrida

Catatan penting:
- Pola saat ini: hampir semua halaman utama adalah file PHP yang:
  - include `connect.php` (akses DB langsung)
  - include `navbar.php`
  - mengandung HTML + query SQL + logika bisnis dalam satu file
- Ini menjadikan banyak file berada di kategori Hibrida dan kandidat refactor utama.

1. Backend murni (logika, utilitas, integrasi, script teknis)

Ciri: tidak menampilkan UI ke user akhir; fokus ke koneksi, helper, maintenance, ekspor, dsb.

Contoh (non-UI, dianggap backend):

- Koneksi dan konfigurasi:
  - [`connect.php`](connect.php:1)
  - [`connect_production.php`](connect_production.php:1)
  - [`connect_byethost.php`](connect_byethost.php:1)

- Helper / service-like:
  - [`absen_helper.php`](absen_helper.php:1) – validasi absensi, cek shift, logika lupa absen, dll.
  - [`functions_role.php`](functions_role.php:1) – role resolution, otorisasi terkait posisi.
  - [`email_helper.php`](email_helper.php:1)
  - [`security_helper.php`](security_helper.php:1)
  - [`telegram_helper.php`/`set_telegram_webhook.php`/`telegram_webhook.php`] (integrasi bot).
  - [`calculate_status_kehadiran.php`](calculate_status_kehadiran.php:1)
  - Berbagai script debug/migrasi/fix:
    - `fix_*`, `migrasi_*`, `run_migration.php`, `clean_database.php`, `fix_admin_tardiness.php`, `fix_rekap_absen_status.php`, `fix_dokumen_medis_upload.php`, `migrate_pengajuan_izin_schema.php`, dll.
  - Dokumen/template pemrosesan:
    - [`docx.php`](docx.php:1), [`generate_slip.php`](generate_slip.php:1), [`generate_certificate.php`](generate_certificate.php:1)

- API-style saat ini:
  - [`api_shift_calendar.php`](api_shift_calendar.php:1) – sudah mendekati API backend.
  - [`api_location_validate.php`](api_location_validate.php:1)
  - `api_kalender.php`, `api_v2_test.php`, dll. (perlu distandardisasi, tapi secara konsep backend).

- Vendor / library:
  - `vendor/`, `tbs/`, `tbs/opentbs/` – sepenuhnya backend/dependency.

Kesimpulan:
- File-file ini adalah kandidat langsung untuk masuk ke layer backend (config, helpers, services, repositories, jobs).

2. Frontend murni (UI, static, tampilan)

Ciri: HTML/CSS/JS tanpa akses DB langsung; aman tetap di sisi presentasi.

- Assets:
  - `assets/css/*.css`
  - `assets/js/*.js` (perlu cek isi, tapi secara target harus jadi frontend-only; saat ini beberapa mengandung sedikit logika, tetapi bukan akses DB).
  - `logo.png`, images lain, icons.
- Dokumen HTML referensi:
  - `shift_management_quick_reference.html`
- Partial UI:
  - [`navbar.php`](navbar.php:64) – saat ini ada sedikit logika session/role; target: dipindah jadi view/partial dengan data dari controller.

Target:
- Semua ini akan ditempatkan di `frontend/public/assets` dan `frontend/views/partials`.

3. Hibrida/legacy (mencampur tampilan + SQL + logika)

Ini inti masalah yang harus dipisah menjadi controller + service/repo + view.

Contoh utama (bukan daftar penuh tapi kategori representatif):

- Autentikasi & landing:
  - [`index.php`](index.php:1-627) – login & registrasi whitelist: SQL + whitelist check + HTML form.
  - [`login.php`](login.php:1-112) – proses login backend murni (tanpa HTML); ini nanti jadi endpoint backend. Saat ini sudah cukup terpisah.
  - [`logout.php`](logout.php:1-6) – endpoint logout (backend, akan dipindah).
- Dashboard dan halaman utama:
  - [`mainpage.php`](mainpage.php:1-779) – heavy: query statistik absensi, shift, telegram, plus HTML dashboard.
- Absensi:
  - [`absen.php`](absen.php:1-178) – include DB + helper, validasi + HTML kamera.
  - [`proses_absensi.php`](proses_absensi.php:1-800+) – JSON API-like, tapi di root; ini backend.
- Shift & kalender:
  - [`shift_management.php`](shift_management.php:1-453) – query + HTML tabel admin + JS fetch ke `api_shift_calendar.php`.
  - [`shift_calendar.php`](shift_calendar.php:1-300+), [`kalender.php`](kalender.php:11-280+), [`kalender_fixed.php`](kalender_fixed.php:12-283) – hibrida antara UI kalender dan query/data.
  - [`shift_confirmation.php`](shift_confirmation.php:1-263) – load data + HTML.
  - [`jadwal_shift.php`](jadwal_shift.php:1-200) – load data + HTML.
- Rekap dan laporan:
  - [`rekapabsen.php`](rekapabsen.php:5-57), [`rekap_absensi.php`](rekap_absensi.php:3-345), [`export_absensi.php`](export_absensi.php:1-400+) – gabungan query + tampilan / output file.
  - [`view_absensi.php`](view_absensi.php:2-316), [`view_user.php`](view_user.php:1-94)
  - [`best_performer.php`](best_performer.php:20-604)
- Surat izin dan dokumen:
  - [`suratizin.php`](suratizin.php:11-1415)
  - [`ajukan_izin_sakit.php`](ajukan_izin_sakit.php:25-335)
  - [`approve.php`](approve.php:3-69)
  - [`proses_pengajuan_izin_sakit.php`](proses_pengajuan_izin_sakit.php:111-166)
  - [`test_final_suratizin_system.php`](test_final_suratizin_system.php:142-145)
- Profil & pengelolaan user:
  - [`profile.php`](profile.php:15-1028)
  - [`tambah_pegawai.php`](tambah_pegawai.php:2-141)
  - [`edit_pegawai.php`](edit_pegawai.php:2-226)
  - [`edit_user.php`](edit_user.php:2-57)
  - [`delete_user.php`](delete_user.php:2-4)
  - [`whitelist.php`](whitelist.php:6-223)
  - [`posisi_jabatan.php`](posisi_jabatan.php:16-592)
  - [`view_user.php`](view_user.php:2-94)
- Slip gaji:
  - [`slipgaji.php`](slipgaji.php:36-105)
  - [`slip_gaji_management.php`](slip_gaji_management.php:3-275)
- Overview / analitik:
  - [`overview.php`](overview.php:16-1057)
  - [`overview_enhanced.php`](overview_enhanced.php:3-705)
- Lain:
  - Banyak file `test_*.php`, `*debug*` mencampur query dan HTML sebagai tools sementara; anggap sebagai backend tools non-produksi.

Semua file di kategori ini adalah target refactoring menjadi:
- Controller/API backend + View frontend + JS consumer.

Bagian B – Arsitektur Target & Struktur Folder

Target: PHP backend terpisah yang menyediakan API dan server-side rendering minimal, dengan frontend yang tipis dan bersih. Kompatibel XAMPP dan siap untuk future SPA.

Struktur direktori yang disarankan (opinionated):

- `/backend`
  - `/config`
    - `config.php` – konfigurasi umum (env, base URL, dsb).
    - `database.php` – inisialisasi PDO.
    - `app.php` – bootstrap, autoloader (PSR-4 sederhana), session config.
  - `/public`
    - `index.php` – front controller backend API (opsional).
    - `/api`
      - `auth.php` – login/logout, session/token.
      - `users.php`
      - `attendance.php`
      - `shifts.php`
      - `leaves.php`
      - `payroll.php`
      - `reports.php`
      - `telegram.php`
      - `...` (endpoint modular)
  - `/src`
    - `/Controller`
      - `AuthController.php`
      - `UserController.php`
      - `AttendanceController.php`
      - `ShiftController.php`
      - `LeaveController.php`
      - `PayrollController.php`
      - `ReportController.php`
      - `TelegramController.php`
      - `FileController.php`
    - `/Service`
      - `AuthService.php`
      - `UserService.php`
      - `AttendanceService.php`
      - `ShiftService.php`
      - `LeaveService.php`
      - `PayrollService.php`
      - `ReportService.php`
      - `NotificationService.php` (email, Telegram)
      - `SecurityService.php`
    - `/Repository`
      - `UserRepository.php`
      - `AttendanceRepository.php`
      - `ShiftRepository.php`
      - `LeaveRepository.php`
      - `PayrollRepository.php`
      - `WhitelistRepository.php`
      - `BranchRepository.php`
      - `...`
    - `/Model`
      - `User.php`
      - `Attendance.php`
      - `Shift.php`
      - `LeaveRequest.php`
      - `PayrollSlip.php`
      - dll (opsional, simple DTO/array juga bisa).
    - `/Helper`
      - `AbsenHelper.php` (refactor dari [`absen_helper.php`](absen_helper.php:1))
      - `SecurityHelper.php` (dari [`security_helper.php`](security_helper.php:1))
      - `TelegramHelper.php`
      - `EmailHelper.php`
      - `Logger.php`
      - Utility lain.
- `/frontend`
  - `/public`
    - `index.php` – halaman login/landing; hanya view + panggil API backend.
    - `mainpage.php` – dashboard; konsumsi API attendance, shifts, dsb.
    - `absen.php`
    - `suratizin.php`
    - `jadwal_shift.php`
    - `shift_calendar.php`
    - `shift_management.php` (UI saja, data via API)
    - `slipgaji.php`
    - `slip_gaji_management.php` (UI, data via API)
    - `overview.php`
    - `overview_enhanced.php`
    - `profile.php`
    - dll.
  - `/views`
    - `/layouts`
      - `base.php`
      - `auth_layout.php`
    - `/partials`
      - `navbar.php` – tanpa query; hanya pakai variabel dari controller.
      - `footer.php`
    - `/pages`
      - `auth/login.php`
      - `dashboard/main.php`
      - `attendance/absen.php`
      - `attendance/rekap.php`
      - `shifts/calendar.php`
      - `shifts/management.php`
      - `leaves/form.php`
      - `leaves/approve.php`
      - `payroll/list.php`
      - `payroll/manage.php`
      - dll.
  - `/assets`
    - `/css` – pindahan `assets/css`, `style_modern.css`, dll.
    - `/js`
      - `auth.js`
      - `attendance.js`
      - `shifts.js`
      - `leaves.js`
      - `payroll.js`
      - `overview.js`
    - `/img`
      - `logo.png`, dll.

Bridging dengan struktur saat ini (XAMPP):
- Untuk tahap transisi:
  - Tetap gunakan root `/HELMEPPO` sebagai public root.
  - Tambah:
    - `/HELMEPPO/backend/...`
    - `/HELMEPPO/frontend/...`
  - File legacy seperti `mainpage.php` diarahkan menjadi shim yang:
    - memanggil bootstrap frontend,
    - load view baru atau redirect ke route baru,
    - tanpa query langsung.

Bagian C – Pola Legacy & Aturan Refactoring

Pola legacy yang ditemukan:
1) File page = HTML + `include 'connect.php'` + SQL + loop + logic.
2) `navbar.php` membaca langsung `$_SESSION` dan memutuskan link + role.
3) API pseudo:
   - `proses_absensi.php`, `api_shift_calendar.php`, `api_location_validate.php` sudah mengembalikan JSON namun bercampur dengan include global.

Aturan refactoring eksplisit:

1. Semua akses DB
- Dipindah dari:
  - Langsung pakai `$pdo->prepare(...)` di view/page.
- Menjadi:
  - Repository di backend, contoh:
    - `AttendanceRepository::getByUserAndDate($userId, $date)`
    - `ShiftRepository::getAssignmentsForMonth(...)`
- Controller/API tidak memiliki SQL string; hanya panggil repository.

2. Logika bisnis / validasi / workflow
- Dipindah dari:
  - `mainpage.php`, `absen_helper.php` (boleh tetap tapi di namespace service/helper), `suratizin.php`, dll.
- Menjadi:
  - Service:
    - `AttendanceService::validateCheckIn(...)`
    - `AttendanceService::recordCheckIn/Out(...)`
    - `LeaveService::submitLeave(...)`
    - `LeaveService::approveLeave(...)`
    - `ShiftService::assignShift(...)`
    - `AuthService::login(...)`, dll.
- View/frontend hanya:
  - Menampilkan data (variabel yang sudah disiapkan).
  - Trigger aksi via HTTP (form action ke API atau fetch JS).

3. Controller/API
- Tanggung jawab:
  - Parse input (GET/POST/JSON).
  - Validasi dasar.
  - Panggil Service.
  - Return:
    - JSON: `{"status":"success","data":{...}}`
    - atau render template (server-side).

4. View / template
- Tidak boleh:
  - include `connect.php`
  - menjalankan query SQL
  - mengubah session secara semantik (hanya read).
- Boleh:
  - `if/foreach` sederhana.
  - Render variabel yang di-passing controller.
  - Include partial seperti `navbar.php`, `footer.php`.

Bagian D – Contoh Transformasi End-to-End

Contoh: Modul Absensi (absen.php + proses_absensi.php + absen_helper.php)

Legacy (disederhanakan):
- `absen.php`:
  - include `connect.php`, `absen_helper.php`
  - hitung `$validation_result = validateAbsensiConditions(...)`
  - HTML kamera, tombol, dsb.
- `proses_absensi.php`:
  - include `connect.php`, `absen_helper.php`, `security_helper.php`
  - proses POST, validasi, insert/update `absensi`
  - echo JSON.

Target baru:

Backend:

- [`backend/src/Repository/AttendanceRepository.php`](backend/src/Repository/AttendanceRepository.php:1)
  - `findTodayByUser($userId)`
  - `insertCheckIn(...)`
  - `updateCheckOut(...)`
  - `logError(...)`
- [`backend/src/Service/AttendanceService.php`](backend/src/Service/AttendanceService.php:1)
  - gunakan `AttendanceRepository`, `ShiftRepository`, `SecurityHelper`.
  - `validateCheckInEligibility($userId, $role, $coords)`
  - `handleCheckIn($userId, $payload)`
  - `handleCheckOut($userId, $payload)`
- [`backend/public/api/attendance.php`](backend/public/api/attendance.php:1)
  - `POST /api/attendance/check-in`
    - baca JSON
    - cek session/token
    - panggil `AttendanceService::handleCheckIn`
    - return JSON
  - `POST /api/attendance/check-out`
    - panggil `AttendanceService::handleCheckOut`
  - `GET /api/attendance/status-today`
    - kembalikan status absen user hari ini.

Frontend:

- [`frontend/public/absen.php`](frontend/public/absen.php:1)
  - Cek session frontend (via include bootstrap yang membaca session dari backend jika shared, atau redirect jika tidak login).
  - Render HTML:
    - layout, navbar, video, tombol.
  - Sertakan `assets/js/attendance.js`.
- [`frontend/assets/js/attendance.js`](frontend/assets/js/attendance.js:1)
  - Ambil geolocation, capture foto (canvas).
  - Panggil:
    - `fetch('/backend/public/api/attendance/check-in', {...})`
    - `fetch('/backend/public/api/attendance/check-out', {...})`
  - Update UI berdasarkan response JSON.

Hasil:
- Tidak ada SQL di `frontend/public/absen.php`.
- Seluruh business rules absensi di backend service/repository.

Pola ini diaplikasikan ke:
- `mainpage.php` → `DashboardController + DashboardService + /views/dashboard/main.php`
- `suratizin.php`/`proses_pengajuan_izin_sakit.php` → `LeaveController + LeaveService + views/leaves`
- `shift_management.php`/`api_shift_calendar.php` → `ShiftController + ShiftService + ShiftRepository`
- `slipgaji.php`/`slip_gaji_management.php`/`generate_slip.php` → `PayrollController + PayrollService + ...`
- `overview.php`/`overview_enhanced.php` → `ReportController + ReportService`

Bagian E – Desain API (high-level)

Response standar:
- Saran format:
  - `{"status":"success","data":{...}}`
  - `{"status":"error","message":"...", "errorCode":"...", "errors":{...}}`

Contoh API per modul (ringkas):

1. Auth
- `POST /api/auth/login`
  - input: `{username, password}`
  - output success: `data: {user: {id, name, role}, token/sessionId}`
- `POST /api/auth/logout`
- `GET /api/auth/me`
  - detail user aktif.

2. User Management
- `GET /api/users`
- `GET /api/users/{id}`
- `POST /api/users` (admin)
- `PUT /api/users/{id}`
- `DELETE /api/users/{id}` (admin)

3. Attendance
- `GET /api/attendance/today` – status user saat ini.
- `POST /api/attendance/check-in`
- `POST /api/attendance/check-out`
- `GET /api/attendance/summary?userId=&from=&to=`
- `GET /api/attendance/report` – untuk rekap/admin/export.

4. Shift/Calendar
- `GET /api/shifts/templates`
- `GET /api/shifts/assignments?month=&year=&userId=`
- `POST /api/shifts/assign` (admin)
- `DELETE /api/shifts/assign/{id}`
- `POST /api/shifts/confirm` – user confirm via API.

5. Leave (surat izin)
- `POST /api/leaves`
- `GET /api/leaves?userId=`
- `POST /api/leaves/{id}/approve` (admin)
- `POST /api/leaves/{id}/reject` (admin)

6. Payroll
- `GET /api/payroll/slips?userId=`
- `GET /api/payroll/slips/{id}`
- `POST /api/payroll/slips/generate` (admin)

7. Integrasi Telegram / Notifikasi
- `POST /api/telegram/webhook` – untuk webhook bot.
- `POST /api/notifications/test` – optional.

Semua:
- Gunakan PDO prepared statements di Repository.
- Validasi input di Controller/Service.
- Role-based access di middleware / service.

Bagian F – Pola Integrasi Frontend

1. Halaman login (frontend/public/index.php)
- Render form login.
- Submit ke `/backend/public/api/auth/login` via POST.
- Jika sukses:
  - Set session (jika share cookie path) atau simpan token di session frontend.
  - Redirect ke `mainpage.php`.

2. Halaman yang butuh proteksi
- Setiap `frontend/public/*.php`:
  - Panggil helper `require_auth()` yang:
    - Cek session/token (didapat dari backend).
    - Jika tidak valid → redirect ke login.
- Data halaman:
  - Bisa:
    - Dilempar dari backend SSR (include controller yang memanggil API internal).
    - Atau di-load via JS fetch ke endpoint JSON setelah HTML render.

3. Komponen dinamis (tabel, kalender, dsb.)
- Pola:
  - HTML skeleton + JS fetch ke `/api/...`.
  - Contoh: `shift_management.php`:
    - Hilangkan query `SELECT` di view.
    - JS panggil `/api/shifts/assignments` untuk isi tabel.

Bagian G – Rencana Migrasi Bertahap (Praktis untuk XAMPP)

Langkah-langkah yang bisa diikuti tim tanpa big bang rewrite:

1) Label dan dokumentasi (status: sudah dilakukan di blueprint ini)
- Tandai file:
  - Backend murni
  - Frontend murni
  - Hybrid (prioritas refactor)

2) Introduce backend core tanpa mengubah route publik
- Tambah:
  - `/backend/config/{config.php,database.php,app.php}`
  - `/backend/src/{Controller,Service,Repository,Helper}`
  - `/backend/public/api` minimal:
    - `attendance.php` (wrap logika dari `proses_absensi.php`)
    - `shift_calendar.php` (wrap `api_shift_calendar.php`)

3) Ekstrak DB dan helper ke backend
- Pastikan semua file baru memakai satu sumber PDO: `backend/config/database.php`.
- Refactor helper seperti `absen_helper.php` menjadi class di `/backend/src/Helper`.

4) Modul per modul refactor
Urutan disarankan:
- Modul 1: Auth
  - Pindahkan logika `login.php` ke `AuthController` + `AuthService`.
  - `frontend/public/index.php` hanya view.
- Modul 2: Attendance
  - Jadikan `proses_absensi.php` → `backend/public/api/attendance.php`.
  - `frontend/public/absen.php` konsumer API.
- Modul 3: Shift & Kalender
  - `api_shift_calendar.php` → `ShiftController`.
  - `shift_management.php`, `shift_calendar.php`, `kalender.php` → view tipis + JS.
- Modul 4: Surat Izin
  - `suratizin.php`, `approve.php`, `proses_pengajuan_izin_sakit.php` → `LeaveService`, `LeaveController`.
- Modul 5: Payroll
  - `slipgaji*.php`, `generate_slip.php` → `PayrollService`, `PayrollController`.
- Modul 6: Overview / Reports
  - `overview*.php`, rekap, best_performer.

Setiap langkah:
- Pertahankan endpoint lama sementara (shim) yang memanggil layer baru.
- Uji regresi per modul sebelum lanjut.

5) Bersihkan include langsung
- Ganti:
  - `include 'connect.php';` di view → dihapus; view menerima data dari controller atau fetch JS.
  - `include 'navbar.php';`:
    - Ubah `navbar.php` menjadi partial tanpa query, hanya pakai variabel `$currentUser` / `$menu`.
    - Data user sudah disiapkan oleh controller.

6) Decomission legacy
- Setelah setiap modul memakai API/controller baru:
  - Hapus SQL dari view.
  - Tandai file lama yang hanya jadi wrapper untuk redirect ke lokasi baru.

Bagian H – Quality, Security, Maintainability

- Gunakan PDO (sudah dipakai) dengan prepared statements di semua Repository.
- Centralized error handling:
  - Middleware atau base controller yang tangani exception → JSON error standar.
- CSRF:
  - Untuk form non-JSON (SSR), gunakan token di session dan validasi di Controller.
- RBAC:
  - `AuthService` + `AuthorizationService`/helper:
    - `requireRole(['admin'])`, `requireRole(['admin','superadmin'])` dll.
- Naming:
  - Kelas: `PascalCase`, file match nama kelas.
  - Namespace sederhana: `App\Controller`, `App\Service`, dsb (opsional untuk XAMPP tapi direkomendasikan).
- Reusability:
  - Navbar, layout, komponen kartu statistik sebagai partial view.
- Future SPA:
  - Karena semua bisnis dipindah ke backend `/api`, nanti React/Vue bisa langsung pakai API yang sama tanpa rombak ulang.

Ringkasan Eksekusi

- Blueprint ini:
  - Mengklasifikasikan peran file utama.
  - Mendefinisikan struktur direktori backend/frontend yang jelas.
  - Menetapkan aturan refactoring konkret (SQL → Repository, logic → Service, view → tipis).
  - Memberikan contoh transformasi penuh (modul absensi) yang bisa dijadikan template untuk modul lain.
  - Menyusun rencana migrasi bertahap yang menjaga sistem tetap jalan.