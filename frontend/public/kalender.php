<?php
session_start();
// PERBAIKAN: Gunakan operator AND (&&) dan periksa role yang valid
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'superadmin')) {
    header('Location: index.php?error=unauthorized_access');
    exit;

// Opsional: Tambahkan logging untuk audit trail
// error_log("Unauthorized access attempt to kalender.php by role: " . ($_SESSION['role'] ?? 'none'));
}
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
                <option value="">-- Loading shift options... --</option>
                <!-- Dynamic shift options will be loaded here -->
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
                    <option value="">-- Loading shift options... --</option>
                    <!-- Dynamic shift options will be loaded here -->
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

    <!-- Hybrid Calendar Integration Bridge -->
    <script src="hybrid-calendar-bridge.js"></script>
    
    <!-- Enhanced Modern Script with Fallback -->
    <script>
        // Enhanced Modern Calendar System dengan fallback support
        class ModernCalendarWithFallback {
            constructor() {
                this.shiftTemplates = [];
                this.isInitialized = false;
            }
            
            async init() {
                if (this.isInitialized) return;
                
                console.log('🚀 Initializing Enhanced Modern Calendar System...');
                
                try {
                    // Load shift templates dynamically
                    await this.loadDynamicShiftTemplates();
                    
                    // Update UI dengan dynamic data
                    this.updateDynamicUI();
                    
                    // Initialize enhanced features
                    this.initializeEnhancedFeatures();
                    
                    this.isInitialized = true;
                    console.log('✅ Enhanced Modern Calendar System Initialized');
                    
                } catch (error) {
                    console.error('❌ Error initializing enhanced calendar:', error);
                    console.log('🔄 Falling back to hybrid system...');
                }
            }
            
            async loadDynamicShiftTemplates() {
                try {
                    const response = await fetch('api_v2_test.php');
                    const result = await response.json();
                    
                    if (result.status === 'success' && result.data) {
                        this.shiftTemplates = result.data;
                        console.log('✅ Loaded', this.shiftTemplates.length, 'shift templates');
                        return this.shiftTemplates;
                    } else {
                        throw new Error('No shift templates received');
                    }
                } catch (error) {
                    console.error('❌ Failed to load shift templates:', error);
                    // Continue dengan fallback
                    this.shiftTemplates = [];
                    return [];
                }
            }
            
            updateDynamicUI() {
                // Update all shift dropdowns
                this.updateShiftDropdowns();
                
                // Update calendar with dynamic styling
                this.applyDynamicStyles();
                
                // Enhance existing functionality
                this.enhanceExistingFunctionality();
            }
            
            updateShiftDropdowns() {
                const dropdowns = ['modal-shift', 'day-modal-shift-select'];
                
                dropdowns.forEach(dropdownId => {
                    const dropdown = document.getElementById(dropdownId);
                    if (!dropdown) return;
                    
                    // Clear existing options except placeholder
                    const placeholder = dropdown.querySelector('option[value=""]');
                    dropdown.innerHTML = '';
                    
                    if (placeholder) {
                        dropdown.appendChild(placeholder.cloneNode());
                    }
                    
                    // Add dynamic options
                    if (this.shiftTemplates.length > 0) {
                        this.shiftTemplates.forEach(template => {
                            const option = document.createElement('option');
                            option.value = template.name;
                            option.textContent = `${template.display_name} (${template.start_time.slice(0,5)}-${template.end_time.slice(0,5)})`;
                            option.dataset.color = template.color_hex;
                            option.dataset.icon = template.icon_emoji;
                            option.dataset.id = template.id;
                            dropdown.appendChild(option);
                        });
                    } else {
                        // Fallback options
                        const fallbacks = [
                            { name: 'pagi', display_name: 'Shift Pagi', start_time: '07:00:00', end_time: '15:00:00' },
                            { name: 'middle', display_name: 'Shift Middle', start_time: '13:00:00', end_time: '21:00:00' },
                            { name: 'sore', display_name: 'Shift Sore', start_time: '15:00:00', end_time: '23:00:00' },
                            { name: 'off', display_name: 'Off', start_time: '00:00:00', end_time: '00:00:00' }
                        ];
                        
                        fallbacks.forEach(shift => {
                            const option = document.createElement('option');
                            option.value = shift.name;
                            option.textContent = `${shift.display_name} (${shift.start_time.slice(0,5)}-${shift.end_time.slice(0,5)})`;
                            dropdown.appendChild(option);
                        });
                    }
                });
                
                console.log('✅ Shift dropdowns updated with', this.shiftTemplates.length, 'dynamic options');
            }
            
            applyDynamicStyles() {
                // Add CSS untuk dynamic shift styling
                if (!document.getElementById('dynamic-shifts-styles')) {
                    const style = document.createElement('style');
                    style.id = 'dynamic-shifts-styles';
                    style.textContent = `
                        .shift-option-morning { border-left: 3px solid #FF9800; }
                        .shift-option-afternoon { border-left: 3px solid #2196F3; }
                        .shift-option-evening { border-left: 3px solid #9C27B0; }
                        .shift-option-full-day { border-left: 3px solid #4CAF50; }
                        .shift-option-off { border-left: 3px solid #9E9E9E; }
                        
                        .hybrid-calendar-enhanced {
                            position: relative;
                        }
                        
                        .hybrid-calendar-enhanced::before {
                            content: "🔧 Hybrid Mode Active";
                            position: absolute;
                            top: -10px;
                            right: 10px;
                            background: #4CAF50;
                            color: white;
                            padding: 2px 8px;
                            border-radius: 10px;
                            font-size: 10px;
                            z-index: 1000;
                        }
                    `;
                    document.head.appendChild(style);
                }
                
                // Add enhancement class
                const calendarView = document.getElementById('calendar-view');
                if (calendarView) {
                    calendarView.classList.add('hybrid-calendar-enhanced');
                }
            }
            
            enhanceExistingFunctionality() {
                // Enhance branch selection
                this.enhanceBranchSelection();
                
                // Enhance shift assignment
                this.enhanceShiftAssignment();
                
                // Add modern features
                this.addModernFeatures();
            }
            
            enhanceBranchSelection() {
                const cabangSelect = document.getElementById('cabang-select');
                if (cabangSelect) {
                    // Add enhanced event listener
                    const originalHandler = cabangSelect.onchange;
                    cabangSelect.addEventListener('change', (e) => {
                        console.log('🏢 Enhanced branch selection:', e.target.value);
                        
                        // Notify both systems
                        if (window.HybridCalendarBridge) {
                            window.HybridCalendarBridge.notifyBranchChange(e.target.value);
                        }
                        
                        // Call original handler if exists
                        if (originalHandler) {
                            originalHandler.call(cabangSelect, e);
                        }
                    });
                }
            }
            
            enhanceShiftAssignment() {
                // Override shift modal if exists
                const modalShiftSelect = document.getElementById('modal-shift');
                if (modalShiftSelect) {
                    modalShiftSelect.addEventListener('change', (e) => {
                        const selectedOption = e.target.selectedOptions[0];
                        if (selectedOption && selectedOption.dataset.color) {
                            console.log('🎨 Enhanced shift selection:', selectedOption.textContent);
                            // Apply dynamic styling
                            e.target.style.borderColor = selectedOption.dataset.color;
                        }
                    });
                }
                
                // Enhance day modal shift selection
                const dayModalShiftSelect = document.getElementById('day-modal-shift-select');
                if (dayModalShiftSelect) {
                    dayModalShiftSelect.addEventListener('change', (e) => {
                        const selectedOption = e.target.selectedOptions[0];
                        if (selectedOption && selectedOption.dataset.color) {
                            e.target.style.borderColor = selectedOption.dataset.color;
                        }
                    });
                }
            }
            
            addModernFeatures() {
                // Add floating action button untuk modern features
                this.addFloatingActionButton();
                
                // Add keyboard shortcuts
                this.addKeyboardShortcuts();
                
                // Add performance monitoring
                this.monitorPerformance();
            }
            
            addFloatingActionButton() {
                const fab = document.createElement('button');
                fab.innerHTML = '⚙️';
                fab.style.cssText = `
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    width: 50px;
                    height: 50px;
                    border-radius: 50%;
                    background: #2196F3;
                    color: white;
                    border: none;
                    cursor: pointer;
                    font-size: 20px;
                    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                    z-index: 1000;
                `;
                
                fab.addEventListener('click', () => {
                    this.showSystemInfo();
                });
                
                fab.addEventListener('mouseenter', () => {
                    fab.style.background = '#1976D2';
                });
                
                fab.addEventListener('mouseleave', () => {
                    fab.style.background = '#2196F3';
                });
                
                document.body.appendChild(fab);
            }
            
            showSystemInfo() {
                const info = document.createElement('div');
                info.style.cssText = `
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background: white;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 8px 16px rgba(0,0,0,0.3);
                    z-index: 2000;
                    max-width: 400px;
                `;
                
                const systemStatus = `
                    <h3>🗺 Hybrid Calendar System Status</h3>
                    <p><strong>Shift Templates:</strong> ${this.shiftTemplates.length} loaded</p>
                    <p><strong>Legacy System:</strong> ${window.KalenderCore ? '✅ Active' : '❌ Not loaded'}</p>
                    <p><strong>Modern System:</strong> ${window.KalenderModernApp ? '✅ Active' : '❌ Not loaded'}</p>
                    <p><strong>API Status:</strong> ${this.shiftTemplates.length > 0 ? '✅ Connected' : '⚠️ Fallback mode'}</p>
                    <p><strong>Performance:</strong> ${this.getPerformanceInfo()}</p>
                    <button onclick="this.parentElement.remove()" style="margin-top: 10px; padding: 8px 16px; background: #2196F3; color: white; border: none; border-radius: 4px;">Close</button>
                `;
                
                info.innerHTML = systemStatus;
                document.body.appendChild(info);
                
                // Close when clicking outside
                info.addEventListener('click', (e) => {
                    if (e.target === info) {
                        info.remove();
                    }
                });
            }
            
            addKeyboardShortcuts() {
                document.addEventListener('keydown', (e) => {
                    if (e.ctrlKey) {
                        switch (e.key) {
                            case '1':
                                e.preventDefault();
                                document.getElementById('view-month').click();
                                break;
                            case '2':
                                e.preventDefault();
                                document.getElementById('view-week').click();
                                break;
                            case '3':
                                e.preventDefault();
                                document.getElementById('view-day').click();
                                break;
                            case 's':
                                e.preventDefault();
                                document.getElementById('export-schedule').click();
                                break;
                        }
                    }
                });
            }
            
            getPerformanceInfo() {
                if (performance && performance.timing) {
                    const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
                    return `${loadTime}ms load time`;
                }
                return 'Monitoring not available';
            }
            
            monitorPerformance() {
                // Monitor page performance
                setTimeout(() => {
                    if (performance) {
                        const perfData = performance.getEntriesByType('navigation')[0];
                        if (perfData) {
                            console.log('📊 Performance:', {
                                loadTime: `${perfData.loadEventEnd - perfData.loadEventStart}ms`,
                                domContentLoaded: `${perfData.domContentLoadedEventEnd - perfData.domContentLoadedEventStart}ms`,
                                totalTime: `${perfData.loadEventEnd - perfData.navigationStart}ms`
                            });
                        }
                    }
                }, 1000);
            }
        }
        
        // Initialize enhanced system
        document.addEventListener('DOMContentLoaded', async function() {
            console.log('🚀 Initializing Enhanced Hybrid Calendar System...');
            
            const modernSystem = new ModernCalendarWithFallback();
            await modernSystem.init();
            
            // Expose untuk debugging
            window.ModernCalendar = modernSystem;
        });
        
        // Utility functions untuk hybrid system
        window.HybridUtils = {
            // Test API connectivity
            testAPI: async function() {
                try {
                    const response = await fetch('api_v2_test.php');
                    const result = await response.json();
                    return result.status === 'success';
                } catch (error) {
                    return false;
                }
            },
            
            // Get system status
            getStatus: function() {
                return {
                    legacy: !!window.KalenderCore,
                    modern: !!window.KalenderModernApp,
                    bridge: !!window.HybridCalendarBridge,
                    api: window.ModernCalendar ? window.ModernCalendar.shiftTemplates.length : 0
                };
            },
            
            // Force reinitialization
            restart: function() {
                console.log('🔄 Restarting hybrid system...');
                location.reload();
            }
        };
    </script>
</body>
</html>
