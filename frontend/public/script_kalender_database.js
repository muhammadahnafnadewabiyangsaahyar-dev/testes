// ============ DROPDOWN UPDATE FUNCTIONS ============
function updateShiftDropdown(shiftsData) {
    const modalShiftSelect = document.getElementById('day-modal-shift-select');
    if (!modalShiftSelect) {
        console.error('Shift dropdown modal not found');
        return;
    }
    
    console.log('updateShiftDropdown - Updating dropdown with data:', shiftsData);
    
    // Clear existing options
    modalShiftSelect.innerHTML = '<option value="">-- Pilih Shift --</option>';
    
    if (!shiftsData || shiftsData.length === 0) {
        const option = document.createElement('option');
        option.value = "";
        option.textContent = "Tidak ada shift tersedia";
        option.disabled = true;
        modalShiftSelect.appendChild(option);
        console.log('No shifts available, showing "Tidak ada shift tersedia"');
        return;
    }
    
    // Populate with shift data
    shiftsData.forEach((shift, index) => {
        const option = document.createElement('option');
        
        // Ensure shift has required fields
        const shiftId = shift.id || `shift-${index}`;
        const namaShift = shift.nama_shift || shift.shift_type || 'Shift';
        const jamMasuk = shift.jam_masuk || '00:00:00';
        const jamKeluar = shift.jam_keluar || '00:00:00';
        
        option.value = shiftId;
        option.textContent = `${namaShift} (${jamMasuk.substring(0, 5)} - ${jamKeluar.substring(0, 5)})`;
        
        // Store metadata
        option.dataset.jamMasuk = jamMasuk;
        option.dataset.jamKeluar = jamKeluar;
        option.dataset.namaShift = namaShift;
        
        modalShiftSelect.appendChild(option);
        console.log(`Added shift option: ${namaShift} (${jamMasuk} - ${jamKeluar})`);
    });
    
    console.log(`Successfully loaded ${shiftsData.length} shifts into dropdown`);
}
/**
 * Kalender Shift Karyawan - Database Integration (Simplified UI Version)
 * Version: 2.0
 * - Removed shift selector dropdown from main UI
 * - Auto-load all shifts per branch
 * - Synchronized summaries with active view
 * - Download and filter capabilities for summaries
 */

// ============ VARIABLES ============
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
    document.getElementById('cabang-select')?.addEventListener('change', async function() {
        const cabangId = this.value || null;
        const cabangName = this.options[this.selectedIndex]?.text || null;
        
        currentCabangId = cabangId;
        currentCabangName = cabangName;
        
        if (cabangId && cabangName) {
            await loadShiftList(cabangName);
            await loadShiftAssignments();
        }
        
        generateCalendar(currentMonth, currentYear);
    });
    
    // Modal close
    document.querySelector('.close')?.addEventListener('click', () => {
        document.getElementById('shift-modal').style.display = 'none';
    });
    
    // Day assign modal close and actions
    document.querySelector('.close-day-assign')?.addEventListener('click', closeDayAssignModal);
    document.getElementById('cancel-day-shift')?.addEventListener('click', closeDayAssignModal);
    document.getElementById('save-day-shift')?.addEventListener('click', saveDayShiftAssignment);
    
    // Select/deselect all pegawai buttons
    document.getElementById('select-all-pegawai')?.addEventListener('click', function(e) {
        e.preventDefault();
        const checkboxes = document.querySelectorAll('.pegawai-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = true;
            const card = cb.closest('.pegawai-card');
            if (card) card.classList.add('selected');
        });
        updateSelectedCount();
    });
    
    document.getElementById('deselect-all-pegawai')?.addEventListener('click', function(e) {
        e.preventDefault();
        const checkboxes = document.querySelectorAll('.pegawai-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = false;
            const card = cb.closest('.pegawai-card');
            if (card) card.classList.remove('selected');
        });
        updateSelectedCount();
    });
    
    // Search pegawai functionality
    document.getElementById('search-pegawai')?.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const cards = document.querySelectorAll('.pegawai-card');
        
        cards.forEach(card => {
            const name = card.dataset.pegawaiName || '';
            if (name.includes(searchTerm)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    });
    
    // Feature buttons
    document.getElementById('export-schedule')?.addEventListener('click', exportSchedule);
    document.getElementById('backup-data')?.addEventListener('click', backupData);
    document.getElementById('restore-data')?.addEventListener('click', restoreData);
    document.getElementById('toggle-summary')?.addEventListener('click', toggleSummary);
    document.getElementById('hide-summary')?.addEventListener('click', hideSummary);
    document.getElementById('download-summary')?.addEventListener('click', downloadSummary);
    
    // Summary filter
    document.getElementById('summary-filter')?.addEventListener('input', filterSummaryByName);
    
    // Summary navigation buttons - FIXED: use correct IDs
    document.getElementById('summary-prev')?.addEventListener('click', navigateSummaryPrevious);
    document.getElementById('summary-next')?.addEventListener('click', navigateSummaryNext);
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const dayAssignModal = document.getElementById('day-assign-modal');
        if (event.target === dayAssignModal) {
            closeDayAssignModal();
        }
    });
}

// ============ DATABASE FUNCTIONS ============
async function loadCabangList() {
    try {
        const response = await fetch('api_shift_calendar.php?action=get_cabang');
        const result = await response.json();
        
        if (result.status === 'success' && result.data) {
            const cabangSelect = document.getElementById('cabang-select');
            if (cabangSelect) {
                cabangSelect.innerHTML = '<option value="">-- Pilih Cabang --</option>';
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

async function loadShiftList(outletName) {
    if (!currentCabangId) {
        console.log('loadShiftList - No cabang selected, skipping');
        return;
    }
    
    try {
        console.log('loadShiftList - Loading shifts for:', {
            cabangId: currentCabangId,
            month: currentMonth + 1,
            year: currentYear
        });
        
        const response = await fetch(`api_shift_calendar.php?action=get_shifts&cabang_id=${currentCabangId}&month=${currentMonth + 1}&year=${currentYear}`);
        const result = await response.json();
        
        console.log('loadShiftList - API response:', result);
        
        if (result.status === 'success' && result.data) {
            shiftList = result.data;
            console.log('✅ Loaded shifts for outlet:', outletName, '- Count:', result.data.length);
            console.log('Shift list data:', shiftList);
            
            // Update dropdown if modal is open
            const modalShiftSelect = document.getElementById('day-modal-shift-select');
            if (modalShiftSelect) {
                updateShiftDropdown(shiftList);
            }
        } else {
            console.error('Failed to load shifts:', result.message);
            shiftList = [];
        }
    } catch (error) {
        console.error('Error loading shifts:', error);
        shiftList = [];
    }
}

async function loadShiftAssignments() {
    // FIXED: Only require currentCabangId - load ALL shifts without needing to select one
    if (!currentCabangId) {
        console.log('loadShiftAssignments - No cabang selected, skipping');
        return;
    }
    
    console.log('loadShiftAssignments - Loading ALL shifts for cabang:', currentCabangId);
    
    // Determine which months to load based on current view
    let monthsToLoad = [];
    
    if (currentView === 'week') {
        // For week view, we need to load the month of the week start and potentially the next month
        const weekStart = new Date(currentDate);
        const day = weekStart.getDay();
        const diff = weekStart.getDate() - day + (day === 0 ? -6 : 1);
        weekStart.setDate(diff);
        
        const weekEnd = new Date(weekStart);
        weekEnd.setDate(weekStart.getDate() + 6);
        
        // Add both months if week spans across months
        const startMonth = `${weekStart.getFullYear()}-${String(weekStart.getMonth() + 1).padStart(2, '0')}`;
        const endMonth = `${weekEnd.getFullYear()}-${String(weekEnd.getMonth() + 1).padStart(2, '0')}`;
        
        monthsToLoad.push(startMonth);
        if (startMonth !== endMonth) {
            monthsToLoad.push(endMonth);
        }
    } else if (currentView === 'day') {
        // For day view, just load the month of current date
        monthsToLoad.push(`${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}`);
    } else {
        // For month view, load current month
        monthsToLoad.push(`${currentYear}-${String(currentMonth + 1).padStart(2, '0')}`);
    }
    
    console.log('Loading shift assignments for months:', monthsToLoad);
    
    try {
        shiftAssignments = {};
        
        // Load data for each month
        for (const month of monthsToLoad) {
            const response = await fetch(`api_shift_calendar.php?action=get_assignments&cabang_id=${currentCabangId}&month=${month}`);
            const result = await response.json();
            
            console.log(`loadShiftAssignments - API response for ${month}:`, result);
            
            if (result.status === 'success' && result.data) {
                console.log(`loadShiftAssignments - Processing ${result.data.length} assignments for ${month}`);
                result.data.forEach(assignment => {
                    const key = `${assignment.tanggal_shift}-${assignment.user_id}`;
                    shiftAssignments[key] = {
                        id: assignment.id,
                        user_id: assignment.user_id,
                        cabang_id: assignment.cabang_id,
                        shift_date: assignment.tanggal_shift,
                        shift_type: assignment.nama_shift,
                        pegawai_name: assignment.nama_lengkap,
                        jam_masuk: assignment.jam_masuk,
                        jam_keluar: assignment.jam_keluar,
                        status_konfirmasi: assignment.status_konfirmasi || 'pending'
                    };
                    console.log(`loadShiftAssignments - Added assignment: ${key}`, shiftAssignments[key]);
                });
            }
        }
        
        console.log('loadShiftAssignments - Final shiftAssignments object:', shiftAssignments);
        generateCalendar(currentMonth, currentYear);
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
    
    if (!calendarBody) return;
    
    // Hide other views first
    const monthView = document.getElementById('month-view');
    const weekView = document.getElementById('week-view');
    const dayView = document.getElementById('day-view');
    const yearView = document.getElementById('year-view');
    
    if (monthView) monthView.style.display = 'block';
    if (weekView) weekView.style.display = 'none';
    if (dayView) dayView.style.display = 'none';
    if (yearView) yearView.style.display = 'none';
    
    calendarBody.innerHTML = '';
    
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    let date = 1;

    for (let i = 0; i < 6; i++) {
        const row = document.createElement('tr');
        let cellsInRow = 0;

        for (let j = 0; j < 7; j++) {
            if (i === 0 && j < firstDay) {
                const cell = document.createElement('td');
                row.appendChild(cell);
            } else if (date > daysInMonth) {
                const cell = document.createElement('td');
                row.appendChild(cell);
            } else {
                const cell = document.createElement('td');
                
                // Store current date value in closure
                const currentDateValue = date;
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(currentDateValue).padStart(2, '0')}`;
                cell.dataset.date = dateStr;
                cell.dataset.day = currentDateValue;
                cell.dataset.month = month;
                cell.dataset.year = year;
                
                // Date number
                const dateDiv = document.createElement('div');
                dateDiv.className = 'date-number';
                dateDiv.textContent = currentDateValue;
                cell.appendChild(dateDiv);
                
                // Display shift assignments for this date
                if (currentCabangId && shiftAssignments) {
                    console.log(`Month view - Checking date ${dateStr}`);
                    Object.keys(shiftAssignments).forEach(key => {
                        const assignment = shiftAssignments[key];
                        if (assignment.shift_date === dateStr) {
                            console.log(`Month view - Found shift on ${dateStr}:`, assignment);
                            const shiftDiv = document.createElement('div');
                            const statusClass = assignment.status_konfirmasi || 'pending';
                            shiftDiv.className = `shift-assignment shift-${assignment.shift_type} status-${statusClass}`;
                            
                            // Add status badge
                            let statusBadge = '';
                            if (statusClass === 'approved') {
                                statusBadge = '<span class="status-badge badge-approved">✓</span>';
                            } else if (statusClass === 'declined') {
                                statusBadge = '<span class="status-badge badge-declined">✗</span>';
                            } else {
                                statusBadge = '<span class="status-badge badge-pending">⏱</span>';
                            }
                            
                            shiftDiv.innerHTML = `${statusBadge} ${assignment.pegawai_name}: ${shiftDetails[assignment.shift_type]?.label || assignment.shift_type}`;
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
                if (currentDateValue === today.getDate() && 
                    year === today.getFullYear() && 
                    month === today.getMonth()) {
                    cell.classList.add('today');
                }

                // Click event - use data attributes to ensure correct date
                cell.addEventListener('click', function() {
                    const clickedDay = parseInt(this.dataset.day);
                    const clickedMonth = parseInt(this.dataset.month);
                    const clickedYear = parseInt(this.dataset.year);
                    
                    currentDate = new Date(clickedYear, clickedMonth, clickedDay);
                    currentMonth = clickedMonth;
                    currentYear = clickedYear;
                    
                    console.log('Clicked date:', currentDate.toLocaleDateString('id-ID'));
                    switchView('day');
                });

                row.appendChild(cell);
                date++;
                cellsInRow++;
            }
        }
        calendarBody.appendChild(row);
        
        // Stop if we've passed all dates and this row had no dates
        if (date > daysInMonth && cellsInRow === 0) {
            break;
        }
    }
    
    // Auto-update summaries when view changes
    updateSummaries();
}

function generateWeekView(date) {
    const weekView = document.getElementById('week-view');
    const weekCalendar = document.getElementById('week-calendar');
    
    if (!weekView || !weekCalendar) return;
    
    // Hide other views first
    const monthView = document.getElementById('month-view');
    const dayView = document.getElementById('day-view');
    const yearView = document.getElementById('year-view');
    
    if (monthView) monthView.style.display = 'none';
    if (dayView) dayView.style.display = 'none';
    if (yearView) yearView.style.display = 'none';
    
    // Show week view
    weekView.style.display = 'block';
    
    // Calculate week start (Monday)
    const weekStart = new Date(date);
    const day = weekStart.getDay();
    const diff = weekStart.getDate() - day + (day === 0 ? -6 : 1);
    weekStart.setDate(diff);
    
    // Clear and rebuild
    const timeColumn = document.getElementById('time-column');
    const daysColumn = document.getElementById('days-column');
    
    // FIXED: Hide time column, integrate time into day columns
    if (timeColumn) {
        timeColumn.style.display = 'none';
    }
    
    if (daysColumn) {
        daysColumn.innerHTML = '';
        const dayNames = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        const HOUR_HEIGHT = 60; // pixels per hour (same as time column)
        
        for (let i = 0; i < 7; i++) {
            const currentDay = new Date(weekStart);
            currentDay.setDate(weekStart.getDate() + i);
            
            const dayColumn = document.createElement('div');
            dayColumn.className = 'day-column';
            dayColumn.style.flex = '1';
            dayColumn.style.borderRight = '1px solid #ddd';
            dayColumn.style.position = 'relative';
            dayColumn.style.minWidth = '140px'; // Wider to accommodate time labels
            
            // REMOVED: day-header element to eliminate UI duplication
            // Navigation date is displayed in current-nav element
            
            // Fix timezone bug: use local date instead of UTC
            const dateStr = `${currentDay.getFullYear()}-${String(currentDay.getMonth() + 1).padStart(2, '0')}-${String(currentDay.getDate()).padStart(2, '0')}`;
            
            // Get all shifts for this day
            let dayShifts = [];
            if (currentCabangId && shiftAssignments) {
                Object.keys(shiftAssignments).forEach(key => {
                    const assignment = shiftAssignments[key];
                    console.log('Week view - Checking:', {
                        assignmentDate: assignment.shift_date,
                        currentDateStr: dateStr,
                        matches: assignment.shift_date === dateStr,
                        assignment: assignment
                    });
                    if (assignment.shift_date === dateStr) {
                        dayShifts.push(assignment);
                    }
                });
            }
            
            console.log(`Week view - Day ${dateStr}: Found ${dayShifts.length} shifts`);
            
            // FIXED: Create container for absolute positioning with background grid
            const dayContent = document.createElement('div');
            dayContent.style.position = 'relative';
            dayContent.style.height = `${24 * HOUR_HEIGHT}px`;
            dayContent.style.cursor = 'pointer';
            dayContent.style.paddingLeft = '50px'; // Space for time labels (smaller in week view)
            
            // FIXED: Create 24 hour background grid with click events and time labels
            for (let hour = 0; hour < 24; hour++) {
                const hourSlot = document.createElement('div');
                hourSlot.className = 'week-hour-slot-bg';
                hourSlot.style.position = 'absolute';
                hourSlot.style.top = `${hour * HOUR_HEIGHT}px`;
                hourSlot.style.height = `${HOUR_HEIGHT}px`;
                hourSlot.style.width = '100%';
                hourSlot.style.borderBottom = '1px solid #e0e0e0';
                hourSlot.style.boxSizing = 'border-box';
                
                // Time label (only show on first column to avoid repetition)
                if (i === 0) {
                    const timeLabel = document.createElement('div');
                    timeLabel.textContent = `${String(hour).padStart(2, '0')}:00`;
                    timeLabel.style.position = 'absolute';
                    timeLabel.style.left = '5px';
                    timeLabel.style.top = '50%';
                    timeLabel.style.transform = 'translateY(-50%)';
                    timeLabel.style.fontSize = '10px';
                    timeLabel.style.fontWeight = '600';
                    timeLabel.style.color = '#666';
                    timeLabel.style.width = '40px';
                    timeLabel.style.pointerEvents = 'none';
                    hourSlot.appendChild(timeLabel);
                }
                
                // FIXED: Add click event to assign shift on specific hour in week view
                hourSlot.addEventListener('click', function(e) {
                    // Only trigger if not clicking on a shift card
                    if (e.target === hourSlot || e.target.tagName === 'DIV') {
                        openDayAssignModal(currentDay, hour);
                    }
                });
                
                // Add hover effect
                hourSlot.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f0f8ff';
                });
                hourSlot.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
                
                dayContent.appendChild(hourSlot);
            }
            
            // Group shifts by type to avoid overlapping
            const shiftGroups = {};
            dayShifts.forEach(assignment => {
                const key = `${assignment.cabang_id}-${assignment.jam_masuk}-${assignment.jam_keluar}`;
                if (!shiftGroups[key]) {
                    shiftGroups[key] = {
                        shift: assignment,
                        employees: []
                    };
                }
                shiftGroups[key].employees.push(assignment);
            });
            
            // FIXED: Convert to array and detect overlaps
            const shiftsArray = Object.values(shiftGroups);
            
            // Sort by start time
            shiftsArray.sort((a, b) => {
                const jamMasukA = a.shift.jam_masuk || '00:00:00';
                const jamMasukB = b.shift.jam_masuk || '00:00:00';
                const startHourA = parseInt(jamMasukA.split(':')[0]) + parseInt(jamMasukA.split(':')[1])/60;
                const startHourB = parseInt(jamMasukB.split(':')[0]) + parseInt(jamMasukB.split(':')[1])/60;
                return startHourA - startHourB;
            });
            
            // Assign column for each shift to avoid overlap
            shiftsArray.forEach((group, index) => {
                const jamMasuk = group.shift.jam_masuk || '00:00:00';
                const jamKeluar = group.shift.jam_keluar || '00:00:00';
                const startHour = parseInt(jamMasuk.split(':')[0]);
                const startMinute = parseInt(jamMasuk.split(':')[1]);
                const endHour = parseInt(jamKeluar.split(':')[0]);
                const endMinute = parseInt(jamKeluar.split(':')[1]);
                
                const shiftStart = startHour + startMinute/60;
                let duration = (endHour + endMinute/60) - (startHour + startMinute/60);
                if (duration <= 0) duration += 24;
                const shiftEnd = shiftStart + duration;
                
                // Find which column this shift should be in
                let column = 0;
                const usedColumns = [];
                
                // Check previous shifts for overlap
                for (let i = 0; i < index; i++) {
                    const prevGroup = shiftsArray[i];
                    const prevJamMasuk = prevGroup.shift.jam_masuk || '00:00:00';
                    const prevJamKeluar = prevGroup.shift.jam_keluar || '00:00:00';
                    const prevStartHour = parseInt(prevJamMasuk.split(':')[0]);
                    const prevStartMinute = parseInt(prevJamMasuk.split(':')[1]);
                    const prevEndHour = parseInt(prevJamKeluar.split(':')[0]);
                    const prevEndMinute = parseInt(prevJamKeluar.split(':')[1]);
                    
                    const prevStart = prevStartHour + prevStartMinute/60;
                    let prevDuration = (prevEndHour + prevEndMinute/60) - (prevStartHour + prevStartMinute/60);
                    if (prevDuration <= 0) prevDuration += 24;
                    const prevEnd = prevStart + prevDuration;
                    
                    // Check if overlaps
                    if (shiftStart < prevEnd && shiftEnd > prevStart) {
                        usedColumns.push(prevGroup.column || 0);
                    }
                }
                
                // Find first available column
                while (usedColumns.includes(column)) {
                    column++;
                }
                
                group.column = column;
            });
            
            // Update totalColumns for all shifts
            const maxColumns = Math.max(...shiftsArray.map(s => s.column !== undefined ? s.column + 1 : 1));
            shiftsArray.forEach(group => {
                group.totalColumns = maxColumns;
            });
            
            // FIXED: Create shift cards with absolute positioning and stretching
            shiftsArray.forEach(group => {
                const assignment = group.shift;
                const jamMasuk = assignment.jam_masuk || '00:00:00';
                const jamKeluar = assignment.jam_keluar || '00:00:00';
                const startHour = parseInt(jamMasuk.split(':')[0]);
                const startMinute = parseInt(jamMasuk.split(':')[1]);
                const endHour = parseInt(jamKeluar.split(':')[0]);
                const endMinute = parseInt(jamKeluar.split(':')[1]);
                
                // Calculate position and duration
                const topPosition = (startHour + startMinute/60) * HOUR_HEIGHT;
                let duration = (endHour + endMinute/60) - (startHour + startMinute/60);
                if (duration <= 0) duration += 24; // Handle overnight shifts
                const cardHeight = duration * HOUR_HEIGHT - 4; // -4px for margin
                
                const statusClass = assignment.status_konfirmasi || 'pending';
                
                const shiftName = assignment.shift_type || assignment.nama_shift || 'Shift';
                const jamMasukDisplay = jamMasuk.substring(0, 5);
                const jamKeluarDisplay = jamKeluar.substring(0, 5);
                
                let statusColor = '#ff9800';
                if (statusClass === 'approved') {
                    statusColor = '#4CAF50';
                } else if (statusClass === 'declined') {
                    statusColor = '#f44336';
                }
                
                const shiftCard = document.createElement('div');
                shiftCard.style.position = 'absolute';
                shiftCard.style.top = `${topPosition}px`;
                shiftCard.style.height = `${cardHeight}px`;
                // FIXED: Use column-based positioning to avoid overlap with time label offset
                const columnWidth = 100 / group.totalColumns;
                const leftPercent = (group.column || 0) * columnWidth;
                const widthPercent = columnWidth;
                shiftCard.style.left = `calc(50px + ${leftPercent}%)`; // Offset for time labels
                shiftCard.style.width = `calc(${widthPercent}% - ${i === 0 ? '50px' : '0px'})`; // Only first column has time labels
                shiftCard.style.boxSizing = 'border-box';
                shiftCard.style.backgroundColor = statusColor;
                shiftCard.style.color = 'white';
                shiftCard.style.padding = '4px';
                shiftCard.style.margin = '2px';
                shiftCard.style.borderRadius = '3px';
                shiftCard.style.fontSize = '10px';
                shiftCard.style.fontWeight = 'bold';
                shiftCard.style.overflow = 'auto';
                shiftCard.style.boxShadow = '0 2px 4px rgba(0,0,0,0.2)';
                shiftCard.style.zIndex = '10';
                shiftCard.style.cursor = 'pointer';
                
                // Build employee list
                let employeeNames = group.employees.map(e => e.pegawai_name || e.nama_lengkap || 'Unknown').join(', ');
                if (employeeNames.length > 30) {
                    employeeNames = employeeNames.substring(0, 30) + `... (+${group.employees.length - 1})`;
                }
                
                shiftCard.innerHTML = `
                    <div style="font-weight: bold; margin-bottom: 2px;">${shiftName}</div>
                    <div style="font-size: 9px; opacity: 0.9; margin-bottom: 2px;">${jamMasukDisplay}-${jamKeluarDisplay}</div>
                    <div style="font-size: 9px; opacity: 0.9;">${employeeNames}</div>
                `;
                
                shiftCard.title = `${shiftName} (${jamMasukDisplay}-${jamKeluarDisplay})\nPegawai: ${group.employees.map(e => e.pegawai_name || e.nama_lengkap).join(', ')}`;
                
                dayContent.appendChild(shiftCard);
            });
            
            dayColumn.appendChild(dayContent);
            daysColumn.appendChild(dayColumn);
        }
    }
    
    // Auto-update summaries when view changes
    updateSummaries();
}

function generateDayView(date) {
    const dayView = document.getElementById('day-view');
    const dayCalendar = document.getElementById('day-calendar');
    
    if (!dayView || !dayCalendar) return;
    
    // Hide other views first
    const monthView = document.getElementById('month-view');
    const weekView = document.getElementById('week-view');
    const yearView = document.getElementById('year-view');
    
    if (monthView) monthView.style.display = 'none';
    if (weekView) weekView.style.display = 'none';
    if (yearView) yearView.style.display = 'none';
    
    // Show day view
    dayView.style.display = 'block';
    
    const dayTimeColumn = document.getElementById('day-time-column');
    const dayContent = document.getElementById('day-content');
    // Fix timezone bug: use local date instead of UTC
    const dateStr = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    
    console.log('Day view - Looking for shifts on:', dateStr);
    console.log('Day view - All shiftAssignments:', shiftAssignments);
    
    // Collect all shifts for this day
    let dayShifts = [];
    if (shiftAssignments) {
        Object.keys(shiftAssignments).forEach(key => {
            const assignment = shiftAssignments[key];
            console.log('Day view - Checking:', {
                key: key,
                assignmentDate: assignment.shift_date,
                currentDateStr: dateStr,
                matches: assignment.shift_date === dateStr,
                assignment: assignment
            });
            if (assignment.shift_date === dateStr) {
                dayShifts.push(assignment);
            }
        });
    }
    
    console.log(`Day view - Found ${dayShifts.length} shifts for ${dateStr}`);
    
    if (dayTimeColumn && dayContent) {
        // FIXED: Hide time column, integrate time labels into main content
        dayTimeColumn.style.display = 'none';
        
        dayContent.innerHTML = '';
        
        // REMOVED: contentHeader (day-header) element to eliminate UI duplication
        // Navigation date is displayed in current-nav element
        
        // Add instruction if no cabang selected
        if (!currentCabangId) {
            const instruction = document.createElement('div');
            instruction.style.padding = '20px';
            instruction.style.textAlign = 'center';
            instruction.style.color = '#ff9800';
            instruction.style.backgroundColor = '#fff3e0';
            instruction.style.borderRadius = '8px';
            instruction.style.margin = '20px';
            instruction.innerHTML = '<strong>ℹ️ Pilih cabang terlebih dahulu untuk melihat dan assign shift!</strong>';
            dayContent.appendChild(instruction);
            return;
        }
        
        // FIXED: Calculate shift duration and positioning for stretching cards
        // Group all shifts by their start time to position cards correctly
        const shiftsGroupedByStart = {};
        dayShifts.forEach(assignment => {
            const jamMasuk = assignment.jam_masuk || '00:00:00';
            const jamKeluar = assignment.jam_keluar || '00:00:00';
            const startHour = parseInt(jamMasuk.split(':')[0]);
            const startMinute = parseInt(jamMasuk.split(':')[1]);
            const endHour = parseInt(jamKeluar.split(':')[0]);
            const endMinute = parseInt(jamKeluar.split(':')[1]);
            
            // Calculate duration in hours (decimal)
            let duration = (endHour + endMinute/60) - (startHour + startMinute/60);
            if (duration <= 0) duration += 24; // Handle overnight shifts
            
            const key = `${assignment.cabang_id}-${assignment.jam_masuk}-${assignment.jam_keluar}`;
            if (!shiftsGroupedByStart[key]) {
                shiftsGroupedByStart[key] = {
                    shift: assignment,
                    employees: [],
                    startHour: startHour,
                    startMinute: startMinute,
                    duration: duration
                };
            }
            shiftsGroupedByStart[key].employees.push(assignment);
        });
        
        const HOUR_HEIGHT = 60; // pixels per hour
        
        // Create a container for absolute positioning
        const contentContainer = document.createElement('div');
        contentContainer.style.position = 'relative';
        contentContainer.style.height = `${24 * HOUR_HEIGHT}px`;
        contentContainer.style.paddingLeft = '70px'; // Space for time labels
        
        // FIXED: Generate 24-hour time slots with integrated time labels
        for (let hour = 0; hour < 24; hour++) {
            // Content slot for this hour (background grid with time label)
            const contentSlot = document.createElement('div');
            contentSlot.className = 'day-content-slot-bg';
            contentSlot.style.height = `${HOUR_HEIGHT}px`;
            contentSlot.style.borderBottom = '1px solid #e0e0e0';
            contentSlot.style.boxSizing = 'border-box';
            contentSlot.style.position = 'absolute';
            contentSlot.style.top = `${hour * HOUR_HEIGHT}px`;
            contentSlot.style.left = '0';
            contentSlot.style.right = '0';
            contentSlot.style.width = '100%';
            contentSlot.style.cursor = 'pointer';
            contentSlot.style.transition = 'background-color 0.2s';
            
            // Time label (positioned in left area)
            const timeLabel = document.createElement('div');
            timeLabel.textContent = `${String(hour).padStart(2, '0')}:00`;
            timeLabel.style.position = 'absolute';
            timeLabel.style.left = '10px';
            timeLabel.style.top = '0';
            timeLabel.style.lineHeight = `${HOUR_HEIGHT}px`;
            timeLabel.style.fontSize = '12px';
            timeLabel.style.fontWeight = '600';
            timeLabel.style.color = '#666';
            timeLabel.style.width = '50px';
            timeLabel.style.textAlign = 'right';
            timeLabel.style.paddingRight = '10px';
            timeLabel.style.pointerEvents = 'none'; // Don't block clicks
            contentSlot.appendChild(timeLabel);
            
            // Content area (where shifts will be placed)
            const contentArea = document.createElement('div');
            contentArea.style.position = 'absolute';
            contentArea.style.left = '70px';
            contentArea.style.top = '0';
            contentArea.style.right = '0';
            contentArea.style.bottom = '0';
            contentArea.style.borderLeft = '2px solid #ddd';
            contentArea.style.backgroundColor = 'rgba(0, 0, 0, 0.01)'; // Very subtle background
            contentArea.style.pointerEvents = 'none'; // Allow click-through to contentSlot
            contentSlot.appendChild(contentArea);
            
            // Add hover effect
            contentSlot.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f5f5f5';
            });
            contentSlot.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
            
            // Add click event to open assign modal
            contentSlot.addEventListener('click', function(e) {
                // Only trigger if not clicking on a shift card
                const isCardClick = e.target.classList.contains('day-shift') || 
                                   e.target.closest('.day-shift');
                if (!isCardClick) {
                    openDayAssignModal(date, hour);
                }
            });
            
            contentContainer.appendChild(contentSlot);
        }
        
        // FIXED: Detect overlapping cards and create columns
        const shiftsArray = Object.values(shiftsGroupedByStart);
        
        // Sort by start time
        shiftsArray.sort((a, b) => {
            const aStart = a.startHour + a.startMinute/60;
            const bStart = b.startHour + b.startMinute/60;
            return aStart - bStart;
        });
        
        // Assign column for each shift to avoid overlap
        shiftsArray.forEach((shift, index) => {
            const shiftStart = shift.startHour + shift.startMinute/60;
            const shiftEnd = shiftStart + shift.duration;
            
            // Find which column this shift should be in
            let column = 0;
            const usedColumns = [];
            
            // Check previous shifts for overlap
            for (let i = 0; i < index; i++) {
                const prevShift = shiftsArray[i];
                const prevStart = prevShift.startHour + prevShift.startMinute/60;
                const prevEnd = prevStart + prevShift.duration;
                
                // Check if overlaps
                if (shiftStart < prevEnd && shiftEnd > prevStart) {
                    usedColumns.push(prevShift.column || 0);
                }
            }
            
            // Find first available column
            while (usedColumns.includes(column)) {
                column++;
            }
            
            shift.column = column;
            shift.totalColumns = Math.max(...shiftsArray.map(s => s.column !== undefined ? s.column + 1 : 1));
        });
        
        // Update totalColumns for all shifts
        const maxColumns = Math.max(...shiftsArray.map(s => s.column !== undefined ? s.column + 1 : 1));
        shiftsArray.forEach(shift => {
            shift.totalColumns = maxColumns;
        });
        
        // Create shift cards with absolute positioning and stretching
        shiftsArray.forEach(group => {
            const firstAssignment = group.shift;
            
            // Determine overall status
            const statuses = group.employees.map(e => e.status_konfirmasi || 'pending');
            const hasApproved = statuses.includes('approved');
            const hasDeclined = statuses.includes('declined');
            const statusClass = hasApproved ? 'approved' : (hasDeclined ? 'declined' : 'pending');
            const isApproved = hasApproved;
            
            // Color based on status
            let bgColor = '#f0f8ff';
            let borderColor = '#2196F3';
            if (isApproved) {
                bgColor = '#e8f5e9';
                borderColor = '#4CAF50';
            } else if (statusClass === 'declined') {
                bgColor = '#ffebee';
                borderColor = '#f44336';
            }
            
            // Calculate position and height
            const topPosition = (group.startHour + group.startMinute/60) * HOUR_HEIGHT;
            const cardHeight = group.duration * HOUR_HEIGHT - 4; // -4px for small gap
            
            // FIXED: Calculate left and width based on column to avoid overlap
            // Position card in the content area (after time labels)
            const TIME_LABEL_WIDTH = 70; // pixels
            
            // Calculate width per column
            const totalColumns = group.totalColumns || 1;
            const columnIndex = group.column || 0;
            
            const shiftDiv = document.createElement('div');
            shiftDiv.className = 'day-shift';
            shiftDiv.style.backgroundColor = bgColor;
            shiftDiv.style.padding = '8px';
            shiftDiv.style.margin = '2px';
            shiftDiv.style.borderLeft = `4px solid ${borderColor}`;
            shiftDiv.style.borderRadius = '4px';
            shiftDiv.style.position = 'absolute';
            shiftDiv.style.top = `${topPosition}px`;
            shiftDiv.style.height = `${cardHeight}px`;
            // FIXED: Simpler calculation - left starts from TIME_LABEL_WIDTH + offset for column
            shiftDiv.style.left = TIME_LABEL_WIDTH + 'px';
            shiftDiv.style.right = '2px';
            // If multiple columns, adjust width
            if (totalColumns > 1) {
                const widthPercent = (100 / totalColumns);
                shiftDiv.style.width = `calc(${widthPercent}% - 4px)`;
                shiftDiv.style.left = `calc(${TIME_LABEL_WIDTH}px + ${columnIndex * widthPercent}%)`;
            } else {
                shiftDiv.style.width = `calc(100% - ${TIME_LABEL_WIDTH + 4}px)`;
            }
            shiftDiv.style.boxSizing = 'border-box';
            shiftDiv.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
            shiftDiv.style.zIndex = '10';
            shiftDiv.style.overflow = 'auto';
            shiftDiv.style.pointerEvents = 'auto'; // Allow clicking on card
            
            // Status badge
            let statusBadge = '';
            let statusText = '';
            if (isApproved) {
                statusBadge = '✓';
                statusText = 'Approved';
            } else if (statusClass === 'declined') {
                statusBadge = '✗';
                statusText = 'Declined';
            } else {
                statusBadge = '⏱';
                statusText = 'Pending';
            }
            
            // Build employee list
            let employeeList = '';
            group.employees.forEach((emp, index) => {
                const empStatus = emp.status_konfirmasi || 'pending';
                let empIcon = '⏱';
                if (empStatus === 'approved') empIcon = '✓';
                else if (empStatus === 'declined') empIcon = '✗';
                
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
                        <div style="color: ${borderColor}; font-weight: bold; font-size: 14px; margin-bottom: 4px;">
                            ${firstAssignment.nama_shift || firstAssignment.shift_type || 'Shift'}
                        </div>
                        <div style="color: #666; font-size: 12px; margin-bottom: 8px;">
                            ⏰ ${firstAssignment.jam_masuk ? firstAssignment.jam_masuk.substring(0, 5) : '00:00'} - ${firstAssignment.jam_keluar ? firstAssignment.jam_keluar.substring(0, 5) : '00:00'}
                        </div>
                    </div>
                    <span class="status-badge badge-${statusClass}" style="font-size: 11px; padding: 3px 8px; border-radius: 3px; white-space: nowrap;">
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
            
            contentContainer.appendChild(shiftDiv);
        });
        
        dayContent.appendChild(contentContainer);
        
        // Show info if no shifts assigned
        if (dayShifts.length === 0) {
            const noShiftInfo = document.createElement('div');
            noShiftInfo.style.padding = '20px';
            noShiftInfo.style.textAlign = 'center';
            noShiftInfo.style.color = '#666';
            noShiftInfo.style.backgroundColor = '#f5f5f5';
            noShiftInfo.style.borderRadius = '8px';
            noShiftInfo.style.margin = '20px';
            noShiftInfo.style.gridColumn = '1 / -1';
            noShiftInfo.innerHTML = '<strong>📅 Belum ada shift yang di-assign untuk hari ini</strong><br><small>Klik pada jam di sebelah kiri untuk assign shift</small>';
            dayContent.appendChild(noShiftInfo);
        }
        
        // Add instruction at bottom
        const clickInstruction = document.createElement('div');
        clickInstruction.style.padding = '15px';
        clickInstruction.style.textAlign = 'center';
        clickInstruction.style.color = '#4CAF50';
        clickInstruction.style.backgroundColor = '#e8f5e9';
        clickInstruction.style.borderRadius = '8px';
        clickInstruction.style.margin = '20px';
        clickInstruction.style.fontSize = '14px';
        clickInstruction.style.gridColumn = '1 / -1';
        clickInstruction.innerHTML = '<strong>💡 Tip:</strong> Klik pada waktu di sebelah kiri untuk assign shift ke pegawai';
        dayContent.appendChild(clickInstruction);
    }
    
    // Auto-update summaries when view changes
    updateSummaries();
}

function generateYearView(year) {
    const yearView = document.getElementById('year-view');
    const yearGrid = document.getElementById('year-grid');
    
    if (!yearView || !yearGrid) return;
    
    // Hide other views first
    const monthView = document.getElementById('month-view');
    const weekView = document.getElementById('week-view');
    const dayView = document.getElementById('day-view');
    
    if (monthView) monthView.style.display = 'none';
    if (weekView) weekView.style.display = 'none';
    if (dayView) dayView.style.display = 'none';
    
    // Show year view
    yearView.style.display = 'block';
    
    yearGrid.innerHTML = '';
    
    // Add year header
    // REMOVED: yearHeader element to eliminate UI duplication
    // Navigation year is displayed in current-nav element
    
    for (let month = 0; month < 12; month++) {
        const monthDiv = document.createElement('div');
        monthDiv.className = 'month-mini';
        
        const monthTitle = document.createElement('h4');
        monthTitle.textContent = monthNames[month];
        monthDiv.appendChild(monthTitle);
        
        const miniCalendar = document.createElement('div');
        miniCalendar.className = 'mini-calendar-grid';
        miniCalendar.style.display = 'grid';
        miniCalendar.style.gridTemplateColumns = 'repeat(7, 1fr)';
        miniCalendar.style.gap = '2px';
        miniCalendar.style.fontSize = '11px';
        
        // Add day headers
        const dayHeaders = ['M', 'S', 'S', 'R', 'K', 'J', 'S'];
        dayHeaders.forEach(day => {
            const headerCell = document.createElement('div');
            headerCell.style.textAlign = 'center';
            headerCell.style.fontWeight = 'bold';
            headerCell.textContent = day;
            miniCalendar.appendChild(headerCell);
        });
        
        // Mini calendar for each month
        const firstDay = new Date(year, month, 1).getDay();
        const adjustedFirstDay = firstDay === 0 ? 6 : firstDay - 1; // Adjust for Monday start
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
            dayCell.style.textAlign = 'center';
            dayCell.style.padding = '2px';
            dayCell.style.cursor = 'pointer';
            dayCell.style.borderRadius = '3px';
            
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
    
    // Auto-update summaries when view changes
    updateSummaries();
}

// ============ VIEW SWITCHING ============
function switchView(view) {
    currentView = view;
    
    // Update active button
    document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById(`view-${view}`)?.classList.add('active');
    
    // FIX: Only require currentCabangId, not currentShiftId
    // This allows loading all shifts for the cabang when switching from month view
    if (currentCabangId) {
        console.log(`Switching to ${view} view, reloading shift assignments for cabang ${currentCabangId}`);
        loadShiftAssignments();
    } else {
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

// ============ FEATURE FUNCTIONS ============
function exportSchedule() {
    if (!currentCabangName) {
        alert('❌ Pilih cabang terlebih dahulu!');
        return;
    }
    
    let csv = 'Tanggal,Karyawan,Shift,Jam Masuk,Jam Keluar,Cabang\n';
    
    if (shiftAssignments && typeof shiftAssignments === 'object') {
        for (const dateKey in shiftAssignments) {
            const assignments = shiftAssignments[dateKey];
            if (Array.isArray(assignments)) {
                assignments.forEach(assignment => {
                    csv += `${dateKey},${assignment.nama_pegawai || 'N/A'},${assignment.nama_shift || 'N/A'},${assignment.jam_masuk || 'N/A'},${assignment.jam_keluar || 'N/A'},${currentCabangName}\n`;
                });
            }
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
    
    // Get all view containers
    const monthView = document.getElementById('month-view');
    const weekView = document.getElementById('week-view');
    const dayView = document.getElementById('day-view');
    const yearView = document.getElementById('year-view');
    
    if (summaryTables) {
        if (summaryTables.style.display === 'none' || summaryTables.style.display === '') {
            // Show summary, hide ALL calendar views and navigation
            summaryTables.style.display = 'block';
            
            // Hide calendar view container
            if (calendarView) calendarView.style.display = 'none';
            
            // Hide navigation
            if (navigation) navigation.style.display = 'none';
            
            // Hide all individual view containers
            if (monthView) monthView.style.display = 'none';
            if (weekView) weekView.style.display = 'none';
            if (dayView) dayView.style.display = 'none';
            if (yearView) yearView.style.display = 'none';
            
            // Update summaries based on current view and date range
            updateSummaries();
        } else {
            // Hide summary, show calendar
            summaryTables.style.display = 'none';
            
            // Show calendar view container
            if (calendarView) calendarView.style.display = 'block';
            
            // Show navigation
            if (navigation) navigation.style.display = 'flex';
            
            // Show the current view based on currentView variable
            if (currentView === 'month' && monthView) {
                monthView.style.display = 'block';
            } else if (currentView === 'week' && weekView) {
                weekView.style.display = 'block';
            } else if (currentView === 'day' && dayView) {
                dayView.style.display = 'block';
            } else if (currentView === 'year' && yearView) {
                yearView.style.display = 'block';
            }
        }
    }
}

function hideSummary() {
    const summaryTables = document.getElementById('summary-tables');
    const calendarView = document.getElementById('calendar-view');
    const navigation = document.getElementById('navigation');
    
    // Get all view containers
    const monthView = document.getElementById('month-view');
    const weekView = document.getElementById('week-view');
    const dayView = document.getElementById('day-view');
    const yearView = document.getElementById('year-view');
    
    // Hide summary
    if (summaryTables) {
        summaryTables.style.display = 'none';
    }
    
    // Show calendar view container
    if (calendarView) {
        calendarView.style.display = 'block';
    }
    
    // Show navigation
    if (navigation) {
        navigation.style.display = 'flex';
    }
    
    // Show the current view based on currentView variable
    if (currentView === 'month' && monthView) {
        monthView.style.display = 'block';
        if (weekView) weekView.style.display = 'none';
        if (dayView) dayView.style.display = 'none';
        if (yearView) yearView.style.display = 'none';
    } else if (currentView === 'week' && weekView) {
        weekView.style.display = 'block';
        if (monthView) monthView.style.display = 'none';
        if (dayView) dayView.style.display = 'none';
        if (yearView) yearView.style.display = 'none';
    } else if (currentView === 'day' && dayView) {
        dayView.style.display = 'block';
        if (monthView) monthView.style.display = 'none';
        if (weekView) weekView.style.display = 'none';
        if (yearView) yearView.style.display = 'none';
    } else if (currentView === 'year' && yearView) {
        yearView.style.display = 'block';
        if (monthView) monthView.style.display = 'none';
        if (weekView) weekView.style.display = 'none';
        if (dayView) dayView.style.display = 'none';
    }
}

// ============ SUMMARY HELPER FUNCTIONS ============
function getDateRangeForCurrentView() {
    let startDate, endDate;
    
    if (currentView === 'month') {
        // First and last day of current month
        startDate = new Date(currentYear, currentMonth, 1);
        endDate = new Date(currentYear, currentMonth + 1, 0);
    } else if (currentView === 'week') {
        // Start of week (Monday) to end of week (Sunday)
        const weekStart = new Date(currentDate);
        const day = weekStart.getDay();
        const diff = weekStart.getDate() - day + (day === 0 ? -6 : 1);
        weekStart.setDate(diff);
        
        startDate = new Date(weekStart);
        endDate = new Date(weekStart);
        endDate.setDate(startDate.getDate() + 6);
    } else if (currentView === 'day') {
        // Just the current day
        startDate = new Date(currentDate);
        endDate = new Date(currentDate);
    } else if (currentView === 'year') {
        // First and last day of current year
        startDate = new Date(currentYear, 0, 1);
        endDate = new Date(currentYear, 11, 31);
    }
    
    // Format as YYYY-MM-DD
    const formatDate = (date) => {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    };
    
    return {
        start: formatDate(startDate),
        end: formatDate(endDate),
        startDate: startDate,
        endDate: endDate
    };
}

function getViewRangeName() {
    if (currentView === 'month') {
        return `${monthNames[currentMonth]} ${currentYear}`;
    } else if (currentView === 'week') {
        const weekStart = new Date(currentDate);
        const day = weekStart.getDay();
        const diff = weekStart.getDate() - day + (day === 0 ? -6 : 1);
        weekStart.setDate(diff);
        const weekEnd = new Date(weekStart);
        weekEnd.setDate(weekStart.getDate() + 6);
        
        return `${weekStart.getDate()} ${monthNames[weekStart.getMonth()]} - ${weekEnd.getDate()} ${monthNames[weekEnd.getMonth()]} ${weekEnd.getFullYear()}`;
    } else if (currentView === 'day') {
        return `${currentDate.getDate()} ${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
    } else if (currentView === 'year') {
        return `Tahun ${currentYear}`;
    }
    return '';
}

function calculateEmployeeSummary(dateRange) {
    console.log('calculateEmployeeSummary - dateRange:', dateRange);
    console.log('calculateEmployeeSummary - shiftAssignments:', shiftAssignments);
    
    const employeeData = {};
    
    // FIXED: Process shiftAssignments correctly
    if (shiftAssignments && typeof shiftAssignments === 'object') {
        Object.keys(shiftAssignments).forEach(key => {
            const assignment = shiftAssignments[key];
            const assignmentDate = assignment.shift_date;
            
            // Check if assignment is within date range
            if (assignmentDate >= dateRange.start && assignmentDate <= dateRange.end) {
                const pegawaiName = assignment.nama_lengkap || assignment.pegawai_name || 'Unknown';
                const pegawaiId = assignment.user_id || assignment.pegawai_id;
                
                if (!employeeData[pegawaiId]) {
                    employeeData[pegawaiId] = {
                        id: pegawaiId,
                        name: pegawaiName,
                        totalShifts: 0,
                        totalHours: 0,
                        workDays: new Set(),
                        offDays: 0
                    };
                }
                
                employeeData[pegawaiId].totalShifts++;
                
                // Calculate hours from jam_masuk and jam_keluar
                if (assignment.jam_masuk && assignment.jam_keluar) {
                    const jamMasuk = assignment.jam_masuk;
                    const jamKeluar = assignment.jam_keluar;
                    
                    const startHour = parseInt(jamMasuk.split(':')[0]);
                    const startMinute = parseInt(jamMasuk.split(':')[1]);
                    const endHour = parseInt(jamKeluar.split(':')[0]);
                    const endMinute = parseInt(jamKeluar.split(':')[1]);
                    
                    let duration = (endHour + endMinute/60) - (startHour + startMinute/60);
                    if (duration <= 0) duration += 24; // Handle overnight shifts
                    
                    employeeData[pegawaiId].totalHours += duration;
                }
                
                // Track unique work days
                if (assignment.shift_type !== 'off' && assignment.nama_shift !== 'Off') {
                    employeeData[pegawaiId].workDays.add(assignmentDate);
                } else {
                    employeeData[pegawaiId].offDays++;
                }
            }
        });
    }
    
    // Convert to array and format
    const result = Object.values(employeeData).map(emp => ({
        name: emp.name,
        totalShifts: emp.totalShifts,
        totalHours: Math.round(emp.totalHours * 10) / 10, // Round to 1 decimal
        workDays: emp.workDays.size,
        offDays: emp.offDays
    }));
    
    // Sort by name
    result.sort((a, b) => a.name.localeCompare(b.name));
    
    console.log('calculateEmployeeSummary - result:', result);
    return result;
}

function calculateShiftSummary(dateRange) {
    console.log('calculateShiftSummary - dateRange:', dateRange);
    
    const shiftData = {};
    
    // FIXED: Process shiftAssignments correctly
    if (shiftAssignments && typeof shiftAssignments === 'object') {
        Object.keys(shiftAssignments).forEach(key => {
            const assignment = shiftAssignments[key];
            const assignmentDate = assignment.shift_date;
            
            // Check if assignment is within date range
            if (assignmentDate >= dateRange.start && assignmentDate <= dateRange.end) {
                const shiftName = assignment.nama_shift || assignment.shift_type || 'Unknown';
                
                if (!shiftData[shiftName]) {
                    shiftData[shiftName] = 0;
                }
                
                shiftData[shiftName]++;
            }
        });
    }
    
    console.log('calculateShiftSummary - result:', shiftData);
    return shiftData;
}

function updateSummaryDisplay(rangeName, employeeSummary, shiftSummary) {
    console.log('updateSummaryDisplay - employeeSummary:', employeeSummary);
    console.log('updateSummaryDisplay - shiftSummary:', shiftSummary);
    
    // Update employee summary table
    const employeeBody = document.getElementById('employee-summary-body');
    if (employeeBody) {
        if (employeeSummary.length === 0) {
            employeeBody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #999;">Tidak ada data shift untuk periode ini</td></tr>';
        } else {
            employeeBody.innerHTML = '';
            employeeSummary.forEach(emp => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${emp.name}</td>
                    <td style="text-align: center;">${emp.totalShifts}</td>
                    <td style="text-align: center;">${emp.totalHours}</td>
                    <td style="text-align: center;">${emp.workDays}</td>
                    <td style="text-align: center;">${emp.offDays}</td>
                `;
                employeeBody.appendChild(row);
            });
        }
    }
    
    // Update shift summary table
    const shiftBody = document.getElementById('shift-summary-body');
    if (shiftBody) {
        if (Object.keys(shiftSummary).length === 0) {
            shiftBody.innerHTML = '<tr><td colspan="2" style="text-align: center; padding: 20px; color: #999;">Tidak ada data shift untuk periode ini</td></tr>';
        } else {
            shiftBody.innerHTML = '';
            
            // Sort by shift name
            const sortedShifts = Object.entries(shiftSummary).sort((a, b) => a[0].localeCompare(b[0]));
            
            sortedShifts.forEach(([shiftName, count]) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${shiftName}</td>
                    <td style="text-align: center;">${count}</td>
                `;
                shiftBody.appendChild(row);
            });
        }
    }
}

function updateSummaries() {
    console.log('Updating summaries for view:', currentView);
    
    // Check if cabang is selected
    if (!currentCabangName) {
        updateSummaryDisplay('Pilih cabang terlebih dahulu', [], {});
        return;
    }
    
    // Get date range based on current view
    let dateRange = getDateRangeForCurrentView();
    let rangeName = getViewRangeName();
    
    // Update summary title
    const currentSummary = document.getElementById('current-summary');
    if (currentSummary) {
        currentSummary.textContent = `Ringkasan ${rangeName} - ${currentCabangName}`;
        currentSummary.style.display = 'block';
    }
    
    // Calculate summaries based on date range
    const employeeSummary = calculateEmployeeSummary(dateRange);
    const shiftSummary = calculateShiftSummary(dateRange);
    
    // Update display
    updateSummaryDisplay(rangeName, employeeSummary, shiftSummary);
    
    // Update summary navigation labels
    updateSummaryNavigationLabels();
}

function updateSummaryNavigationLabels() {
    const summaryCurrentNav = document.getElementById('summary-current-nav');
    const summaryPrevLabel = document.getElementById('summary-prev-label');
    const summaryNextLabel = document.getElementById('summary-next-label');
    
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
    // Navigate using the same logic as calendar navigation
    navigatePrevious();
    
    // Update summaries for the new period
    updateSummaries();
}

function navigateSummaryNext() {
    // Navigate using the same logic as calendar navigation
    navigateNext();
    
    // Update summaries for the new period
    updateSummaries();
}

// ============ DATABASE FUNCTIONS (CONT'D) ============
async function loadPegawaiForDayAssign() {
    if (!currentCabangName) {
        alert('❌ Pilih cabang terlebih dahulu!');
        return;
    }
    
    try {
        const response = await fetch(`api_shift_calendar.php?action=get_pegawai&outlet=${encodeURIComponent(currentCabangName)}`);
        const result = await response.json();
        
        if (result.status === 'success' && result.data) {
            const container = document.getElementById('pegawai-cards-container');
            if (container) {
                container.innerHTML = '';
                
                // Store pegawai data globally for search functionality
                window.pegawaiData = result.data;
                
                if (result.data.length === 0) {
                    container.innerHTML = '<p style="text-align: center; padding: 20px; color: #999;">Tidak ada pegawai di cabang ini</p>';
                } else {
                    result.data.forEach(pegawai => {
                        const card = createPegawaiCard(pegawai);
                        container.appendChild(card);
                    });
                }
                
                updateSelectedCount();
            }
        } else {
            console.error('Failed to load pegawai:', result.message);
            const container = document.getElementById('pegawai-cards-container');
            if (container) {
                container.innerHTML = '<p style="text-align: center; padding: 20px; color: #f44336;">Error memuat data pegawai</p>';
            }
        }
    } catch (error) {
        console.error('Error loading pegawai for day assign:', error);
        const container = document.getElementById('pegawai-cards-container');
        if (container) {
            container.innerHTML = '<p style="text-align: center; padding: 20px; color: #f44336;">Error memuat data pegawai</p>';
        }
    }
}

function createPegawaiCard(pegawai) {
    const card = document.createElement('div');
    card.className = 'pegawai-card';
    card.dataset.pegawaiId = pegawai.id;
    card.dataset.pegawaiName = (pegawai.name || pegawai.nama_lengkap || '').toLowerCase();
    
    // Check if pegawai already has shift on this date
    const modal = document.getElementById('day-assign-modal');
    const date = modal?.dataset.date;
    
    console.log('createPegawaiCard - Checking shift for pegawai:', pegawai.id, 'date:', date);
    console.log('createPegawaiCard - shiftAssignments type:', typeof shiftAssignments, 'value:', shiftAssignments);
    
    const shiftAssignment = checkIfPegawaiHasShift(pegawai.id, date);
    const hasShift = !!shiftAssignment;
    
    // Check if shift is locked (approved, izin, sakit, or reschedule)
    let isLocked = false;
    let lockReason = '';
    if (shiftAssignment) {
        const status = shiftAssignment.status_konfirmasi || 'pending';
        if (status === 'approved' || status === 'izin' || status === 'sakit' || status === 'reschedule') {
            isLocked = true;
            lockReason = status === 'approved' ? 'Approved' : 
                        status === 'izin' ? 'Izin' : 
                        status === 'sakit' ? 'Sakit' : 'Reschedule';
        }
    }
    
    if (hasShift) {
        card.classList.add('has-shift');
        if (isLocked) {
            card.classList.add('shift-locked');
        }
    }
    
    const displayName = pegawai.name || pegawai.nama_lengkap || 'Tidak ada nama';
    const displayPosisi = pegawai.posisi || '-';
    const displayOutlet = pegawai.outlet || '-';
    
    // Build shift badge
    let shiftBadge = '';
    if (hasShift) {
        if (isLocked) {
            shiftBadge = `<div class="pegawai-card-badge badge-locked">🔒 ${lockReason}</div>`;
        } else {
            shiftBadge = '<div class="pegawai-card-badge">Sudah punya shift</div>';
        }
    }
    
    card.innerHTML = `
        <input type="checkbox" class="pegawai-checkbox" data-pegawai-id="${pegawai.id}" ${hasShift ? 'checked' : ''} ${isLocked ? 'disabled' : ''}>
        <div class="pegawai-card-content">
            <div class="pegawai-card-name">${displayName}</div>
            <div class="pegawai-card-info">${displayPosisi} • ${displayOutlet}</div>
            ${shiftBadge}
        </div>
    `;
    
    // Toggle selection on card click
    card.addEventListener('click', function(e) {
        // Don't allow interaction with locked shifts
        if (isLocked) {
            return;
        }
        
        if (e.target.type !== 'checkbox') {
            const checkbox = card.querySelector('.pegawai-checkbox');
            checkbox.checked = !checkbox.checked;
            toggleCardSelection(card, checkbox.checked);
        } else {
            toggleCardSelection(card, e.target.checked);
        }
        updateSelectedCount();
    });
    
    return card;
}

function toggleCardSelection(card, isSelected) {
    if (isSelected) {
        card.classList.add('selected');
    } else {
        card.classList.remove('selected');
    }
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.pegawai-checkbox:checked');
    const count = checkboxes.length;
    
    const selectedCountEl = document.getElementById('selected-count');
    const saveCountEl = document.getElementById('save-count');
    
    if (selectedCountEl) {
        selectedCountEl.querySelector('strong').textContent = count;
    }
    
    if (saveCountEl) {
        saveCountEl.textContent = count;
    }
}

function checkIfPegawaiHasShift(pegawaiId, date) {
    // Enhanced validation: check if shiftAssignments exists and is an object
    if (!date || !shiftAssignments || typeof shiftAssignments !== 'object') {
        console.log('checkIfPegawaiHasShift - Invalid params:', { date, shiftAssignments: typeof shiftAssignments });
        return null;
    }
    
    // shiftAssignments is an object with keys like "2024-11-05-123"
    // Find assignment that matches this pegawai and date
    try {
        const assignments = Object.values(shiftAssignments);
        const assignment = assignments.find(assignment => 
            assignment.user_id == pegawaiId && 
            assignment.shift_date === date
        );
        
        // Return assignment details if found, or null
        return assignment || null;
    } catch (error) {
        console.error('checkIfPegawaiHasShift - Error:', error);
        return null;
    }
}

function openDayAssignModal(date, hour) {
    // FIXED: Only require cabang, shift will be selected in modal
    if (!currentCabangId) {
        alert('❌ Pilih cabang terlebih dahulu!');
        return;
    }
    
    const modal = document.getElementById('day-assign-modal');
    const modalDate = document.getElementById('day-modal-date');
    const modalCabang = document.getElementById('day-modal-cabang');
    const modalShiftSelect = document.getElementById('day-modal-shift-select');
    
    if (!modal || !modalDate || !modalCabang) return;
    
    // Format tanggal
    const dateStr = date.toLocaleDateString('id-ID', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    modalDate.textContent = dateStr;
    modalCabang.textContent = currentCabangName;
    
    // Store data for saving - Fix timezone bug: use local date instead of UTC
    modal.dataset.date = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    modal.dataset.hour = hour;
    
    // Load shift list first, then populate dropdown
    loadShiftList(currentCabangName).then(() => {
        // FIXED: Populate shift dropdown from shiftList
        updateShiftDropdown(shiftList);
        
        // Load pegawai list
        loadPegawaiForDayAssign();
        
        // Show modal
        modal.style.display = 'block';
    });
}

function closeDayAssignModal() {
    const modal = document.getElementById('day-assign-modal');
    if (modal) {
        modal.style.display = 'none';
        
        // Reset form
        const checkboxes = document.querySelectorAll('.pegawai-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = false;
            const card = cb.closest('.pegawai-card');
            if (card) card.classList.remove('selected');
        });
        
        document.getElementById('search-pegawai').value = '';
        updateSelectedCount();
    }
}

async function saveDayShiftAssignment() {
    const modal = document.getElementById('day-assign-modal');
    const date = modal?.dataset.date;
    const shiftSelect = document.getElementById('day-modal-shift-select');
    const selectedShiftId = shiftSelect?.value;
    
    // FIXED: Validate shift selection
    if (!selectedShiftId) {
        alert('❌ Pilih shift terlebih dahulu!');
        return;
    }
    
    // FIXED: Validate date
    if (!date) {
        alert('❌ Tanggal tidak valid!');
        return;
    }
    
    // Get all checkboxes (both checked and unchecked)
    const allCheckboxes = document.querySelectorAll('.pegawai-checkbox:not([disabled])');
    const selectedCheckboxes = document.querySelectorAll('.pegawai-checkbox:checked:not([disabled])');
    
    // Prepare assignments for checked pegawai
    const assignments = Array.from(selectedCheckboxes).map(cb => {
        const pegawaiId = cb.dataset.pegawaiId;
        return {
            user_id: pegawaiId,
            cabang_id: selectedShiftId,
            tanggal_shift: date
        };
    });
    
    // Prepare cancellations for unchecked pegawai who had shifts
    const cancellations = [];
    allCheckboxes.forEach(cb => {
        const pegawaiId = cb.dataset.pegawaiId;
        const isChecked = cb.checked;
        const shiftAssignment = checkIfPegawaiHasShift(pegawaiId, date);
        
        // If pegawai had shift but is now unchecked, mark for cancellation
        if (shiftAssignment && !isChecked) {
            // Find the assignment ID
            const assignmentKey = Object.keys(shiftAssignments).find(key => {
                const assignment = shiftAssignments[key];
                return assignment.user_id == pegawaiId && assignment.shift_date === date;
            });
            
            if (assignmentKey) {
                cancellations.push({
                    assignment_id: shiftAssignments[assignmentKey].id,
                    user_id: pegawaiId,
                    shift_date: date
                });
            }
        }
    });
    
    // Show confirmation if there are cancellations
    if (cancellations.length > 0) {
        const confirmMsg = `⚠️ Anda akan membatalkan shift untuk ${cancellations.length} pegawai.\nApakah Anda yakin?`;
        if (!confirm(confirmMsg)) {
            return;
        }
    }
    
    try {
        // First, handle cancellations (delete shifts)
        if (cancellations.length > 0) {
            for (const cancellation of cancellations) {
                const deleteResponse = await fetch('api_shift_calendar.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete',
                        id: cancellation.assignment_id
                    })
                });
                
                const deleteResult = await deleteResponse.json();
                if (deleteResult.status !== 'success') {
                    console.error('Failed to delete shift:', cancellation, deleteResult);
                }
            }
        }
        
        // Then, handle new assignments
        if (assignments.length > 0) {
            const response = await fetch('api_shift_calendar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'assign_shifts',
                    cabang_id: currentCabangId,
                    assignments: assignments
                })
            });
            
            const result = await response.json();
            console.log('saveDayShiftAssignment - API response:', result);
            
            if (result.status === 'success') {
                alert(`✅ Shift berhasil disimpan!\n- Ditambahkan: ${assignments.length}\n- Dibatalkan: ${cancellations.length}`);
                closeDayAssignModal();
                loadShiftAssignments();
            } else {
                alert('❌ Gagal menyimpan shift: ' + (result.message || 'Unknown error'));
            }
        } else if (cancellations.length > 0) {
            // Only cancellations, no new assignments
            alert(`✅ Shift berhasil dibatalkan untuk ${cancellations.length} pegawai`);
            closeDayAssignModal();
            loadShiftAssignments();
        } else {
            alert('ℹ️ Tidak ada perubahan shift');
        }
    } catch (error) {
        console.error('Error saving day shift assignment:', error);
        alert('❌ Terjadi kesalahan saat menyimpan shift');
    }
}

// ============ CSV EXPORT FUNCTIONS ============
function downloadSummary() {
    const dateRange = getDateRangeForCurrentView();
    const rangeName = getViewRangeName();
    const format = document.getElementById('download-format')?.value || 'csv';
    
    const employeeSummary = calculateEmployeeSummary(dateRange);
    const shiftSummary = calculateShiftSummary(dateRange);
    
    let content, filename, mimeType;
    
    if (format === 'txt') {
        content = generateTXTContent(employeeSummary, shiftSummary, rangeName);
        filename = `ringkasan_shift_${currentCabangName}_${rangeName.replace(/\s+/g, '_')}.txt`;
        mimeType = 'text/plain;charset=utf-8;';
    } else {
        content = generateCSVContent(dateRange, rangeName);
        filename = `ringkasan_shift_${currentCabangName}_${rangeName.replace(/\s+/g, '_')}.csv`;
        mimeType = 'text/csv;charset=utf-8;';
    }
    
    // Create and download file
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
    
    alert('✅ Ringkasan berhasil diunduh!');
}

function generateCSVContent(dateRange, rangeName) {
    let csv = 'Nama Pegawai,Total Shift,Total Jam,Hari Kerja,Hari Off\n';
    
    const employeeSummary = calculateEmployeeSummary(dateRange);
    const shiftSummary = calculateShiftSummary(dateRange);
    
    // Employee summary
    employeeSummary.forEach(emp => {
        csv += `"${emp.name}","${emp.totalShifts}","${emp.totalHours}","${emp.workDays}","${emp.offDays}"\n`;
    });
    
    csv += '\n"RINGKASAN PER JENIS SHIFT"\n';
    csv += '"Nama Shift","Jumlah"\n';
    for (const shiftName in shiftSummary) {
        csv += `"${shiftName}","${shiftSummary[shiftName]}"\n`;
    }
    
    return csv;
}

function generateTXTContent(employeeSummary, shiftSummary, rangeName) {
    let txt = `RINGKASAN SHIFT - ${currentCabangName}\n`;
    txt += `Periode: ${rangeName}\n`;
    txt += `Tanggal Download: ${new Date().toLocaleString('id-ID')}\n`;
    txt += `${'='.repeat(80)}\n\n`;
    
    // Employee summary
    txt += 'RINGKASAN PER PEGAWAI\n';
    txt += `${'─'.repeat(80)}\n`;
    txt += sprintf('%-40s %12s %12s %10s %10s\n', 'Nama Pegawai', 'Total Shift', 'Total Jam', 'Hari Kerja', 'Hari Off');
    txt += `${'─'.repeat(80)}\n`;
    employeeSummary.forEach(emp => {
        txt += sprintf('%-40s %12d %12d %10d %10d\n', 
            emp.name, emp.totalShifts, emp.totalHours, emp.workDays, emp.offDays);
    });
    
    txt += `\n${'='.repeat(80)}\n\n`;
    
    // Shift summary
    txt += 'RINGKASAN PER JENIS SHIFT\n';
    txt += `${'─'.repeat(50)}\n`;
    txt += sprintf('%-40s %10s\n', 'Nama Shift', 'Jumlah');
    txt += `${'─'.repeat(50)}\n`;
    for (const shiftName in shiftSummary) {
        txt += sprintf('%-40s %10d\n', shiftName, shiftSummary[shiftName]);
    }
    
    return txt;
}

// Helper function for formatted text output
function sprintf(format, ...args) {
    let i = 0;
    return format.replace(/%([-]?)(\d+)?([sd])/g, (match, leftAlign, width, type) => {
        const arg = args[i++];
        let str = type === 'd' ? String(Math.floor(arg)) : String(arg);
        const padding = width ? (parseInt(width) - str.length) : 0;
        
        if (padding > 0) {
            const pad = ' '.repeat(padding);
            str = leftAlign === '-' ? str + pad : pad + str;
        }
        
        return str;
    });
}

function filterSummaryByName() {
    const filterInput = document.getElementById('summary-filter');
    if (!filterInput) return;
    
    const filterValue = filterInput.value.toLowerCase().trim();
    const employeeTableBody = document.getElementById('employee-summary-body');
    
    if (!employeeTableBody) return;
    
    const rows = employeeTableBody.getElementsByTagName('tr');
    
    for (let row of rows) {
        const nameCell = row.getElementsByTagName('td')[0];
        if (nameCell) {
            const nameText = nameCell.textContent.toLowerCase();
            if (nameText.includes(filterValue)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    }
    
    console.log('✅ Summary filtered by:', filterValue);
}


console.log('Kalender Database Complete Script Loaded Successfully!');
