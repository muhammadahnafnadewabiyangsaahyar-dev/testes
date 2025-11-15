// ============ HYBRID INTEGRATION BRIDGE ============
/**
 * Bridge untuk mengintegrasikan sistem kalender lama dengan sistem baru
 * Memungkinkan transisi smooth dari legacy ke modern architecture
 */

(function(window) {
    'use strict';
    
    const HybridCalendarBridge = {};
    
    // ============ COMPATIBILITY LAYER ============
    
    // Mock dependencies untuk script lama
    window.KalenderUtils = window.KalenderUtils || {
        monthNames: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'],
        formatDate: function(date) {
            return date.toISOString().split('T')[0];
        },
        formatTime: function(timeString) {
            return timeString ? timeString.substring(0, 5) : '00:00';
        },
        calculateDuration: function(jamMasuk, jamKeluar) {
            if (!jamMasuk || !jamKeluar) return 0;
            const start = parseInt(jamMasuk.split(':')[0]) + parseInt(jamMasuk.split(':')[1]) / 60;
            const end = parseInt(jamKeluar.split(':')[0]) + parseInt(jamKeluar.split(':')[1]) / 60;
            return end > start ? end - start : (end + 24) - start;
        },
        getShiftColor: function(shiftType) {
            const colors = {
                'pagi': { bg: '#fff3e0', border: '#ff9800', text: '#e65100' },
                'middle': { bg: '#e3f2fd', border: '#2196F3', text: '#0d47a1' },
                'sore': { bg: '#f3e5f5', border: '#9c27b0', text: '#4a148c' },
                'off': { bg: '#f5f5f5', border: '#9e9e9e', text: '#424242' }
            };
            return colors[shiftType?.toLowerCase()] || colors['middle'];
        },
        getShiftEmoji: function(shiftType) {
            const emojis = {
                'pagi': '🌅',
                'middle': '☀️',
                'sore': '🌆',
                'off': '🚫'
            };
            return emojis[shiftType?.toLowerCase()] || '📅';
        }
    };
    
    // Mock KalenderAPI untuk compatibility
    window.KalenderAPI = window.KalenderAPI || {
        loadCabangList: async function() {
            // Delegate ke modern system jika ada
            if (window.ModernShiftAPI) {
                return await new window.ModernShiftAPI().getBranches();
            }
            // Fallback ke legacy
            try {
                const response = await fetch('api_shift_calendar.php?action=get_cabang');
                const result = await response.json();
                return result.data || result.cabang || [];
            } catch (error) {
                console.warn('⚠️ Legacy API not available, using fallback');
                return [];
            }
        },
        
        loadShiftList: async function(cabangName) {
            // Delegate ke modern system jika ada
            if (window.ModernShiftAPI) {
                const modernAPI = new window.ModernShiftAPI();
                const templates = await modernAPI.getShiftTemplates();
                // Convert modern format ke legacy format
                return templates.map(template => ({
                    id: template.id,
                    nama_shift: template.name,
                    display_name: template.display_name,
                    jam_masuk: template.start_time,
                    jam_keluar: template.end_time
                }));
            }
            // Fallback ke legacy
            try {
                const response = await fetch(`api_shift_calendar.php?action=get_shifts&outlet=${encodeURIComponent(cabangName)}`);
                const result = await response.json();
                return result.data || result.shifts || [];
            } catch (error) {
                console.warn('⚠️ Legacy shift API not available');
                return [];
            }
        },
        
        loadShiftAssignments: async function(cabangId) {
            try {
                const response = await fetch(`api_shift_calendar.php?action=get_assignments&cabang_id=${cabangId}`);
                const result = await response.json();
                console.log('📦 Hybrid: Legacy assignments loaded:', result.data?.length || 0);
                
                if (result.status === 'success' && result.data) {
                    // Convert array ke object format untuk legacy compatibility
                    const assignments = {};
                    result.data.forEach(assignment => {
                        const key = `${assignment.tanggal_shift}-${assignment.user_id}`;
                        assignments[key] = {
                            ...assignment,
                            shift_date: assignment.tanggal_shift,
                            nama_lengkap: assignment.nama_lengkap,
                            nama_shift: assignment.nama_shift
                        };
                    });
                    return assignments;
                }
                return {};
            } catch (error) {
                console.warn('⚠️ Legacy assignment API not available:', error.message);
                return {};
            }
        },
        
        notifyEmployees: async function(date, cabangId) {
            // Fallback notification system
            console.log('📧 Notification requested for:', date, 'cabang:', cabangId);
            return { status: 'success', message: 'Notification system not available in hybrid mode' };
        }
    };
    
    // Mock KalenderAssign untuk compatibility
    window.KalenderAssign = window.KalenderAssign || {
        closeDayAssignModal: function() {
            const modal = document.getElementById('day-assign-modal');
            if (modal) modal.style.display = 'none';
        },
        
        saveDayShiftAssignment: async function(currentCabangId, shiftAssignments, reloadCallback) {
            alert('⚠️ Shift assignment in hybrid mode - use modern calendar interface');
            if (reloadCallback) await reloadCallback();
        },
        
        searchPegawai: function(searchTerm) {
            const cards = document.querySelectorAll('.pegawai-card');
            const term = searchTerm.toLowerCase().trim();
            
            cards.forEach(card => {
                const name = card.dataset.pegawaiName || '';
                card.style.display = name.includes(term) ? '' : 'none';
            });
        },
        
        openDayAssignModal: function() {
            alert('⚠️ Use the modern calendar interface for shift assignment');
        }
    };
    
    // Mock KalenderDelete untuk compatibility
    window.KalenderDelete = window.KalenderDelete || {
        closeDeleteModal: function() {
            const modal = document.getElementById('day-delete-modal');
            if (modal) modal.style.display = 'none';
        },
        
        confirmDelete: async function(reloadCallback) {
            if (confirm('⚠️ Delete functionality in hybrid mode')) {
                alert('Delete functionality available in modern interface');
                if (reloadCallback) await reloadCallback();
            }
        },
        
        openDeleteModal: function() {
            alert('⚠️ Use the modern calendar interface for deletion');
        }
    };
    
    // Mock KalenderSummary untuk compatibility
    window.KalenderSummary = window.KalenderSummary || {
        updateSummaryDisplay: function() {
            // Minimal implementation
            console.log('📊 Summary display - hybrid mode');
        },
        
        getDateRangeForCurrentView: function(view, date, month, year) {
            return { start: new Date(), end: new Date() };
        },
        
        getViewRangeName: function() {
            return 'Hybrid View';
        },
        
        calculateEmployeeSummary: function() {
            return [];
        },
        
        calculateShiftSummary: function() {
            return {};
        },
        
        filterSummaryByName: function() {
            // No-op in hybrid mode
        }
    };
    
    // ============ LOADING MANAGEMENT ============
    
    let isLegacyLoaded = false;
    let isModernLoaded = false;
    
    // Load legacy scripts dengan error handling
    async function loadLegacyScripts() {
        if (isLegacyLoaded) return;
        
        console.log('📚 Loading legacy calendar scripts...');
        
        const legacyScripts = [
            'script_kalender_utils.js',
            'script_kalender_api.js',
            'script_kalender_summary.js',
            'script_kalender_assign.js',
            'script_kalender_delete.js',
            'script_kalender_core.js'
        ];
        
        for (const scriptName of legacyScripts) {
            try {
                console.log(`⏳ Loading ${scriptName}...`);
                await loadScript(scriptName);
                console.log(`✅ Loaded ${scriptName}`);
            } catch (error) {
                console.warn(`⚠️ Failed to load ${scriptName}:`, error.message);
                // Continue loading other scripts
            }
        }
        
        // Initialize legacy system jika berhasil
        if (window.KalenderCore) {
            console.log('🚀 Initializing legacy calendar system...');
            try {
                window.KalenderCore.init();
                isLegacyLoaded = true;
                console.log('✅ Legacy calendar system initialized');
            } catch (error) {
                console.error('❌ Failed to initialize legacy system:', error);
            }
        } else {
            console.warn('⚠️ KalenderCore not available, skipping legacy initialization');
        }
    }
    
    // Load modern scripts
    async function loadModernScripts() {
        if (isModernLoaded) return;
        
        console.log('🚀 Loading modern calendar scripts...');
        
        const modernScripts = [
            'kalender-architecture-core.js',
            'kalender-modern-components-final.js'
        ];
        
        for (const scriptName of modernScripts) {
            try {
                console.log(`⏳ Loading ${scriptName}...`);
                await loadScript(scriptName);
                console.log(`✅ Loaded ${scriptName}`);
            } catch (error) {
                console.warn(`⚠️ Failed to load ${scriptName}:`, error.message);
            }
        }
        
        // Initialize modern system jika berhasil
        if (window.KalenderModernApp) {
            console.log('🚀 Initializing modern calendar system...');
            try {
                window.KalenderModernApp.init();
                isModernLoaded = true;
                console.log('✅ Modern calendar system initialized');
            } catch (error) {
                console.error('❌ Failed to initialize modern system:', error);
            }
        } else {
            console.warn('⚠️ KalenderModernApp not available, skipping modern initialization');
        }
    }
    
    // Helper function untuk load script
    function loadScript(src) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = () => reject(new Error(`Failed to load script: ${src}`));
            document.head.appendChild(script);
        });
    }
    
    // ============ INTEGRATION MANAGEMENT ============
    
    // Initialise hybrid system
    HybridCalendarBridge.init = async function() {
        console.log('🌉 Initializing Hybrid Calendar Bridge...');
        
        // Load modern scripts first
        await loadModernScripts();
        
        // Small delay, then load legacy
        setTimeout(async () => {
            await loadLegacyScripts();
            
            // Report final status
            console.log('📊 Hybrid System Status:');
            console.log(`   - Legacy System: ${isLegacyLoaded ? '✅ Loaded' : '❌ Not loaded'}`);
            console.log(`   - Modern System: ${isModernLoaded ? '✅ Loaded' : '❌ Not loaded'}`);
            
            // Add status indicator to UI
            showHybridStatus();
            
        }, 1000);
    };
    
    // Show status indicator
    function showHybridStatus() {
        const status = document.createElement('div');
        status.id = 'hybrid-calendar-status';
        status.style.cssText = `
            position: fixed;
            top: 10px;
            right: 10px;
            background: #333;
            color: white;
            padding: 10px;
            border-radius: 5px;
            font-size: 12px;
            z-index: 9999;
            opacity: 0.9;
        `;
        
        let message = '🔧 Hybrid Calendar Bridge Active<br>';
        message += isModernLoaded ? '✅ Modern System' : '❌ Modern System';
        message += isLegacyLoaded ? ' | ✅ Legacy System' : ' | ❌ Legacy System';
        
        status.innerHTML = message;
        document.body.appendChild(status);
        
        // Auto-hide after 10 seconds
        setTimeout(() => {
            if (status && status.parentNode) {
                status.style.transition = 'opacity 0.5s';
                status.style.opacity = '0';
                setTimeout(() => status.remove(), 500);
            }
        }, 10000);
    }
    
    // Public API
    window.HybridCalendarBridge = HybridCalendarBridge;
    
    // Auto-init when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        HybridCalendarBridge.init();
    });
    
    console.log('✅ Hybrid Calendar Bridge script loaded');
    
})(window);