// ============ KALENDER CORE MODULE ============
/**
 * Main calendar orchestration module
 * Handles: initialization, state management, view switching, calendar generation, navigation
 */
(function(window) {
    'use strict';
    
    const KalenderCore = {};
    
    // ============ STATE VARIABLES ============
    let currentCabangId = null;
    let currentCabangName = null;
    let pegawaiList = [];
    let shiftList = [];
    let shiftAssignments = {};
    let currentMonth = new Date().getMonth();
    let currentYear = new Date().getFullYear();
    let currentView = 'month';
    let currentDate = new Date();
    let holidays = [];
    
    // ============ INITIALIZATION ============
    KalenderCore.init = function() {
        console.log('Initializing Kalender Core...');
        document.addEventListener('DOMContentLoaded', async function() {
            console.log('DOM Loaded - Starting Kalender App');
            await initializeApp();
        });
    };
    
    async function initializeApp() {
        const cabangList = await window.KalenderAPI.loadCabangList();
        populateCabangDropdown(cabangList);
        setupAllEventListeners();
        generateCalendar(currentMonth, currentYear);
        updateNavigationLabels();
    }
    
    function populateCabangDropdown(cabangList) {
        const cabangSelect = document.getElementById('cabang-select');
        if (cabangSelect && cabangList) {
            cabangSelect.innerHTML = '<option value="">-- Pilih Cabang --</option>';
            cabangList.forEach(cabang => {
                const option = document.createElement('option');
                option.value = cabang.id;
                option.textContent = cabang.nama_outlet ||cabang.outlet || cabang.nama_cabang || cabang.name;
                cabangSelect.appendChild(option);
            });
        }
    }
    
    // ============ EVENT LISTENERS ============
    function setupAllEventListeners() {
        // View buttons
        document.getElementById('view-day')?.addEventListener('click', () => switchView('day'));
        document.getElementById('view-week')?.addEventListener('click', () => switchView('week'));
        document.getElementById('view-month')?.addEventListener('click', () => switchView('month'));
        document.getElementById('view-year')?.addEventListener('click', () => switchView('year'));
        
        // Navigation buttons
        document.getElementById('prev-nav')?.addEventListener('click', navigatePrevious);
        document.getElementById('next-nav')?.addEventListener('click', navigateNext);
        
        // Cabang selector
        document.getElementById('cabang-select')?.addEventListener('change', async function() {
            const cabangId = this.value || null;
            const cabangName = this.options[this.selectedIndex]?.text || null;
            
            currentCabangId = cabangId;
            currentCabangName = cabangName;
            
            console.log('🏢 Cabang selected:', { cabangId, cabangName });
            
            if (cabangId && cabangName) {
                console.log('📥 Loading shift list and assignments...');
                shiftList = await window.KalenderAPI.loadShiftList(cabangName);
                console.log('✅ Shift list loaded:', shiftList);
                console.log('📋 Shift list count:', shiftList.length);
                console.log('📝 Shift names:', shiftList.map(s => s.nama_shift));
                
                shiftAssignments = await window.KalenderAPI.loadShiftAssignments(cabangId);
                console.log('✅ Shift assignments loaded:', shiftAssignments);
                console.log('📊 Total assignments:', Object.keys(shiftAssignments).length);
                
                // Log unique shift types in assignments
                const uniqueShifts = [...new Set(Object.values(shiftAssignments).map(a => a.nama_shift))];
                console.log('🔍 Unique shift types in assignments:', uniqueShifts);
            }
            
            generateCalendar(currentMonth, currentYear);
        });
        
        // Modal close handlers
        document.querySelector('.close')?.addEventListener('click', () => {
            document.getElementById('shift-modal').style.display = 'none';
        });
        
        document.querySelector('.close-day-assign')?.addEventListener('click', () => {
            window.KalenderAssign.closeDayAssignModal();
        });
        
        document.getElementById('cancel-day-shift')?.addEventListener('click', () => {
            window.KalenderAssign.closeDayAssignModal();
        });
        
        document.getElementById('save-day-shift')?.addEventListener('click', async () => {
            await window.KalenderAssign.saveDayShiftAssignment(
                currentCabangId,
                shiftAssignments,
                async () => {
                    shiftAssignments = await window.KalenderAPI.loadShiftAssignments(currentCabangId);
                    generateCalendar(currentMonth, currentYear);
                }
            );
        });
        
        // Delete modal handlers
        document.querySelector('.close-day-delete')?.addEventListener('click', () => {
            window.KalenderDelete.closeDeleteModal();
        });
        
        document.getElementById('cancel-delete-shift')?.addEventListener('click', () => {
            window.KalenderDelete.closeDeleteModal();
        });
        
        document.getElementById('confirm-delete-shift')?.addEventListener('click', async () => {
            await window.KalenderDelete.confirmDelete(async () => {
                shiftAssignments = await window.KalenderAPI.loadShiftAssignments(currentCabangId);
                generateCalendar(currentMonth, currentYear);
            });
        });
        
        // Select/deselect all pegawai buttons
        document.getElementById('select-all-pegawai')?.addEventListener('click', function(e) {
            e.preventDefault();
            const checkboxes = document.querySelectorAll('.pegawai-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = true;
                const card = cb.closest('.pegawai-card');
                if (card) card.classList.add('selected');
            });
        });
        
        document.getElementById('deselect-all-pegawai')?.addEventListener('click', function(e) {
            e.preventDefault();
            const checkboxes = document.querySelectorAll('.pegawai-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = false;
                const card = cb.closest('.pegawai-card');
                if (card) card.classList.remove('selected');
            });
        });
        
        // Search pegawai functionality
        document.getElementById('search-pegawai')?.addEventListener('input', function(e) {
            window.KalenderAssign.searchPegawai(e.target.value);
        });
        
        // Feature buttons
        document.getElementById('export-schedule')?.addEventListener('click', exportSchedule);
        document.getElementById('backup-data')?.addEventListener('click', backupData);
        document.getElementById('restore-data')?.addEventListener('click', restoreData);
        document.getElementById('toggle-summary')?.addEventListener('click', toggleSummary);
        document.getElementById('hide-summary')?.addEventListener('click', hideSummary);
        document.getElementById('download-summary')?.addEventListener('click', downloadSummary);
        
        // Summary filter
        document.getElementById('summary-filter')?.addEventListener('input', () => {
            window.KalenderSummary.filterSummaryByName();
        });
        
        // Summary navigation buttons
        document.getElementById('summary-prev')?.addEventListener('click', navigateSummaryPrevious);
        document.getElementById('summary-next')?.addEventListener('click', navigateSummaryNext);
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const dayAssignModal = document.getElementById('day-assign-modal');
            if (event.target === dayAssignModal) {
                window.KalenderAssign.closeDayAssignModal();
            }
            
            const dayDeleteModal = document.getElementById('day-delete-modal');
            if (event.target === dayDeleteModal) {
                window.KalenderDelete.closeDeleteModal();
            }
        });
    }
    
    // ============ CALENDAR GENERATION ============
    function generateCalendar(month, year) {
        if (currentView === 'month') {
            generateMonthView(month, year);
        } else if (currentView === 'week') {
            generateWeekView(currentDate);
        } else if (currentView === 'day') {
            generateDayView(currentDate);
        } else if (currentView === 'year') {
            generateYearView(year);
        }
        updateNavigationLabels();
    }
    
    // NOTE: These view generation functions are LARGE (300-800 lines each)
    // They need to be copied from the original script_kalender_database.js
    // with modifications to use the module namespace
    
    function generateMonthView(month, year) {
        const calendarBody = document.getElementById('calendar-body');
        
        if (!calendarBody) return;
        
        // Hide other views first
        hideAllViews();
        const monthView = document.getElementById('month-view');
        if (monthView) monthView.style.display = 'block';
        
        calendarBody.innerHTML = '';
        
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        let date = 1;

        for (let i = 0; i < 6; i++) {
            const row = document.createElement('tr');
            
            for (let j = 0; j < 7; j++) {
                const cell = document.createElement('td');
                
                if (i === 0 && j < firstDay) {
                    cell.classList.add('empty');
                } else if (date > daysInMonth) {
                    cell.classList.add('empty');
                } else {
                    const dateDiv = document.createElement('div');
                    dateDiv.className = 'date-number';
                    dateDiv.textContent = date;
                    cell.appendChild(dateDiv);
                    
                    const today = new Date();
                    if (date === today.getDate() && 
                        month === today.getMonth() && 
                        year === today.getFullYear()) {
                        cell.classList.add('today');
                    }
                    
                    // Add click handler to switch to day view
                    const currentDateForCell = new Date(year, month, date);
                    cell.style.cursor = 'pointer';
                    cell.addEventListener('click', function() {
                        currentDate = currentDateForCell;
                        switchView('day');
                    });
                    
                    // Show shift assignments for this date
                    const dateStr = window.KalenderUtils.formatDate(new Date(year, month, date));
                    const shiftsForDate = Object.values(shiftAssignments).filter(
                        assignment => assignment.shift_date === dateStr
                    );
                    
                    if (shiftsForDate.length > 0) {
                        const shiftsDiv = document.createElement('div');
                        shiftsDiv.className = 'shifts-summary';
                        shiftsDiv.textContent = `${shiftsForDate.length} shift(s)`;
                        shiftsDiv.style.fontSize = '11px';
                        shiftsDiv.style.color = '#2196F3';
                        shiftsDiv.style.marginTop = '5px';
                        cell.appendChild(shiftsDiv);
                    }
                    
                    date++;
                }
                row.appendChild(cell);
            }
            
            calendarBody.appendChild(row);
            
            if (date > daysInMonth) break;
        }
        
        updateSummaries();
    }
    
    function generateWeekView(date) {
        hideAllViews();
        const weekView = document.getElementById('week-view');
        if (weekView) weekView.style.display = 'block';
        
        const weekCalendar = document.getElementById('week-calendar');
        if (!weekCalendar) return;
        
        // Calculate week start (Monday)
        const weekStart = new Date(date);
        const day = weekStart.getDay();
        const diff = weekStart.getDate() - day + (day === 0 ? -6 : 1);
        weekStart.setDate(diff);
        
        // Clear and rebuild
        const timeColumn = document.getElementById('time-column');
        const daysColumn = document.getElementById('days-column');
        
        if (timeColumn) timeColumn.style.display = 'none';
        
        if (daysColumn) {
            daysColumn.innerHTML = '';
            
            for (let i = 0; i < 7; i++) {
                const currentDay = new Date(weekStart);
                currentDay.setDate(weekStart.getDate() + i);
                const dateStr = window.KalenderUtils.formatDate(currentDay);
                
                const dayColumn = document.createElement('div');
                dayColumn.className = 'day-column';
                
                // const dayHeader = document.createElement('div'); // REMOVED: Redundant with current-nav
                // dayHeader.className = 'day-header'; // REMOVED: Redundant with current-nav
                // const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']; // REMOVED: Redundant with current-nav
                // dayHeader.textContent = `${dayNames[currentDay.getDay()]} ${currentDay.getDate()}`; // REMOVED: Redundant with current-nav
                // dayColumn.appendChild(dayHeader); // REMOVED: Redundant with current-nav
                
                const dayContent = document.createElement('div');
                dayContent.className = 'day-content';
                
                // Get shifts for this day
                const shiftsForDay = Object.values(shiftAssignments).filter(
                    assignment => assignment.shift_date === dateStr
                );
                
                console.log(`📅 Week view - ${dateStr}:`, shiftsForDay.length, 'shifts');
                if (shiftsForDay.length > 0) {
                    console.log('   Shifts:', shiftsForDay.map(s => s.nama_shift).join(', '));
                }
                
                if (shiftsForDay.length > 0) {
                    // Group shifts by jam_masuk and jam_keluar
                    const groupedShifts = {};
                    shiftsForDay.forEach(assignment => {
                        const key = `${assignment.jam_masuk}-${assignment.jam_keluar}-${assignment.nama_shift}`;
                        if (!groupedShifts[key]) {
                            groupedShifts[key] = {
                                nama_shift: assignment.nama_shift,
                                jam_masuk: assignment.jam_masuk,
                                jam_keluar: assignment.jam_keluar,
                                employees: []
                            };
                        }
                        groupedShifts[key].employees.push(assignment.nama_lengkap || 'Unknown');
                    });
                    
                    console.log('   Grouped into', Object.keys(groupedShifts).length, 'shift groups');
                    
                    // Display grouped shifts
                    Object.values(groupedShifts).forEach(group => {
                        const shiftColors = window.KalenderUtils.getShiftColor(group.nama_shift);
                        const shiftEmoji = window.KalenderUtils.getShiftEmoji(group.nama_shift);
                        
                        const shiftDiv = document.createElement('div');
                        shiftDiv.className = 'shift-item';
                        shiftDiv.style.cssText = `margin-bottom: 10px; padding: 10px; border-left: 4px solid ${shiftColors.border}; background-color: ${shiftColors.bg}; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);`;
                        shiftDiv.innerHTML = `
                            <div style="font-weight: bold; color: ${shiftColors.text}; margin-bottom: 4px; font-size: 13px;">
                                ${shiftEmoji} ${group.nama_shift || 'Shift'}
                            </div>
                            <div style="font-size: 11px; color: #666; margin-bottom: 6px;">
                                ⏰ ${window.KalenderUtils.formatTime(group.jam_masuk)} - ${window.KalenderUtils.formatTime(group.jam_keluar)}
                            </div>
                            <div style="font-size: 12px; color: #333;">
                                <strong>👥 ${group.employees.length} pegawai:</strong><br>
                                <span style="font-size: 11px;">${group.employees.join(', ')}</span>
                            </div>
                        `;
                        dayContent.appendChild(shiftDiv);
                    });
                } else {
                    const emptyDiv = document.createElement('div');
                    emptyDiv.style.cssText = 'padding: 20px; text-align: center; color: #999;';
                    emptyDiv.textContent = 'Tidak ada shift';
                    dayContent.appendChild(emptyDiv);
                }
                
                dayColumn.appendChild(dayContent);
                daysColumn.appendChild(dayColumn);
            }
        }
        
        updateSummaries();
    }
    
    function generateDayView(date) {
        hideAllViews();
        const dayView = document.getElementById('day-view');
        if (dayView) dayView.style.display = 'block';
        
        const dayContent = document.getElementById('day-content');
        if (!dayContent) return;
        
        const dateStr = window.KalenderUtils.formatDate(date);
        
        console.log('📅 Day view - Date:', dateStr);
        console.log('📦 Day view - shiftAssignments object:', shiftAssignments);
        console.log('📋 Day view - Total assignments in memory:', Object.keys(shiftAssignments || {}).length);
        
        // Collect all shifts for this day
        let dayShifts = [];
        if (shiftAssignments) {
            Object.keys(shiftAssignments).forEach(key => {
                const assignment = shiftAssignments[key];
                console.log('🔍 Checking assignment:', key, '→', assignment.shift_date, 'vs', dateStr);
                if (assignment.shift_date === dateStr) {
                    dayShifts.push(assignment);
                    console.log('✅ Match found!', assignment);
                }
            });
        }
        
        console.log(`📊 Day view - Found ${dayShifts.length} shifts for ${dateStr}`);
        
        const dayTimeColumn = document.getElementById('day-time-column');
        if (dayTimeColumn) dayTimeColumn.style.display = 'none';
        
        dayContent.innerHTML = '';
        
        // Add header
        // const contentHeader = document.createElement('div'); // REMOVED: Redundant with current-nav
        // contentHeader.className = 'day-header'; // REMOVED: Redundant with current-nav
        // const monthNames = window.KalenderUtils.monthNames; // REMOVED: Redundant with current-nav
        // contentHeader.textContent = `${date.getDate()} ${monthNames[date.getMonth()]} ${date.getFullYear()}`; // REMOVED: Redundant with current-nav
        // contentHeader.style.cssText = 'height: 50px; display: flex; align-items: center; justify-content: center; font-weight: bold; background-color: #f9f9f9; border-bottom: 2px solid #ddd;'; // REMOVED: Redundant with current-nav
        // dayContent.appendChild(contentHeader); // REMOVED: Redundant with current-nav
        
        // Add instruction if no cabang selected
        if (!currentCabangId) {
            const instruction = document.createElement('div');
            instruction.style.cssText = 'padding: 20px; text-align: center; color: #ff9800; background-color: #fff3e0; border-radius: 8px; margin: 20px;';
            instruction.innerHTML = '<strong>ℹ️ Pilih cabang terlebih dahulu untuk melihat dan assign shift!</strong>';
            dayContent.appendChild(instruction);
            return;
        }
        
        // Add Notify Employees button at top
        const notifyButtonContainer = document.createElement('div');
        notifyButtonContainer.style.cssText = 'padding: 15px 20px; background-color: #e3f2fd; border-radius: 8px; margin: 20px; display: flex; justify-content: space-between; align-items: center;';
        notifyButtonContainer.innerHTML = `
            <div>
                <strong style="color: #1976d2;">📧 Notifikasi Shift</strong>
                <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">Kirim email reminder ke semua pegawai yang memiliki shift hari ini</p>
            </div>
            <button id="notify-employees-btn" style="background-color: #2196F3; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 14px; white-space: nowrap; transition: background-color 0.3s;">
                📧 Kirim Notifikasi
            </button>
        `;
        dayContent.appendChild(notifyButtonContainer);
        
        // Add click handler for notify button
        const notifyBtn = notifyButtonContainer.querySelector('#notify-employees-btn');
        notifyBtn.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#1976D2';
        });
        notifyBtn.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '#2196F3';
        });
        notifyBtn.addEventListener('click', async function() {
            if (dayShifts.length === 0) {
                alert('❌ Tidak ada shift untuk tanggal ini');
                return;
            }
            
            if (confirm(`📧 Kirim email notifikasi ke ${dayShifts.length} pegawai?\n\nEmail akan berisi detail shift untuk tanggal ${dateStr}`)) {
                this.disabled = true;
                this.innerHTML = '⏳ Mengirim...';
                this.style.backgroundColor = '#757575';
                
                const result = await window.KalenderAPI.notifyEmployees(dateStr, currentCabangId);
                
                if (result.status === 'success') {
                    alert(`✅ ${result.message}\n\nTerkirim: ${result.sent}\nGagal: ${result.failed}`);
                } else {
                    alert(`❌ Gagal mengirim notifikasi: ${result.message}`);
                }
                
                this.disabled = false;
                this.innerHTML = '📧 Kirim Notifikasi';
                this.style.backgroundColor = '#2196F3';
            }
        });
        
        const HOUR_HEIGHT = 60;
        const contentContainer = document.createElement('div');
        contentContainer.style.cssText = `position: relative; height: ${24 * HOUR_HEIGHT}px;`;
        
        // Generate 24-hour time slots
        for (let hour = 0; hour < 24; hour++) {
            const contentSlot = document.createElement('div');
            contentSlot.className = 'day-content-slot-bg';
            contentSlot.style.cssText = `height: ${HOUR_HEIGHT}px; border-bottom: 1px solid #e0e0e0; position: absolute; top: ${hour * HOUR_HEIGHT}px; left: 0; right: 0; width: 100%; cursor: pointer; transition: background-color 0.2s;`;
            
            // Time label
            const timeLabel = document.createElement('div');
            timeLabel.textContent = `${String(hour).padStart(2, '0')}:00`;
            timeLabel.style.cssText = `position: absolute; left: 10px; top: 0; line-height: ${HOUR_HEIGHT}px; font-size: 12px; font-weight: 600; color: #666; width: 50px; text-align: right; padding-right: 10px; pointer-events: none;`;
            contentSlot.appendChild(timeLabel);
            
            // Content area
            const contentArea = document.createElement('div');
            contentArea.style.cssText = 'position: absolute; left: 70px; top: 0; right: 0; bottom: 0; border-left: 2px solid #ddd; background-color: rgba(0, 0, 0, 0.01); pointer-events: none;';
            contentSlot.appendChild(contentArea);
            
            // Hover effect
            contentSlot.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f5f5f5';
            });
            contentSlot.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
            
            // Click event to open assign modal
            contentSlot.addEventListener('click', function(e) {
                const isCardClick = e.target.classList.contains('day-shift') || e.target.closest('.day-shift');
                if (!isCardClick) {
                    window.KalenderAssign.openDayAssignModal(date, hour, currentCabangId, currentCabangName, shiftList, shiftAssignments);
                }
            });
            
            contentContainer.appendChild(contentSlot);
        }
        
        // Group shifts and create cards
        const shiftsGroupedByStart = {};
        dayShifts.forEach(assignment => {
            const jamMasuk = assignment.jam_masuk || '00:00:00';
            const jamKeluar = assignment.jam_keluar || '00:00:00';
            const startHour = parseInt(jamMasuk.split(':')[0]);
            const startMinute = parseInt(jamMasuk.split(':')[1]) || 0;
            
            const duration = window.KalenderUtils.calculateDuration(jamMasuk, jamKeluar);
            
            console.log(`🕐 Parsing shift time for ${assignment.nama_shift}:`, {
                jamMasuk: jamMasuk,
                jamKeluar: jamKeluar,
                startHour: startHour,
                startMinute: startMinute,
                duration: duration
            });
            
            const key = `${assignment.cabang_id}-${assignment.jam_masuk}-${assignment.jam_keluar}`;
            if (!shiftsGroupedByStart[key]) {
                shiftsGroupedByStart[key] = {
                    shift: assignment,
                    employees: [],
                    startHour: startHour,
                    startMinute: startMinute,
                    duration: duration,
                    jamMasuk: jamMasuk,
                    jamKeluar: jamKeluar
                };
            }
            shiftsGroupedByStart[key].employees.push(assignment);
        });
        
        console.log('📦 Day view - Grouped shifts:', Object.keys(shiftsGroupedByStart).length, 'groups');
        Object.values(shiftsGroupedByStart).forEach(group => {
            console.log(`   - ${group.shift.nama_shift}: ${group.employees.length} pegawai (${group.shift.jam_masuk}-${group.shift.jam_keluar})`);
        });
        
        // Detect overlapping shifts and assign columns
        const groupsArray = Object.values(shiftsGroupedByStart);
        
        // Calculate end time for each group
        groupsArray.forEach(group => {
            const endHour = parseInt(group.jamKeluar.split(':')[0]);
            const endMinute = parseInt(group.jamKeluar.split(':')[1]) || 0;
            group.endHour = endHour;
            group.endMinute = endMinute;
            group.column = 0; // Default column
        });
        
        // Sort by start time
        groupsArray.sort((a, b) => {
            const aStart = a.startHour + a.startMinute/60;
            const bStart = b.startHour + b.startMinute/60;
            return aStart - bStart;
        });
        
        // Detect overlaps and assign columns
        const columns = []; // Track occupied time ranges per column
        
        groupsArray.forEach(group => {
            const groupStart = group.startHour + group.startMinute/60;
            const groupEnd = group.endHour + group.endMinute/60;
            
            // Find first available column
            let assignedColumn = -1;
            
            for (let col = 0; col < columns.length; col++) {
                const columnShifts = columns[col];
                let hasOverlap = false;
                
                for (let existingShift of columnShifts) {
                    const existingStart = existingShift.startHour + existingShift.startMinute/60;
                    const existingEnd = existingShift.endHour + existingShift.endMinute/60;
                    
                    // Check if there's an overlap
                    if (groupStart < existingEnd && groupEnd > existingStart) {
                        hasOverlap = true;
                        break;
                    }
                }
                
                if (!hasOverlap) {
                    assignedColumn = col;
                    columns[col].push(group);
                    break;
                }
            }
            
            // If no available column found, create new one
            if (assignedColumn === -1) {
                columns.push([group]);
                assignedColumn = columns.length - 1;
            }
            
            group.column = assignedColumn;
        });
        
        const totalColumns = Math.max(columns.length, 1);
        
        console.log(`📐 Layout: ${totalColumns} columns detected`);
        columns.forEach((col, index) => {
            console.log(`   Column ${index}: ${col.map(g => g.shift.nama_shift).join(', ')}`);
        });
        
        // Create shift cards with horizontal layout
        groupsArray.forEach(group => {
            const firstAssignment = group.shift;
            
            // Determine status
            const statuses = group.employees.map(e => e.status_konfirmasi || 'pending');
            const hasApproved = statuses.includes('approved');
            const hasDeclined = statuses.includes('declined');
            const isApproved = hasApproved;
            
            // Get shift colors based on shift type
            const shiftColors = window.KalenderUtils.getShiftColor(firstAssignment.nama_shift);
            const shiftEmoji = window.KalenderUtils.getShiftEmoji(firstAssignment.nama_shift);
            
            let bgColor = shiftColors.bg;
            let borderColor = shiftColors.border;
            let textColor = shiftColors.text;
            
            // Override colors for status
            if (isApproved) {
                bgColor = '#e8f5e9';
                borderColor = '#4CAF50';
                textColor = '#2e7d32';
            } else if (hasDeclined) {
                bgColor = '#ffebee';
                borderColor = '#f44336';
                textColor = '#c62828';
            }
            
            // Calculate position and height
            const topPosition = (group.startHour + group.startMinute/60) * HOUR_HEIGHT;
            const cardHeight = group.duration * HOUR_HEIGHT - 4;
            
            // Calculate horizontal position based on column
            const columnWidth = 100 / totalColumns; // Width percentage per column
            const leftOffset = 70; // Time label width
            const columnLeftPercent = (group.column * columnWidth);
            const cardWidthPercent = columnWidth - 1; // -1% for gap between columns
            
            console.log(`📍 Positioning ${firstAssignment.nama_shift} card:`, {
                jamMasuk: group.jamMasuk,
                jamKeluar: group.jamKeluar,
                column: group.column,
                totalColumns: totalColumns,
                topPosition: topPosition,
                cardHeight: cardHeight,
                columnLeftPercent: columnLeftPercent,
                cardWidthPercent: cardWidthPercent
            });
            
            const shiftDiv = document.createElement('div');
            shiftDiv.className = 'day-shift';
            shiftDiv.style.cssText = `
                background-color: ${bgColor}; 
                padding: 8px; 
                margin: 2px; 
                border-left: 4px solid ${borderColor}; 
                border-radius: 4px; 
                position: absolute; 
                top: ${topPosition}px; 
                height: ${cardHeight}px; 
                left: calc(${leftOffset}px + ${columnLeftPercent}%); 
                width: calc(${cardWidthPercent}% - ${leftOffset/totalColumns}px);
                box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
                z-index: 10; 
                overflow: auto; 
                pointer-events: auto; 
                cursor: pointer;
            `.replace(/\s+/g, ' ').trim();
            
            // Status badge
            let statusBadge = '';
            let statusText = '';
            if (isApproved) {
                statusBadge = '✓';
                statusText = 'Approved';
            } else if (hasDeclined) {
                statusBadge = '✗';
                statusText = 'Declined';
            } else {
                statusBadge = '⏱';
                statusText = 'Pending';
            }
            
            // Build employee list
            let employeeList = '';
            group.employees.forEach((emp) => {
                const empStatus = emp.status_konfirmasi || 'pending';
                let empIcon = '⏱';
                if (empStatus === 'approved') empIcon = '✅';
                else if (empStatus === 'declined') empIcon = '❌';
                
                employeeList += `
                    <div style="display: flex; align-items: center; gap: 6px; padding: 4px 0;">
                        <span style="font-size: 12px;">${empIcon}</span>
                        <span style="font-size: 13px; color: #333;">${emp.nama_lengkap || emp.pegawai_name || 'Unknown'}</span>
                    </div>
                `;
            });
            
            shiftDiv.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                    <div style="flex: 1;">
                        <div style="color: ${textColor}; font-weight: bold; font-size: 14px; margin-bottom: 4px;">
                            ${shiftEmoji} ${firstAssignment.nama_shift || firstAssignment.shift_type || 'Shift'}
                        </div>
                        <div style="color: #666; font-size: 12px; margin-bottom: 8px;">
                            ⏰ ${window.KalenderUtils.formatTime(firstAssignment.jam_masuk)} - ${window.KalenderUtils.formatTime(firstAssignment.jam_keluar)}
                        </div>
                    </div>
                    <span class="status-badge badge-${statusBadge}" style="font-size: 11px; padding: 3px 8px; border-radius: 3px; white-space: nowrap;">
                        ${statusBadge} ${statusText}
                    </span>
                </div>
                <div style="border-top: 1px solid ${borderColor}40; padding-top: 8px;">
                    <div style="font-size: 11px; color: #666; margin-bottom: 4px; font-weight: 600;">
                        Pegawai (${group.employees.length}):
                    </div>
                    ${employeeList}
                </div>
                ${isApproved ? '<div style="color: #4CAF50; font-size: 11px; margin-top: 8px; padding-top: 6px; border-top: 1px solid #4CAF5040;"><em>🔒 Shift ini terkunci (ada pegawai yang sudah approved)</em></div>' : ''}
            `;
            
            // Add click handler to open delete modal
            shiftDiv.addEventListener('click', function(e) {
                e.stopPropagation();
                const dateFormatted = date.toLocaleDateString('id-ID', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                window.KalenderDelete.openDeleteModal(group, dateFormatted);
            });
            
            contentContainer.appendChild(shiftDiv);
        });
        
        dayContent.appendChild(contentContainer);
        
        // Show info if no shifts assigned
        if (dayShifts.length === 0) {
            const noShiftInfo = document.createElement('div');
            noShiftInfo.style.cssText = 'padding: 20px; text-align: center; color: #666; background-color: #f5f5f5; border-radius: 8px; margin: 20px;';
            noShiftInfo.innerHTML = '<strong>📅 Belum ada shift yang di-assign untuk hari ini</strong><br><small>Klik pada jam di sebelah kiri untuk assign shift</small>';
            dayContent.appendChild(noShiftInfo);
        }
        
        // Add instruction at bottom
        const clickInstruction = document.createElement('div');
        clickInstruction.style.cssText = 'padding: 15px; text-align: center; color: #4CAF50; background-color: #e8f5e9; border-radius: 8px; margin: 20px; font-size: 14px;';
        clickInstruction.innerHTML = '<strong>💡 Tip:</strong> Klik pada waktu di sebelah kiri untuk assign shift, klik pada shift card untuk hapus';
        dayContent.appendChild(clickInstruction);
        
        updateSummaries();
    }
    
    function generateYearView(year) {
        hideAllViews();
        const yearView = document.getElementById('year-view');
        if (yearView) yearView.style.display = 'block';
        
        const yearGrid = document.getElementById('year-grid');
        if (!yearGrid) return;
        
        yearGrid.innerHTML = '';
        
        // REMOVED: yearHeader element to eliminate UI duplication
        // Navigation year is displayed in current-nav element
        
        const monthNames = window.KalenderUtils.monthNames;
        
        for (let month = 0; month < 12; month++) {
            const monthDiv = document.createElement('div');
            monthDiv.className = 'month-mini';
            
            const monthTitle = document.createElement('h4');
            monthTitle.textContent = monthNames[month];
            monthDiv.appendChild(monthTitle);
            
            const miniCalendar = document.createElement('div');
            miniCalendar.className = 'mini-calendar-grid';
            miniCalendar.style.cssText = 'display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; font-size: 11px;';
            
            // Add day headers
            const dayHeaders = ['M', 'S', 'S', 'R', 'K', 'J', 'S'];
            dayHeaders.forEach(day => {
                const headerCell = document.createElement('div');
                headerCell.style.cssText = 'text-align: center; font-weight: bold;';
                headerCell.textContent = day;
                miniCalendar.appendChild(headerCell);
            });
            
            // Mini calendar for each month
            const firstDay = new Date(year, month, 1).getDay();
            const adjustedFirstDay = firstDay === 0 ? 6 : firstDay - 1;
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            
            // Add empty cells before first day
            for (let i = 0; i < adjustedFirstDay; i++) {
                const emptyCell = document.createElement('div');
                miniCalendar.appendChild(emptyCell);
            }
            
            // Add day cells
            for (let date = 1; date <= daysInMonth; date++) {
                const dayCell = document.createElement('div');
                dayCell.textContent = date;
                dayCell.style.cssText = 'text-align: center; padding: 2px; cursor: pointer; border-radius: 3px;';
                
                // Highlight today
                const today = new Date();
                if (date === today.getDate() && 
                    month === today.getMonth() && 
                    year === today.getFullYear()) {
                    dayCell.style.backgroundColor = '#4caf50';
                    dayCell.style.color = 'white';
                }
                
                dayCell.addEventListener('click', () => {
                    currentDate = new Date(year, month, date);
                    currentMonth = month;
                    currentYear = year;
                    switchView('month');
                });
                
                dayCell.addEventListener('mouseenter', () => {
                    if (dayCell.style.backgroundColor !== 'rgb(76, 175, 80)') {
                        dayCell.style.backgroundColor = '#e0e0e0';
                    }
                });
                
                dayCell.addEventListener('mouseleave', () => {
                    if (dayCell.style.backgroundColor !== 'rgb(76, 175, 80)') {
                        dayCell.style.backgroundColor = '';
                    }
                });
                
                miniCalendar.appendChild(dayCell);
            }
            
            monthDiv.appendChild(miniCalendar);
            yearGrid.appendChild(monthDiv);
        }
        
        updateSummaries();
    }
    
    function hideAllViews() {
        const monthView = document.getElementById('month-view');
        const weekView = document.getElementById('week-view');
        const dayView = document.getElementById('day-view');
        const yearView = document.getElementById('year-view');
        
        if (monthView) monthView.style.display = 'none';
        if (weekView) weekView.style.display = 'none';
        if (dayView) dayView.style.display = 'none';
        if (yearView) yearView.style.display = 'none';
    }
    
    // ============ VIEW SWITCHING ============
    function switchView(view) {
        currentView = view;
        
        // Update active button
        document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById(`view-${view}`)?.classList.add('active');
        
        if (currentCabangId) {
            console.log(`Switching to ${view} view, reloading shift assignments for cabang ${currentCabangId}`);
            window.KalenderAPI.loadShiftAssignments(currentCabangId).then(data => {
                shiftAssignments = data;
                generateCalendar(currentMonth, currentYear);
            });
        } else {
            if (view === 'month') {
                generateMonthView(currentMonth, currentYear);
            } else if (view === 'week') {
                generateWeekView(currentDate);
            } else if (view === 'day') {
                generateDayView(currentDate);
            } else if (view === 'year') {
                generateYearView(currentYear);
            }
        }
        
        updateNavigationLabels();
    }
    
    // ============ NAVIGATION ============
    function navigatePrevious() {
        if (currentView === 'month') {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            generateMonthView(currentMonth, currentYear);
        } else if (currentView === 'week') {
            currentDate.setDate(currentDate.getDate() - 7);
            generateWeekView(currentDate);
        } else if (currentView === 'day') {
            currentDate.setDate(currentDate.getDate() - 1);
            generateDayView(currentDate);
        } else if (currentView === 'year') {
            currentYear--;
            generateYearView(currentYear);
        }
        
        if (currentCabangId) {
            window.KalenderAPI.loadShiftAssignments(currentCabangId).then(data => {
                shiftAssignments = data;
                generateCalendar(currentMonth, currentYear);
            });
        }
        updateNavigationLabels();
    }
    
    function navigateNext() {
        if (currentView === 'month') {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            generateMonthView(currentMonth, currentYear);
        } else if (currentView === 'week') {
            currentDate.setDate(currentDate.getDate() + 7);
            generateWeekView(currentDate);
        } else if (currentView === 'day') {
            currentDate.setDate(currentDate.getDate() + 1);
            generateDayView(currentDate);
        } else if (currentView === 'year') {
            currentYear++;
            generateYearView(currentYear);
        }
        
        if (currentCabangId) {
            window.KalenderAPI.loadShiftAssignments(currentCabangId).then(data => {
                shiftAssignments = data;
                generateCalendar(currentMonth, currentYear);
            });
        }
        updateNavigationLabels();
    }
    
    function updateNavigationLabels() {
        const currentNav = document.getElementById('current-nav');
        const prevLabel = document.getElementById('prev-label');
        const nextLabel = document.getElementById('next-label');
        const monthNames = window.KalenderUtils.monthNames;
        
        if (currentView === 'month') {
            if (currentNav) currentNav.textContent = `${monthNames[currentMonth]} ${currentYear}`;
            if (prevLabel) prevLabel.textContent = 'Bulan Sebelumnya';
            if (nextLabel) nextLabel.textContent = 'Bulan Berikutnya';
        } else if (currentView === 'week') {
            const weekStart = new Date(currentDate);
            const day = weekStart.getDay();
            const diff = weekStart.getDate() - day + (day === 0 ? -6 : 1);
            weekStart.setDate(diff);
            const weekEnd = new Date(weekStart);
            weekEnd.setDate(weekStart.getDate() + 6);
            
            if (currentNav) currentNav.textContent = `${weekStart.getDate()} ${monthNames[weekStart.getMonth()]} - ${weekEnd.getDate()} ${monthNames[weekEnd.getMonth()]} ${weekEnd.getFullYear()}`;
            if (prevLabel) prevLabel.textContent = 'Minggu Sebelumnya';
            if (nextLabel) nextLabel.textContent = 'Minggu Berikutnya';
        } else if (currentView === 'day') {
            if (currentNav) currentNav.textContent = `${currentDate.getDate()} ${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
            if (prevLabel) prevLabel.textContent = 'Hari Sebelumnya';
            if (nextLabel) nextLabel.textContent = 'Hari Berikutnya';
        } else if (currentView === 'year') {
            if (currentNav) currentNav.textContent = `${currentYear}`;
            if (prevLabel) prevLabel.textContent = 'Tahun Sebelumnya';
            if (nextLabel) nextLabel.textContent = 'Tahun Berikutnya';
        }
    }
    
    // ============ FEATURE FUNCTIONS ============
    function exportSchedule() {
        if (!currentCabangName) {
            alert('❌ Pilih cabang terlebih dahulu!');
            return;
        }
        
        const monthNames = window.KalenderUtils.monthNames;
        let csv = 'Tanggal,Karyawan,Shift,Jam Masuk,Jam Keluar,Cabang\n';
        
        if (shiftAssignments && typeof shiftAssignments === 'object') {
            for (const dateKey in shiftAssignments) {
                const assignment = shiftAssignments[dateKey];
                csv += `${assignment.shift_date},${assignment.nama_lengkap || 'N/A'},${assignment.nama_shift || 'N/A'},${assignment.jam_masuk || 'N/A'},${assignment.jam_keluar || 'N/A'},${currentCabangName}\n`;
            }
        }
        
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `jadwal_shift_${currentCabangName}_${monthNames[currentMonth]}_${currentYear}.csv`;
        a.click();
        URL.revokeObjectURL(url);
        alert('✅ Jadwal berhasil diekspor!');
    }
    
    function backupData() {
        const data = {
            shiftAssignments: shiftAssignments,
            holidays: holidays,
            currentCabangId: currentCabangId,
            currentCabangName: currentCabangName,
            currentMonth: currentMonth,
            currentYear: currentYear,
            backupDate: new Date().toISOString()
        };
        const json = JSON.stringify(data, null, 2);
        const blob = new Blob([json], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `backup_kalender_${currentCabangName || 'all'}_${new Date().toISOString().split('T')[0]}.json`;
        a.click();
        URL.revokeObjectURL(url);
        alert('✅ Data berhasil di-backup!');
    }
    
    function restoreData() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.json';
        
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(event) {
                try {
                    const data = JSON.parse(event.target.result);
                    
                    if (confirm('⚠️ Restore data akan menimpa data yang ada di memori. Lanjutkan?\n\nNote: Data di database tidak akan terpengaruh.')) {
                        if (data.shiftAssignments) shiftAssignments = data.shiftAssignments;
                        if (data.holidays) holidays = data.holidays;
                        if (data.currentCabangId) currentCabangId = data.currentCabangId;
                        if (data.currentCabangName) currentCabangName = data.currentCabangName;
                        
                        generateCalendar(currentMonth, currentYear);
                        alert('✅ Data berhasil di-restore!');
                    }
                } catch (error) {
                    alert('❌ File tidak valid!');
                    console.error('Error parsing backup file:', error);
                }
            };
            reader.readAsText(file);
        });
        
        input.click();
    }
    
    function toggleSummary() {
        const summaryTables = document.getElementById('summary-tables');
        const calendarView = document.getElementById('calendar-view');
        const navigation = document.getElementById('navigation');
        
        if (summaryTables) {
            if (summaryTables.style.display === 'none' || summaryTables.style.display === '') {
                // Show summary, hide calendar
                summaryTables.style.display = 'block';
                if (calendarView) calendarView.style.display = 'none';
                if (navigation) navigation.style.display = 'none';
                hideAllViews();
                updateSummaries();
            } else {
                // Hide summary, show calendar
                hideSummary();
            }
        }
    }
    
    function hideSummary() {
        const summaryTables = document.getElementById('summary-tables');
        const calendarView = document.getElementById('calendar-view');
        const navigation = document.getElementById('navigation');
        
        if (summaryTables) summaryTables.style.display = 'none';
        if (calendarView) calendarView.style.display = 'block';
        if (navigation) navigation.style.display = 'flex';
        
        generateCalendar(currentMonth, currentYear);
    }
    
    function updateSummaries() {
        console.log('Updating summaries for view:', currentView);
        
        if (!currentCabangName) {
            window.KalenderSummary.updateSummaryDisplay('Pilih cabang terlebih dahulu', [], {});
            return;
        }
        
        const dateRange = window.KalenderSummary.getDateRangeForCurrentView(
            currentView,
            currentDate,
            currentMonth,
            currentYear
        );
        const rangeName = window.KalenderSummary.getViewRangeName(
            currentView,
            currentDate,
            currentMonth,
            currentYear
        );
        
        const currentSummary = document.getElementById('current-summary');
        if (currentSummary) {
            currentSummary.textContent = `Ringkasan ${rangeName} - ${currentCabangName}`;
            currentSummary.style.display = 'block';
        }
        
        const employeeSummary = window.KalenderSummary.calculateEmployeeSummary(dateRange, shiftAssignments);
        const shiftSummary = window.KalenderSummary.calculateShiftSummary(dateRange, shiftAssignments);
        
        window.KalenderSummary.updateSummaryDisplay(rangeName, employeeSummary, shiftSummary);
        
        updateSummaryNavigationLabels();
    }
    
    function updateSummaryNavigationLabels() {
        const summaryCurrentNav = document.getElementById('summary-current-nav');
        const summaryPrevLabel = document.getElementById('summary-prev-label');
        const summaryNextLabel = document.getElementById('summary-next-label');
        const monthNames = window.KalenderUtils.monthNames;
        
        if (currentView === 'month') {
            if (summaryCurrentNav) summaryCurrentNav.textContent = `${monthNames[currentMonth]} ${currentYear}`;
            if (summaryPrevLabel) summaryPrevLabel.textContent = '◀ Bulan Sebelumnya';
            if (summaryNextLabel) summaryNextLabel.textContent = 'Bulan Berikutnya ▶';
        } else if (currentView === 'week') {
            const weekStart = new Date(currentDate);
            const day = weekStart.getDay();
            const diff = weekStart.getDate() - day + (day === 0 ? -6 : 1);
            weekStart.setDate(diff);
            const weekEnd = new Date(weekStart);
            weekEnd.setDate(weekStart.getDate() + 6);
            
            if (summaryCurrentNav) summaryCurrentNav.textContent = `${weekStart.getDate()} ${monthNames[weekStart.getMonth()]} - ${weekEnd.getDate()} ${monthNames[weekEnd.getMonth()]} ${weekEnd.getFullYear()}`;
            if (summaryPrevLabel) summaryPrevLabel.textContent = '◀ Minggu Sebelumnya';
            if (summaryNextLabel) summaryNextLabel.textContent = 'Minggu Berikutnya ▶';
        } else if (currentView === 'day') {
            if (summaryCurrentNav) summaryCurrentNav.textContent = `${currentDate.getDate()} ${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
            if (summaryPrevLabel) summaryPrevLabel.textContent = '◀ Hari Sebelumnya';
            if (summaryNextLabel) summaryNextLabel.textContent = 'Hari Berikutnya ▶';
        } else if (currentView === 'year') {
            if (summaryCurrentNav) summaryCurrentNav.textContent = `${currentYear}`;
            if (summaryPrevLabel) summaryPrevLabel.textContent = '◀ Tahun Sebelumnya';
            if (summaryNextLabel) summaryNextLabel.textContent = 'Tahun Berikutnya ▶';
        }
    }
    
    function navigateSummaryPrevious() {
        navigatePrevious();
        updateSummaries();
    }
    
    function navigateSummaryNext() {
        navigateNext();
        updateSummaries();
    }
    
    function downloadSummary() {
        const dateRange = window.KalenderSummary.getDateRangeForCurrentView(
            currentView,
            currentDate,
            currentMonth,
            currentYear
        );
        const rangeName = window.KalenderSummary.getViewRangeName(
            currentView,
            currentDate,
            currentMonth,
            currentYear
        );
        const format = document.getElementById('download-format')?.value || 'csv';
        
        const employeeSummary = window.KalenderSummary.calculateEmployeeSummary(dateRange, shiftAssignments);
        const shiftSummary = window.KalenderSummary.calculateShiftSummary(dateRange, shiftAssignments);
        
        let content, filename, mimeType;
        
        if (format === 'txt') {
            content = window.KalenderSummary.generateTXTContent(employeeSummary, shiftSummary, rangeName, currentCabangName);
            filename = `ringkasan_shift_${currentCabangName}_${rangeName.replace(/\s+/g, '_')}.txt`;
            mimeType = 'text/plain;charset=utf-8;';
        } else {
            content = window.KalenderSummary.generateCSVContent(dateRange, rangeName, shiftAssignments);
            filename = `ringkasan_shift_${currentCabangName}_${rangeName.replace(/\s+/g, '_')}.csv`;
            mimeType = 'text/csv;charset=utf-8;';
        }
        
        const blob = new Blob([content], { type: mimeType });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);
        
        alert('✅ Ringkasan berhasil diunduh!');
    }
    
    // Export to window
    window.KalenderCore = KalenderCore;
    
    console.log('✅ KalenderCore module loaded');
    
    // Auto-initialize
    KalenderCore.init();
    
})(window);
