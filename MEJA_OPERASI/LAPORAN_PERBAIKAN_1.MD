DOKUMENTASI ARSITEKTUR DAN PEMETAAN KODE HELMEPPO (SETELAH PEMBERSIHAN AWAL)

Catatan ruang lingkup:
- Hanya mencakup file PHP aplikasi inti di root dan subfolder relevan.
- Mengabaikan vendor/, tbs/, dan file non-PHP (zip, docx, csv, log, dll).
- Logger.php dan log_viewer.php serta backup/test tertentu telah dihapus sesuai instruksi sebelumnya.

1. Ikhtisar Arsitektur Saat Ini

Secara garis besar arsitektur HELMEPPO adalah:

- Model: Akses langsung ke database via PDO dari dalam file PHP “entry point” dan helper. Tidak ada layer model terpisah formal.
- Controller/Logic:
  - Tersebar di:
    - proses_*.php (handler form / action spesifik).
    - *_helper.php (fungsi utilitas bisnis).
    - api_*.php (endpoint AJAX/JSON).
    - Beberapa file campuran dengan query langsung + HTML.
- View/Frontend:
  - Banyak halaman adalah campuran PHP + HTML + CSS inline + JS inline.
  - Navbar dan beberapa layout menggunakan include.
  - CSS utama ada di assets/css, tapi beberapa file masih pakai link langsung “style_modern.css” (path lama) atau inline style.
- Routing:
  - Berbasis file: index.php, mainpage.php, absen.php, suratizin.php, dsb dipanggil langsung.
  - Tidak ada router terpusat.
- Session & Security:
  - security_helper.php sebagai utilitas session/CSRF/rate limiting.
  - Banyak file memulai session sendiri dan melakukan cek role.

Arsitektur saat ini cenderung:
- Monolitik berbasis file.
- Strong coupling antara view dan business logic.
- Banyak entry point langsung.
- Cocok dijadikan dasar refactor ke struktur yang lebih terorganisir (public/app/views/helpers).

2. Klasifikasi File: Frontend, Backend, Campuran, Legacy/Test

A. Frontend “murni” (dominan tampilan, minimal atau tanpa logic bisnis)

File berikut terutama menyajikan HTML/CSS/JS dan hanya memakai sedikit PHP untuk include session/navbar atau cek sederhana:

- navbar.php
  - Peran: Komponen navigasi global.
  - Logic: Cek session/role untuk menentukan link.
  - Styling: Inline <style> di dalam file.
  - Kategori: Campuran ringan (view + sedikit logic + CSS embedded), direkomendasikan dipindah ke view + CSS eksternal.

- overview_enhanced.php
  - Peran: Dashboard AI overview (alternatif/tampilan lanjutan).
  - Logic: Ambil data ringkas + panggil API lokal AI.
  - Banyak HTML + CSS embedded + JS.
  - Kategori: Campuran berat (UI kaya + data), tapi fungsinya sangat presentational; cocok direfaktor pisah logic.

- shift_management.php
  - Peran: UI admin untuk assign shift, tabel assignment.
  - Logic: Query data + render HTML + JS interaksi (fetch api_shift_calendar.php).
  - Kategori: Campuran (admin view + logic query). Secara arsitektur sebaiknya: controller terpisah + view terpisah.

Tidak ada file yang 100% “frontend only” (tanpa PHP/logic) di root; hampir semua mengandung minimal session/cek login atau query.

B. Backend murni (logic, handler, API, helper; tanpa atau minim HTML)

File di bawah berfokus pada proses, JSON, atau fungsi utilitas:

- connect.php / connect_production.php / connect_byethost.php
  - Peran: Konfigurasi koneksi database (PDO).
  - Dependensi: Digunakan oleh hampir semua modul.
  - Kategori: Backend murni (infrastruktur).

- security_helper.php
  - Peran: Security/CSRF/Session hardening, rate limiting, sanitasi input.
  - Dipakai oleh index.php, login.php, dan potensi file lain.
  - Kategori: Backend murni, logic kritis.

- absen_helper.php
  - Peran: Fungsi bisnis absensi:
    - hitungJarak(), validateUserShift(), validateUserLocation(), validateAdminTime(), validateAdminDay(), validateAbsensiConditions(), handleLupaAbsen(), dsb.
  - Dipakai oleh absen.php, proses_absensi.php, dan modul absensi.
  - Kategori: Backend murni (business logic absensi).

- functions_role.php
  - Peran: Helper role/otorisasi (mis: isAdminOrSuperadmin()).
  - Dipakai oleh mainpage.php, shift_management.php, dsb.
  - Kategori: Backend murni.

- email_helper.php
  - Peran: Abstraksi pengiriman email (PHPMailer), notifikasi izin dsb.
  - Kategori: Backend murni.

- api_*:
  - api_kalender.php, api_location_validate.php, api_shift_calendar.php, api_shift_confirmation_email.php, api_v2_test.php (sebagian test).
  - Peran: Endpoint AJAX/JSON.
  - Kategori:
    - api_kalender.php, api_location_validate.php, api_shift_calendar.php, api_shift_confirmation_email.php: Backend murni (API).
    - api_v2_test.php: lebih dekat ke test/debug.

- proses_*:
  - proses_absensi.php
    - Proses form absensi, validasi, simpan ke DB.
    - Pakai absen_helper.php, connect.php, session.
    - Backend murni (handler inti).
  - proses_konfirmasi_lembur.php
    - Update status lembur untuk user.
    - Backend murni.
  - proses_pengajuan_izin_sakit.php
    - Handler pengajuan izin sakit.
    - Backend murni.
  - proses_approve.php
    - Handler approve/reject izin (JSON response).
    - Gunakan email_helper, update absensi, dsb.
    - Backend murni, logic kritis (izin & sinkron absensi).

- generate_*, auto_generate_slipgaji.php
  - Peran: Pembuatan slip gaji/dokumen, cron jobs.
  - Backend murni.

- calculate_status_kehadiran.php, duration_calculator.php, ShiftConfigurationManager.php
  - Peran: Perhitungan status kehadiran, utilitas durasi, manajemen shift.
  - Backend murni, business logic.

- telegram_helper.php, telegram_webhook.php, set_telegram_webhook.php
  - Peran: Integrasi Telegram (helper, endpoint webhook, script set webhook).
  - Kategori: Backend murni.

- import_database.php, import_whitelist.php, clean_database.php, fix_*.php, migrasi_*.php, migrate_*.php, run_migration.php, create_face_tables.php, check_*.php
  - Peran: Skrip maintenance/migrasi/diagnostik.
  - Produksi normal tidak memanggil langsung dari UI.
  - Kategori: Backend murni (ops tool / maintenance).

C. File campuran frontend-backend (logic + query + HTML/CSS/JS)

Ini kelompok utama yang perlu perhatian refactor:

- index.php
  - Fungsi utama:
    - Entry point login & registrasi (whitelist-based).
    - Mengelola session & redirect.
    - Query DB (whitelist, posisi, cabang).
    - Render form login, form registrasi, whitelist modal.
    - Inline JS untuk whitelist check dan interaksi UI.
  - Dependensi:
    - connect.php, security_helper.php.
    - login.php (sebagai target action).
  - Campuran:
    - Controller (registrasi), Model (query), View (HTML/CSS/JS) dalam satu file.
  - Risiko:
    - Sulit diuji dan dipelihara; rawan bug pada perubahan kecil.

- mainpage.php
  - Fungsi utama:
    - Dashboard utama setelah login (untuk user dan admin).
    - Hitung statistic absensi, lupa absen pulang, persentase kehadiran.
    - Render UI kartu statistik, chart (Chart.js), wizard setup account, panduan Telegram.
  - Dependensi:
    - connect.php, functions_role.php, navbar.php.
  - Campuran berat:
    - Banyak query + perhitungan + markup + styling inline.
    - Logic kritis (perhitungan absensi) bercampur view.

- absen.php
  - Fungsi utama:
    - Halaman UI absensi harian (kamera, lokasi).
    - Validasi awal via validateAbsensiConditions().
    - Render UI status & tombol absen, JS (script_absen.js).
  - Dependensi:
    - connect.php, absen_helper.php, navbar.php, proses_absensi.php.
  - Campuran:
    - Logic guard, validasi, view, inline style dicampur.

- suratizin.php
  - Fungsi utama:
    - Form pengajuan surat izin/sakit.
    - Proses generate dokumen docx (OpenTBS) langsung di file yang sama.
    - Logic simpan pengajuan, generate file, update DB.
  - Dependensi:
    - connect.php, TBS/OpenTBS (via tbs/), uploads/.
  - Campuran berat:
    - Business logic izin + generation docx + HTML form + CSS + JS dalam satu file.

- overview.php dan overview_enhanced.php
  - Fungsi utama:
    - Dashboard analitik, AI-powered insights.
    - Query kompleks + call API eksternal + lihat tampilan grafis.
  - Campuran berat:
    - Mengandung logic analytics + panggilan API + tampilan.

- shift_management.php
  - Lihat di atas: admin tool + tabel + JS API.

- Jadwal dan kalender:
  - jadwal_shift.php, kalender.php, kalender_fixed.php, shift_calendar.php, shift_management_quick_reference.html (non-PHP), dsb:
    - Umumnya: query shift + render view + include JS DayPilot, dsb.
    - Campuran: logic + view.

- Lain-lain:
  - profile.php
  - slip_gaji_management.php
  - slipgaji.php
  - rekap_absensi.php, rekapabsen.php
  - approve.php, approve_lembur.php, konfirmasi_lembur.php
  - upload_foto.php
  - posisis_jabatan.php
  - view_user.php, view_absensi.php (jika ada)
  - mayoritas: campuran (akses DB + HTML tabel/form).

D. File legacy/test/backup/debug

Berdasarkan struktur dan penamaan (dan sebagian sudah dihapus):

- Test/Debug (tidak untuk produksi, aman diarsipkan/di luar root):
  - [`test_absensi_validasi.php`](test_absensi_validasi.php:1)
  - [`test_api_integration.php`](test_api_integration.php:1)
  - [`test_debug_absensi.php`](test_debug_absensi.php:1)
  - [`test_enum_fix_final.php`](test_enum_fix_final.php:1)
  - [`test_final_suratizin_system.php`](test_final_suratizin_system.php:1)
  - [`test_hybrid_integration.php`](test_hybrid_integration.php:1)
  - [`test_opentbs_fixes.php`](test_opentbs_fixes.php:1)
  - [`test_php_errors_fix.php`](test_php_errors_fix.php:1)
  - [`test_shift_dropdown_fix.php`](test_shift_dropdown_fix.php:1)
  - [`test_simple_enum.php`](test_simple_enum.php:1)
  - [`test_status_lokasi_fix.php`](test_status_lokasi_fix.php:1)
  - [`test_telegram_constant_fix.php`](test_telegram_constant_fix.php:1)
  - [`test_upload_dokumen_medis.php`](test_upload_dokumen_medis.php:1)
  - Fungsi: QA, proof-of-fix.
  - Rekomendasi: Pindahkan ke direktori tests/ atau tools/, jangan di-deploy ke environment produksi.

- Debug / Maintenance spesifik:
  - [`debug_data_structure.php`](debug_data_structure.php:1)
  - [`debug_import_whitelist.php`](debug_import_whitelist.php:1)
  - [`check_enum_values.php`](check_enum_values.php:1)
  - [`check_status_lokasi.php`](check_status_lokasi.php:1)
  - [`final_enum_test.php`](final_enum_test.php:1)
  - Rekomendasi: Arsipkan atau pindahkan ke tools/, bukan public root.

- Backup:
  - [`shift_calendar_backup_20251111.php`](shift_calendar_backup_20251111.php:1)
    - Snapshot logic shift_calendar lama.
    - Tidak direferensikan.
    - Direkomendasikan diarsipkan di folder backup/ atau dihapus jika sudah confident.

- Lain:
  - Direktori FINAL_ADVANCED_CAL, FRONTEND_INTEGR, ultimate:
    - Kemungkinan berisi prototype/arsip.
    - Perlu review isi; jika hanya POC/legacy: pindahkan ke archive/ atau hapus.

3. Struktur Styling dan CSS

A. Sumber CSS utama

- assets/css/style_modern.css
- assets/css/style.css
- assets/css/style3.css
- assets/css/style_calendar.css
- assets/css/style_jadwal_shift.css
- assets/css/form_input_fixes.css

Beberapa file PHP masih merujuk ke “style_modern.css” langsung dari root (misalnya mainpage.php, overview.php) bukannya “assets/css/style_modern.css”. Ini indikasi:
- Duplikasi / inkonsistensi path assets.
- Potensi file CSS lama di root (cek dan konsolidasikan).

B. Inline dan embedded style

Banyak file mencampur CSS di dalam file PHP:

- navbar.php: <style> untuk dropdown admin panel.
- absen.php: inline style untuk container, status box, modal lembur.
- mainpage.php: <style> besar untuk dashboard grid, card, wizard.
- overview.php: <style> panjang untuk card, grid, AI sections.
- overview_enhanced.php, shift_management.php, suratizin.php: pola serupa.

Pola:
- “File menyatu”: PHP + HTML + CSS + JS dalam satu berkas menyebabkan:
  - Sulit reuse dan maintain.
  - Risiko konflik style antar halaman, terutama class generic (misalnya .container, .header, .btn, dll) yang didefinisikan berbeda di beberapa file.

C. Class CSS lintas file dan potensi konflik

Dari pattern yang muncul:

- Class umum yang lazim dan boleh reuse:
  - .headercontainer, .main-title, .subtitle-container, .content-container, .btn, .footer-container, .footer-text.
  - Jika didefinisikan konsisten di satu sumber CSS global (style_modern.css), aman.

- Class berpotensi konflik:
  - .container
  - .header
  - .btn, .btn-primary, .btn-secondary (mungkin didefinisikan berbeda di beberapa file).
  - .stat-card, .dashboard-grid, .table, dsb, yang dideklarasikan ulang dengan variasi gaya.
  - Karena CSS menyebar di inline/embedded, nama class yang sama bisa override antar halaman.

- Pola masalah:
  - Banyak inline style style="..." untuk box, warna, layout.
  - Banyak embedded <style> di tiap halaman mendefinisikan ulang komponen yang seharusnya global.

Rekomendasi teknis CSS dirangkum di bagian rekomendasi.

4. Analisis Dependensi dan Alur Utama

A. Flow utama aplikasi

Alur utama yang dapat dipetakan:

1) Autentikasi
- index.php:
  - Tampilkan login dan registrasi (dengan whitelist).
  - Arahkan login ke login.php (POST).
- login.php:
  - Validasi kredensial dengan DB.
  - Gunakan security_helper.php (rate-limit, CSRF, dsb).
  - Jika sukses → redirect ke mainpage.php.
- reset_password.php, forgot_password.php:
  - Flow pemulihan password (gunakan email helper, PHPMailer).

2) Dashboard setelah login
- mainpage.php:
  - Entry utama setelah login.
  - Tampilkan statistik absensi personal, wizard setup akun, integrasi Telegram.
  - Untuk admin: quick stats & link ke modul-modul admin.

3) Modul Absensi
- absen.php:
  - UI untuk absen menggunakan kamera + lokasi.
  - include absen_helper.php untuk validateAbsensiConditions.
  - Submit ke proses_absensi.php.
- proses_absensi.php:
  - Handle logika insert/update absensi, validasi lokasi/shift.

4) Modul Izin/Sakit
- suratizin.php:
  - Form pengajuan izin/sakit + generate dokumen via OpenTBS.
  - Terintegrasi dengan pengajuan_izin & uploads.
- approve.php / proses_approve.php:
  - Admin meng-approve/menolak, update pengajuan_izin, generate absensi otomatis, kirim email.

5) Modul Slip Gaji
- slipgaji.php:
  - User view slip gaji.
- slip_gaji_management.php:
  - Admin kelola slip gaji.
- auto_generate_slipgaji.php, generate_slip.php:
  - Proses backend/cron generate slip.

6) Modul Shift & Kalender
- kalender.php, kalender_fixed.php, shift_calendar.php:
  - Tampilkan kalender shift.
- shift_management.php:
  - Admin assign shift secara tabel.
- api_shift_calendar.php:
  - Backend untuk CRUD shift via AJAX.
- ShiftConfigurationManager.php:
  - Logic konfigurasi shift.

7) Modul Rekap & Laporan
- rekapabsen.php, rekap_absensi.php:
  - Rekap absensi, laporan.
- export_absensi.php:
  - Export CSV/XLSX menggunakan data rekap.

8) Modul Telegram
- telegram_helper.php:
  - Utilitas kirim pesan.
- telegram_webhook.php:
  - Endpoint pesan masuk dari bot.
- set_telegram_webhook.php:
  - Skrip set webhook; dipanggil manual.

9) Modul Profil & Manajemen User
- profile.php:
  - Edit profil, upload foto/ttd.
- edit_user.php, delete_user.php:
  - Manajemen user oleh admin.
- view_user.php (jika ada di repo):
  - List user untuk admin.

B. Dependensi umum

- Hampir semua file:
  - session_start();
  - include 'connect.php';
- Banyak modul admin:
  - require/functions_role.php untuk cek role.

C. File dengan logic kritis bercampur view

Contoh dominan:
- index.php, mainpage.php, suratizin.php, overview.php, overview_enhanced.php, shift_management.php, rekap_absensi.php, dsb:
  - Query database langsung di file yang sama dengan HTML.
  - Validasi otorisasi + rendering UI bercampur.

Konsekuensi:
- Sulit menguji unit.
- Sulit memisahkan concerns.
- Risiko keamanan jika validasi tidak konsisten saat copy-paste.

5. Daftar File Legacy/Test/Backup/Debug yang Disarankan Diarsipkan/Hapus

Berdasarkan pola nama, konten, dan konteks:

- Sudah dihapus (sesuai instruksi):
  - suratizin_backup.php
  - suratizin_backup_20251110_204305.php
  - test_foto_display_fix.php
  - logger.php
  - log_viewer.php

- Direkomendasikan pindah ke folder tests/ atau tools/ (bukan public root):
  - test_absensi_validasi.php
  - test_api_integration.php
  - test_debug_absensi.php
  - test_enum_fix_final.php
  - test_final_suratizin_system.php
  - test_hybrid_integration.php
  - test_opentbs_fixes.php
  - test_php_errors_fix.php
  - test_shift_dropdown_fix.php
  - test_simple_enum.php
  - test_status_lokasi_fix.php
  - test_telegram_constant_fix.php
  - test_upload_dokumen_medis.php
  - debug_data_structure.php
  - debug_import_whitelist.php
  - check_enum_values.php
  - check_status_lokasi.php
  - final_enum_test.php

- Direkomendasikan arsip/backup:
  - shift_calendar_backup_20251111.php
  - Folder FINAL_ADVANCED_CAL, FRONTEND_INTEGR, ultimate (perlu cek, namun indikasi POC/legacy).
  - File .sql, .zip deployment bundle: tetap, tapi dipindahkan ke folder docs/backup/ agar tidak tercampur dengan runtime.

6. Rekomendasi Praktis dan Konkret

A. Pemisahan Frontend–Backend

1) Target arsitektur direktori (high-level):

- public/
  - index.php (bootstrap, minimal)
  - assets/ (css, js, images)
- app/
  - Http/
    - Controllers/ (LoginController.php, AbsensiController.php, IzinController.php, ShiftController.php, dll)
    - Api/ (ShiftCalendarApi.php, KalenderApi.php, dsb)
  - Models/ (Register.php, Absensi.php, PengajuanIzin.php, ShiftAssignment.php, dll)
  - Services/ (TelegramService.php, SlipGajiService.php, IzinService.php, dsb)
  - Helpers/ (security_helper.php, absen_helper.php refactor, functions_role.php refactor)
- views/
  - auth/ (login.php, register.php)
  - dashboard/ (main.php, overview.php)
  - absensi/
  - izin/
  - shift/
  - slipgaji/
  - partials/ (navbar.php, footer.php, layout.php)
- scripts/ or tools/
  - test_*.php, debug_*.php, migrate_*.php, fix_*.php, shift_calendar_backup_*.php, dll.

2) Langkah teknis spesifik:

- Pisahkan logic dari file campuran:
  - index.php:
    - Pindahkan logika registrasi ke controller (AuthController), gunakan view terpisah untuk form.
  - mainpage.php:
    - Pindahkan perhitungan statistik ke DashboardService/Controller.
  - suratizin.php:
    - Pindahkan processIzinSubmission dan generateIzinDocument ke IzinService.
  - overview.php / overview_enhanced.php:
    - Pindahkan AI call & query ke service terpisah, view hanya render data.
  - shift_management.php:
    - Gunakan controller + API (sudah ada api_shift_calendar.php; perkuat konsistensi).

- Standarisasi include:
  - Semua access DB hanya via satu bootstrap (config + PDO).
  - Semua require helper dilakukan di controller, bukan di view.

B. Standardisasi CSS

1) Konsolidasi stylesheet:

- Gabungkan style utama ke:
  - assets/css/style_modern.css (utama).
  - assets/css/style_admin.css (khusus admin/dashboard).
  - assets/css/style_calendar.css (khusus kalender/shift).
- Pindahkan semua <style> inline di:
  - navbar.php, mainpage.php, overview.php, overview_enhanced.php, shift_management.php, absen.php, suratizin.php, dll
  - ke file CSS terpisah yang logis.

2) Penamaan class:

- Gunakan pola BEM atau setidaknya prefix by domain:
  - .nav-*, .dashboard-*, .absen-*, .izin-*, .shift-* dst.
- Hindari class generik yang konflik:
  - Ganti definisi khusus:
    - .container → .dashboard-container, .shift-container, dll.
    - .header → .page-header, dst.
- Audit class lintas file:
  - Pastikan .stat-card, .dashboard-grid, .btn, dll didefinisikan sekali secara global (jika benar-benar shared).
  - Jika style berbeda per halaman, gunakan namespace: .overview-stat-card, .mainpage-stat-card.

3) Kurangi inline style:

- Hapus style="..." kecuali untuk kasus sangat khusus.
- Pindahkan styling ke CSS:
  - Box informasi, alert, modal, dsb.

C. Penataan Ulang Struktur Direktori

Rekomendasi konkret:

- Buat struktur seperti:
  - /public:
    - index.php (router/front-controller minimal).
    - assets/ (pindah dari root).
  - /app:
    - helpers, services, controllers.
  - /views:
    - pisahkan tampilan.
  - /tools:
    - test_*.php, debug_*.php, migrasi, fix_*, backup lama.

- Ubah akses:
  - Web server document root → public/.
  - File PHP lain tidak dapat diakses langsung via URL (meningkatkan keamanan).

D. Rekomendasi tambahan terkait file legacy/test

- Semua test_*.php dan debug_*.php:
  - Jangan deploy ke production root.
  - Jika masih perlu:
    - Pindah ke /tools atau /tests.
    - Require login admin + protection tambahan jika ingin accessible via web.
- shift_calendar_backup_*.php dan folder POC:
  - Arsipkan di /archive atau di repo terpisah.

Ringkasan

Dokumentasi ini memetakan:

- File backend murni: helper, API, proses_*, konfigurasi, migrasi.
- File frontend-campuran: halaman utama, dashboard, absensi, izin, overview, shift management, dll yang menggabungkan query + HTML + CSS + JS.
- File legacy/test/debug: jelas dipisahkan sebagai kandidat arsip.
- Struktur CSS: saat ini tersebar dan berpotensi konflik; direkomendasikan konsolidasi dan standardisasi.
- Alur utama: index/login → mainpage → modul (absensi, izin, lembur, slip gaji, shift, rekap, Telegram) sudah terpetakan dengan dependensi utama.

Dokumen ini dapat langsung dijadikan referensi:
- Untuk refactoring bertahap ke arsitektur MVC yang rapi.
- Untuk membersihkan file legacy/test dari lingkungan produksi.
- Untuk standardisasi styling dan pengurangan code smell akibat file campuran logic + view.