/**
 * Kalender Shift Karyawan - Database Integration (Complete Version)
 * Versi lengkap dengan semua fitur esensial dari script_hybrid.js
 */

// ============ VARIABLES ============
let currentCabangId = null;
let pegawaiList = [];
let shiftAssignments = {};
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();
let currentView = 'month';
let currentDate = new Date();
let holidays = [];

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

const shiftDetails = {
    'pagi': { hours: 8, start: '08:00', end: '16:00', label: 'Shift Pagi' },
    'siang': { hours: 8, start: '16:00', end: '00:00', label: 'Shift Siang' },
    'malam': { hours: 8, start: '00:00', end: '08:00', label: 'Shift Malam' },
    'off': { hours: 0, start: '-', end: '-', label: 'Off' }
};

// ============ INITIALIZATION ============
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing Kalender Database Integration (Complete)...');
    initializeApp();
});

async function initializeApp() {
    await loadCabangList();
    setupAllEventListeners();
    generateCalendar(currentMonth, currentYear);
    updateNavigationLabels();
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
    document.getElementById('cabang-select')?.addEventListener('change', function() {
        currentCabangId = this.value || null;
        if (currentCabangId) {
            loadShiftAssignments();
        }
        generateCalendar(currentMonth, currentYear);
    });
    
    // Modal close
    document.querySelector('.close')?.addEventListener('click', () => {
        document.getElementById('shift-modal').style.display = 'none';
    });
    
    // Feature buttons
    document.getElementById('add-employee')?.addEventListener('click', addEmployee);
    document.getElementById('export-schedule')?.addEventListener('click', exportSchedule);
    document.getElementById('add-holiday')?.addEventListener('click', addHoliday);
    document.getElementById('search-employee')?.addEventListener('click', searchEmployee);
    document.getElementById('filter-status')?.addEventListener('click', filterByStatus);
    document.getElementById('filter-date')?.addEventListener('click', filterByDateRange);
    document.getElementById('notify-shifts')?.addEventListener('click', notifyUpcomingShifts);
    document.getElementById('alert-low-shifts')?.addEventListener('click', alertLowShifts);
    document.getElementById('backup-data')?.addEventListener('click', backupData);
    document.getElementById('restore-data')?.addEventListener('click', restoreData);
    document.getElementById('set-preferences')?.addEventListener('click', setEmployeePreferences);
    document.getElementById('set-timezone')?.addEventListener('click', setTimeZone);
    document.getElementById('notify-manager')?.addEventListener('click', notifyManager);
    document.getElementById('notify-employee-change')?.addEventListener('click', notifyEmployeeChange);
    document.getElementById('notify-employee-assigned')?.addEventListener('click', notifyEmployeeAssigned);
    document.getElementById('toggle-summary')?.addEventListener('click', toggleSummary);
    document.getElementById('hide-summary')?.addEventListener('click', hideSummary);
    document.getElementById('download-summary')?.addEventListener('click', downloadSummary);
}

// ============ DATABASE FUNCTIONS ============
async function loadCabangList() {
    try {
        const response = await fetch('api_shift_calendar.php?action=get_cabang');
        const result = await response.json();
        
        if (result.status === 'success' && result.data) {
            const cabangSelect = document.getElementById('cabang-select');
            if (cabangSelect) {
                cabangSelect.innerHTML = '<option value="">-- Pilih Cabang & Shift --</option>';
                result.data.forEach(cabang => {
                    const option = document.createElement('option');
                    option.value = cabang.id;
                    option.textContent = cabang.nama_cabang;
                    cabangSelect.appendChild(option);
                });
            }
        }
    } catch (error) {
        console.error('Error loading cabang:', error);
    }
}

async function loadShiftAssignments() {
    if (!currentCabangId) return;
    
    try {
        const response = await fetch(`api_shift_calendar.php?action=get_assignments&cabang_id=${currentCabangId}&month=${currentMonth + 1}&year=${currentYear}`);
        const result = await response.json();
        
        if (result.status === 'success') {
            shiftAssignments = {};
            if (result.data) {
                result.data.forEach(assignment => {
                    const key = `${assignment.shift_date}-${assignment.pegawai_id}`;
                    shiftAssignments[key] = assignment;
                });
            }
            generateCalendar(currentMonth, currentYear);
        }
    } catch (error) {
        console.error('Error loading shift assignments:', error);
    }
}

async function saveShiftAssignment(pegawaiId, date, shiftType) {
    if (!currentCabangId) {
        alert('Pilih cabang terlebih dahulu!');
        return false;
    }
    
    try {
        const response = await fetch('api_shift_calendar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_assignment',
                cabang_id: currentCabangId,
                pegawai_id: pegawaiId,
                shift_date: date,
                shift_type: shiftType
            })
        });
        
        const result = await response.json();
        if (result.status === 'success') {
            await loadShiftAssignments();
            return true;
        }
        return false;
    } catch (error) {
        console.error('Error saving shift:', error);
        return false;
    }
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

function generateMonthView(month, year) {
    const calendarBody = document.getElementById('calendar-body');
    const monthYear = document.getElementById('month-year');
    
    if (!calendarBody) return;
    
    calendarBody.innerHTML = '';
    
    if (monthYear) {
        monthYear.textContent = `${monthNames[month]} ${year}`;
    }
    
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    let date = 1;

    for (let i = 0; i < 6; i++) {
        const row = document.createElement('tr');

        for (let j = 0; j < 7; j++) {
            if (i === 0 && j < firstDay) {
                const cell = document.createElement('td');
                row.appendChild(cell);
            } else if (date > daysInMonth) {
                break;
            } else {
                const cell = document.createElement('td');
                cell.classList.add('calendar-day');
                
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(date).padStart(2, '0')}`;
                cell.dataset.date = dateStr;
                
                // Date number
                const dateDiv = document.createElement('div');
                dateDiv.className = 'date-number';
                dateDiv.textContent = date;
                cell.appendChild(dateDiv);
                
                // Display shift assignments for this date
                if (currentCabangId && shiftAssignments) {
                    Object.keys(shiftAssignments).forEach(key => {
                        const assignment = shiftAssignments[key];
                        if (assignment.shift_date === dateStr) {
                            const shiftDiv = document.createElement('div');
                            shiftDiv.className = `shift-assignment shift-${assignment.shift_type}`;
                            shiftDiv.textContent = `${assignment.pegawai_name}: ${shiftDetails[assignment.shift_type]?.label || assignment.shift_type}`;
                            cell.appendChild(shiftDiv);
                        }
                    });
                }
                
                // Highlight holidays
                if (holidays.includes(dateStr)) {
                    cell.classList.add('holiday');
                }

                // Highlight today
                const today = new Date();
                if (date === today.getDate() && 
                    year === today.getFullYear() && 
                    month === today.getMonth()) {
                    cell.classList.add('today');
                }

                // Click event
                cell.addEventListener('click', () => {
                    currentDate = new Date(year, month, date);
                    switchView('day');
                });

                row.appendChild(cell);
                date++;
            }
        }
        calendarBody.appendChild(row);
    }
}

function generateWeekView(date) {
    const weekView = document.getElementById('week-view');
    const weekCalendar = document.getElementById('week-calendar');
    
    if (!weekView || !weekCalendar) return;
    
    // Hide other views
    document.getElementById('month-view').style.display = 'none';
    document.getElementById('day-view').style.display = 'none';
    document.getElementById('year-view').style.display = 'none';
    weekView.style.display = 'block';
    
    // Calculate week start (Monday)
    const weekStart = new Date(date);
    const day = weekStart.getDay();
    const diff = weekStart.getDate() - day + (day === 0 ? -6 : 1);
    weekStart.setDate(diff);
    
    // Clear and rebuild
    const timeColumn = document.getElementById('time-column');
    const daysColumn = document.getElementById('days-column');
    
    if (timeColumn) {
        timeColumn.innerHTML = '<div class="time-header">Waktu</div>';
        for (let hour = 0; hour < 24; hour++) {
            const timeSlot = document.createElement('div');
            timeSlot.className = 'time-slot';
            timeSlot.textContent = `${String(hour).padStart(2, '0')}:00`;
            timeColumn.appendChild(timeSlot);
        }
    }
    
    if (daysColumn) {
        daysColumn.innerHTML = '';
        const dayNames = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        
        for (let i = 0; i < 7; i++) {
            const currentDay = new Date(weekStart);
            currentDay.setDate(weekStart.getDate() + i);
            
            const dayColumn = document.createElement('div');
            dayColumn.className = 'day-column';
            
            // REMOVED: dayHeader element to eliminate UI duplication
            // Navigation date is displayed in current-nav element
            
            // Add time slots for this day
            for (let hour = 0; hour < 24; hour++) {
                const slot = document.createElement('div');
                slot.className = 'week-time-slot';
                
                const dateStr = currentDay.toISOString().split('T')[0];
                // Display shifts for this day/time if applicable
                
                dayColumn.appendChild(slot);
            }
            
            daysColumn.appendChild(dayColumn);
        }
    }
}

function generateDayView(date) {
    const dayView = document.getElementById('day-view');
    const dayCalendar = document.getElementById('day-calendar');
    
    if (!dayView || !dayCalendar) return;
    
    // Hide other views
    document.getElementById('month-view').style.display = 'none';
    document.getElementById('week-view').style.display = 'none';
    document.getElementById('year-view').style.display = 'none';
    dayView.style.display = 'block';
    
    const dayTimeColumn = document.getElementById('day-time-column');
    const dayContent = document.getElementById('day-content');
    
    if (dayTimeColumn) {
        dayTimeColumn.innerHTML = '<div class="time-header">Waktu</div>';
        for (let hour = 0; hour < 24; hour++) {
            const timeSlot = document.createElement('div');
            timeSlot.className = 'time-slot';
            timeSlot.textContent = `${String(hour).padStart(2, '0')}:00`;
            dayTimeColumn.appendChild(timeSlot);
        }
    }
    
    if (dayContent) {
        dayContent.innerHTML = '';
        const dateStr = date.toISOString().split('T')[0];
        
        // REMOVED: header (day-header) element to eliminate UI duplication
        // Navigation date is displayed in current-nav element
        
        // Display shifts for this day
        if (currentCabangId && shiftAssignments) {
            Object.keys(shiftAssignments).forEach(key => {
                const assignment = shiftAssignments[key];
                if (assignment.shift_date === dateStr) {
                    const shiftDiv = document.createElement('div');
                    shiftDiv.className = 'day-shift';
                    const details = shiftDetails[assignment.shift_type];
                    shiftDiv.innerHTML = `
                        <strong>${assignment.pegawai_name}</strong><br>
                        ${details?.label || assignment.shift_type}<br>
                        ${details?.start} - ${details?.end}
                    `;
                    dayContent.appendChild(shiftDiv);
                }
            });
        }
    }
}

function generateYearView(year) {
    const yearView = document.getElementById('year-view');
    const yearGrid = document.getElementById('year-grid');
    
    if (!yearView || !yearGrid) return;
    
    // Hide other views
    document.getElementById('month-view').style.display = 'none';
    document.getElementById('week-view').style.display = 'none';
    document.getElementById('day-view').style.display = 'none';
    yearView.style.display = 'block';
    
    // REMOVED: year header element to eliminate UI duplication
    // Navigation year is displayed in current-nav element
    
    for (let month = 0; month < 12; month++) {
        const monthDiv = document.createElement('div');
        monthDiv.className = 'year-month';
        monthDiv.innerHTML = `<h3>${monthNames[month]}</h3>`;
        
        const miniCalendar = document.createElement('div');
        miniCalendar.className = 'mini-calendar';
        
        // Mini calendar for each month
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        
        let date = 1;
        for (let i = 0; i < 6; i++) {
            for (let j = 0; j < 7; j++) {
                const dayCell = document.createElement('span');
                if (i === 0 && j < firstDay) {
                    dayCell.textContent = '';
                } else if (date > daysInMonth) {
                    break;
                } else {
                    dayCell.textContent = date;
                    dayCell.addEventListener('click', () => {
                        currentDate = new Date(year, month, date);
                        currentMonth = month;
                        currentYear = year;
                        switchView('day');
                    });
                    date++;
                }
                miniCalendar.appendChild(dayCell);
            }
        }
        
        monthDiv.appendChild(miniCalendar);
        yearGrid.appendChild(monthDiv);
    }
}

// ============ VIEW SWITCHING ============
function switchView(view) {
    currentView = view;
    
    // Update active button
    document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById(`view-${view}`)?.classList.add('active');
    
    // Generate appropriate view
    if (view === 'month') {
        generateMonthView(currentMonth, currentYear);
    } else if (view === 'week') {
        generateWeekView(currentDate);
    } else if (view === 'day') {
        generateDayView(currentDate);
    } else if (view === 'year') {
        generateYearView(currentYear);
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
        loadShiftAssignments();
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
        loadShiftAssignments();
    }
    updateNavigationLabels();
}

function updateNavigationLabels() {
    const currentNav = document.getElementById('current-nav');
    const prevLabel = document.getElementById('prev-label');
    const nextLabel = document.getElementById('next-label');
    
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

// ============ FEATURE FUNCTIONS (Stubs for now) ============
function addEmployee() {
    alert('Fitur Tambah Karyawan: Silakan gunakan halaman manajemen pegawai untuk menambah karyawan baru.');
}

function exportSchedule() {
    let csv = 'Tanggal,Karyawan,Shift\n';
    
    if (currentCabangId && shiftAssignments) {
        Object.values(shiftAssignments).forEach(assignment => {
            csv += `${assignment.shift_date},${assignment.pegawai_name},${assignment.shift_type}\n`;
        });
    }
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `jadwal_shift_${monthNames[currentMonth]}_${currentYear}.csv`;
    a.click();
}

function addHoliday() {
    const dateStr = prompt('Masukkan tanggal libur (YYYY-MM-DD):');
    if (dateStr && /^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
        holidays.push(dateStr);
        generateCalendar(currentMonth, currentYear);
        alert('Hari libur berhasil ditambahkan!');
    }
}

function searchEmployee() {
    alert('Fitur Cari Karyawan akan segera tersedia.');
}

function filterByStatus() {
    alert('Fitur Filter Status akan segera tersedia.');
}

function filterByDateRange() {
    alert('Fitur Filter Tanggal akan segera tersedia.');
}

function notifyUpcomingShifts() {
    alert('Fitur Notifikasi Shift akan segera tersedia.');
}

function alertLowShifts() {
    alert('Fitur Alert Shift Kurang akan segera tersedia.');
}

function backupData() {
    const data = {
        shiftAssignments: shiftAssignments,
        holidays: holidays,
        currentCabangId: currentCabangId
    };
    const json = JSON.stringify(data);
    const blob = new Blob([json], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `backup_kalender_${new Date().toISOString().split('T')[0]}.json`;
    a.click();
    alert('Data berhasil di-backup!');
}

function restoreData() {
    alert('Fitur Restore Data akan segera tersedia.');
}

function setEmployeePreferences() {
    alert('Fitur Set Preferensi Shift akan segera tersedia.');
}

function setTimeZone() {
    alert('Fitur Set Zona Waktu akan segera tersedia.');
}

function notifyManager() {
    alert('Fitur Notify Manager akan segera tersedia.');
}

function notifyEmployeeChange() {
    alert('Fitur Notify Employee Change akan segera tersedia.');
}

function notifyEmployeeAssigned() {
    alert('Fitur Notify Employee Assigned akan segera tersedia.');
}

function toggleSummary() {
    const summaryTables = document.getElementById('summary-tables');
    if (summaryTables) {
        if (summaryTables.style.display === 'none') {
            summaryTables.style.display = 'block';
            updateSummaries();
        } else {
            summaryTables.style.display = 'none';
        }
    }
}

function hideSummary() {
    const summaryTables = document.getElementById('summary-tables');
    if (summaryTables) {
        summaryTables.style.display = 'none';
    }
}

function updateSummaries() {
    // Summary update logic will be implemented here
    console.log('Updating summaries...');
}

function downloadSummary() {
    const format = document.getElementById('download-format')?.value || 'csv';
    alert(`Download ringkasan dalam format ${format.toUpperCase()} akan segera tersedia.`);
}

console.log('Kalender Database Complete Script Loaded Successfully!');
