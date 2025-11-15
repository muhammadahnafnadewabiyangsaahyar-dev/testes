<?php
session_start();

// PERBAIKAN: Gunakan operator AND (&&) dan periksa role yang valid
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'superadmin')) {
    header('Location: index.php?error=unauthorized_access');
    exit;
}

// Opsional: Tambahkan logging untuk audit trail
// error_log("Unauthorized access attempt to kalender.php by role: " . ($_SESSION['role'] ?? 'none'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalender Manajemen Shift Karyawan</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php
    include 'navbar.php';
    ?>
    <h1 style="text-align: center; color: #333; margin-bottom: 30px;">Kalender Manajemen Shift Karyawan</h1>
    <div id="controls">
        <div id="view-controls">
            <button id="view-day" class="view-btn">Day</button>
            <button id="view-week" class="view-btn">Week</button>
            <button id="view-month" class="view-btn active">Month</button>
            <button id="view-year" class="view-btn">Year</button>
        </div>

        <label for="cabang-select">Pilih Cabang:</label>
        <select id="cabang-select">
            <option value="">-- Pilih Cabang --</option>
        </select>

        <button id="shift-management-link" onclick="window.location.href='shift_management.php'" style="background-color: #2196F3; color: white; font-weight: bold; margin-right: 10px;">
            📋 Kelola Shift (Tabel)
        </button>
        <button id="export-schedule">Ekspor Jadwal (CSV)</button>
        <button id="backup-data">Backup Data</button>
        <button id="restore-data">Restore Data</button>
        <button id="toggle-summary">Tampilkan Ringkasan</button>
    </div>
    <div id="navigation">
        <button id="prev-nav">< <span id="prev-label">Bulan Sebelumnya</span></button>
        <span id="current-nav"></span>
        <button id="next-nav"><span id="next-label">Bulan Berikutnya</span> ></button>
    </div>
    <div id="calendar-view">
        <div id="month-view" class="view-container">
            <table id="calendar">
                <thead>
                    <tr>
                        <th>Minggu</th>
                        <th>Senin</th>
                        <th>Selasa</th>
                        <th>Rabu</th>
                        <th>Kamis</th>
                        <th>Jumat</th>
                        <th>Sabtu</th>
                    </tr>
                </thead>
                <tbody id="calendar-body">
                    <!-- Kalender akan dihasilkan di sini -->
                </tbody>
            </table>
        </div>
        <div id="week-view" class="view-container" style="display: none;">
            <!-- <span id="week-range" style="display: none;"></span> REMOVED: Redundant with current-nav -->
            <div id="week-calendar">
                <div id="time-column">
                    <!-- Jam akan diisi oleh JS -->
                </div>
                <div id="days-column">
                    <!-- Hari-hari dalam minggu akan diisi oleh JS -->
                </div>
            </div>
        </div>
        <div id="day-view" class="view-container" style="display: none;">
            <!-- <span id="day-date" style="display: none;"></span> REMOVED: Redundant with current-nav -->
            <div id="day-calendar">
                <div id="day-time-column">
                    <!-- Jam akan diisi oleh JS -->
                </div>
                <div id="day-content">
                    <!-- Konten hari akan diisi oleh JS -->
                </div>
            </div>
        </div>
        <div id="year-view" class="view-container" style="display: none;">
            <div id="year-grid">
                <!-- Bulan-bulan akan diisi oleh JS -->
            </div>
        </div>
    </div>

    <!-- Modal untuk menetapkan shift -->
    <div id="shift-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Tetapkan Shift untuk <span id="modal-date"></span></h2>
            <p>Karyawan: <span id="modal-employee"></span></p>
            <label for="modal-shift">Shift:</label>
            <select id="modal-shift">
                <option value="pagi">Shift Pagi</option>
                <option value="siang">Shift Siang</option>
                <option value="malam">Shift Malam</option>
                <option value="off">Off</option>
            </select>
            <button id="save-shift">Simpan</button>
        </div>
    </div>
    
    <!-- Modal untuk assign shift di Day View -->
    <div id="day-assign-modal" class="modal">
        <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
            <span class="close-day-assign">&times;</span>
            <h2>Assign Shift - <span id="day-modal-date"></span></h2>
            <p style="color: #666; margin-bottom: 20px;">Cabang: <span id="day-modal-cabang"></span></p>
            
            <!-- FIXED: Add shift selector in modal -->
            <div style="margin-bottom: 20px; padding: 15px; background-color: #f0f8ff; border-radius: 8px; border-left: 4px solid #2196F3;">
                <label for="day-modal-shift-select" style="font-weight: bold; display: block; margin-bottom: 8px;">
                    Pilih Shift: <span style="color: red;">*</span>
                </label>
                <select id="day-modal-shift-select" style="width: 100%; padding: 10px; border: 2px solid #2196F3; border-radius: 4px; font-size: 14px;">
                    <option value="">-- Pilih Shift --</option>
                </select>
                <small style="color: #666; display: block; margin-top: 5px;">
                    ℹ️ Shift yang dipilih akan di-assign ke pegawai yang dipilih di bawah
                </small>
            </div>
            
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <label style="font-weight: bold; margin: 0;">Pilih Pegawai:</label>
                    <div style="display: flex; gap: 5px;">
                        <button id="select-all-pegawai" style="background-color: #2196F3; color: white; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                            ✓ Pilih Semua
                        </button>
                        <button id="deselect-all-pegawai" style="background-color: #f44336; color: white; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                            ✗ Batal Semua
                        </button>
                    </div>
                </div>
                <input type="text" id="search-pegawai" placeholder="🔍 Cari nama pegawai..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px;">
                <div id="pegawai-cards-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; max-height: 400px; overflow-y: auto; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background-color: #f9f9f9;">
                    <!-- Pegawai cards akan diisi oleh JavaScript -->
                </div>
                <p id="selected-count" style="margin-top: 10px; font-size: 14px; color: #666;">Terpilih: <strong>0</strong> pegawai</p>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button id="save-day-shift" style="flex: 1; background-color: #4CAF50; color: white; padding: 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px;">
                    💾 Simpan Shift (<span id="save-count">0</span> pegawai)
                </button>
                <button id="cancel-day-shift" style="flex: 1; background-color: #f44336; color: white; padding: 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px;">
                    ❌ Batal
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal untuk delete shift di Day View -->
    <!-- Modal untuk delete shift di Day View -->
    <div id="day-delete-modal" class="modal">
        <div class="modal-content" style="max-width: 700px; max-height: 90vh; overflow-y: auto;">
            <span class="close-day-delete">&times;</span>
            <h2 class="modal-title" style="color: #f44336;">🗑️ Hapus Shift</h2>
            
            <!-- Shift info will be populated by JS -->
            <div class="modal-shift-info">
                <!-- Dynamic content -->
            </div>
            
            <!-- Employee list will be populated by JS -->
            <div class="modal-employee-list">
                <!-- Dynamic content -->
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button id="confirm-delete-shift" style="flex: 1; background-color: #f44336; color: white; padding: 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px;">
                    🗑️ Hapus Shift yang Dipilih
                </button>
                <button id="cancel-delete-shift" style="flex: 1; background-color: #757575; color: white; padding: 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px;">
                    ❌ Batal
                </button>
            </div>
        </div>
    </div>
    
    <div id="summary-tables" style="display: none;">
        <h2>Ringkasan Shift Karyawan</h2>
        
        <!-- Navigation controls for summary -->
        <div id="summary-navigation" style="display: flex; align-items: center; justify-content: center; gap: 15px; margin-bottom: 20px; padding: 15px; background-color: #f5f5f5; border-radius: 8px;">
            <button id="summary-prev" class="nav-btn" style="padding: 10px 20px; background-color: #2196F3; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;">
                <span id="summary-prev-label">◀ Sebelumnya</span>
            </button>
            <span id="summary-current-nav" style="font-weight: bold; font-size: 16px; min-width: 200px; text-align: center; color: #333;">-</span>
            <button id="summary-next" class="nav-btn" style="padding: 10px 20px; background-color: #2196F3; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;">
                <span id="summary-next-label">Berikutnya ▶</span>
            </button>
        </div>
        
        <div style="background-color: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2196F3;">
            <strong id="current-summary" style="display: block; font-size: 16px; color: #1976D2;">Loading...</strong>
            <small style="color: #666; display: block; margin-top: 5px;">💡 Ringkasan ini menampilkan data sesuai dengan view dan tanggal yang dipilih di kalender</small>
        </div>
        <div id="summary-controls">
            <label for="summary-filter">Filter Nama:</label>
            <input type="text" id="summary-filter" placeholder="Cari karyawan...">
            <button id="download-summary">Download Ringkasan</button>
            <select id="download-format">
                <option value="csv">CSV</option>
                <option value="txt">TXT</option>
            </select>
        </div>
        <button id="hide-summary">Sembunyikan Ringkasan</button>
        
        <h3 style="margin-top: 30px; color: #333;">📊 Ringkasan per Karyawan</h3>
        <table id="employee-summary">
            <thead>
                <tr>
                    <th>Karyawan</th>
                    <th>Jumlah Shift</th>
                    <th>Jumlah Jam Kerja</th>
                    <th>Hari Kerja</th>
                    <th>Hari Libur</th>
                </tr>
            </thead>
            <tbody id="employee-summary-body">
                <!-- Data akan diisi oleh JS -->
            </tbody>
        </table>
        
        <!-- Notifier untuk pegawai dengan shift kurang dari 26 hari -->
        <div id="under-minimum-notification" style="margin-top: 30px; padding: 20px; background-color: #fff3e0; border-radius: 8px; border-left: 4px solid #ff9800; display: none;">
            <h3 style="color: #e65100; margin-top: 0;">⚠️ Perhatian: Pegawai Belum Memenuhi Minimum Shift</h3>
            <p style="color: #666; margin-bottom: 15px;">
                Berikut adalah daftar pegawai yang memiliki kurang dari <strong>26 hari shift</strong> dalam periode ini:
            </p>
            <table id="under-minimum-table" style="width: 100%; background-color: white; border-radius: 4px;">
                <thead>
                    <tr style="background-color: #ff9800; color: white;">
                        <th style="padding: 12px; text-align: left;">Karyawan</th>
                        <th style="padding: 12px; text-align: center;">Jumlah Shift</th>
                        <th style="padding: 12px; text-align: center;">Kekurangan</th>
                        <th style="padding: 12px; text-align: center;">Persentase</th>
                    </tr>
                </thead>
                <tbody id="under-minimum-body">
                    <!-- Data akan diisi oleh JS -->
                </tbody>
            </table>
        </div>
        
        <h3 style="margin-top: 30px; color: #333;">📅 Ringkasan per Shift</h3>
        <table id="shift-summary">
            <thead>
                <tr>
                    <th>Shift</th>
                    <th>Jumlah Penugasan</th>
                </tr>
            </thead>
            <tbody id="shift-summary-body">
                <!-- Data akan diisi oleh JS -->
            </tbody>
        </table>
    </div>

    <!-- Modular Kalender Scripts - Load in dependency order -->
    <script src="script_kalender_utils.js"></script>
    <script src="script_kalender_api.js"></script>
    <script src="script_kalender_summary.js"></script>
    <script src="script_kalender_assign.js"></script>
    <script src="script_kalender_delete.js"></script>
    <script src="script_kalender_izin_sakit.js"></script>
    <script src="script_kalender_core.js"></script>
</body>
</html>