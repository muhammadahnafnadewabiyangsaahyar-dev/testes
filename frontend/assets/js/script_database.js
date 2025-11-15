// Global variables
let currentCabangId = null;
let currentUsers = [];
let currentShifts = {};
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();
let currentView = 'month';
let currentDate = new Date();

// Initialize application
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
    setupEventListeners();
});

// Initialize the application
async function initializeApp() {
    try {
        showLoading(true);
        await loadCabangOptions();
        generateCalendar(currentMonth, currentYear);
        updateNavigation();
    } catch (error) {
        console.error('Error initializing app:', error);
        showError('Gagal memuat data aplikasi');
    } finally {
        showLoading(false);
    }
}

// Load cabang options
async function loadCabangOptions() {
    try {
        const response = await fetch('api_kalender.php?action=get_cabang');
        
        // Check if response is OK
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        // Get response text first to debug
        const text = await response.text();
        console.log('Raw response:', text);
        
        // Parse JSON
        let data;
        try {
            data = JSON.parse(text);
        } catch (jsonError) {
            console.error('JSON Parse Error:', jsonError);
            console.error('Response text:', text);
            throw new Error('Invalid JSON response from server');
        }
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        if (data.cabang) {
            const select = document.getElementById('cabang-select');
            select.innerHTML = '<option value="">-- Pilih Cabang --</option>';
            
            data.cabang.forEach(cabang => {
                const option = document.createElement('option');
                option.value = cabang.id;
                option.textContent = cabang.nama;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading cabang:', error);
        showError('Gagal memuat data cabang: ' + error.message);
    }
}

// Load users for selected cabang
async function loadUsers(cabangId) {
    try {
        const response = await fetch(`api_kalender.php?action=get_users&cabang_id=${cabangId}`);
        const data = await response.json();
        
        currentUsers = data.users || [];
        
        const select = document.getElementById('employee-select');
        select.innerHTML = '<option value="">-- Pilih Karyawan --</option>';
        
        if (currentUsers.length > 0) {
            select.disabled = false;
            currentUsers.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = user.name;
                select.appendChild(option);
            });
        } else {
            select.disabled = true;
        }
        
        // Enable shift select if users available
        document.getElementById('shift-select').disabled = currentUsers.length === 0;
        
    } catch (error) {
        console.error('Error loading users:', error);
        showError('Gagal memuat data karyawan');
    }
}

// Load shift data for current month/year
async function loadShiftData(cabangId, month, year) {
    if (!cabangId) return;
    
    try {
        const response = await fetch(`api_kalender.php?action=get_shifts&cabang_id=${cabangId}&month=${month + 1}&year=${year}`);
        const data = await response.json();
        
        // Convert array to object for easy lookup
        currentShifts = {};
        if (data.shifts) {
            data.shifts.forEach(shift => {
                const key = `${shift.date}-${shift.user_id}`;
                currentShifts[key] = shift;
            });
        }
        
        generateCalendar(month, year);
    } catch (error) {
        console.error('Error loading shifts:', error);
        showError('Gagal memuat data shift');
    }
}

// Generate calendar based on current view
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
}

// Generate month view
function generateMonthView(month, year) {
    const calendarBody = document.getElementById('calendar-body');
    const monthYear = document.getElementById('month-year');
    
    calendarBody.innerHTML = '';
    const firstDay = new Date(year, month).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    let date = 1;

    // Update month and year display
    const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                       'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    if (monthYear) {
        monthYear.textContent = `${monthNames[month]} ${year}`;
    }

    // Generate calendar rows
    for (let i = 0; i < 6; i++) {
        const row = document.createElement('tr');

        for (let j = 0; j < 7; j++) {
            if (i === 0 && j < firstDay) {
                // Empty cells before first day
                const cell = document.createElement('td');
                cell.innerHTML = '';
                row.appendChild(cell);
            } else if (date > daysInMonth) {
                // Stop after last day
                break;
            } else {
                const cell = document.createElement('td');
                cell.innerHTML = `<div class="date-number">${date}</div>`;
                cell.classList.add('calendar-day');
                
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(date).padStart(2, '0')}`;
                cell.dataset.date = dateStr;

                // Add shift indicators
                if (currentCabangId) {
                    const shiftContainer = document.createElement('div');
                    shiftContainer.classList.add('shift-container');
                    
                    currentUsers.forEach(user => {
                        const shiftKey = `${dateStr}-${user.id}`;
                        const shift = currentShifts[shiftKey];
                        
                        if (shift) {
                            const shiftElement = document.createElement('div');
                            shiftElement.classList.add('shift-indicator');
                            shiftElement.classList.add(`shift-${shift.shift_type}`);
                            shiftElement.textContent = `${user.name}: ${shift.shift_type.toUpperCase()}`;
                            shiftElement.title = `${user.name} - ${shift.shift_label}`;
                            shiftContainer.appendChild(shiftElement);
                        }
                    });
                    
                    cell.appendChild(shiftContainer);
                }

                // Highlight today
                const today = new Date();
                if (date === today.getDate() && year === today.getFullYear() && month === today.getMonth()) {
                    cell.classList.add('today');
                }

                // Add click event
                cell.addEventListener('click', () => {
                    if (currentCabangId) {
                        openShiftModal(dateStr);
                    } else {
                        showError('Pilih cabang terlebih dahulu');
                    }
                });

                row.appendChild(cell);
                date++;
            }
        }
        
        calendarBody.appendChild(row);
    }
}

// Open shift assignment modal
function openShiftModal(dateStr) {
    const modal = document.getElementById('shift-modal');
    const modalDate = document.getElementById('modal-date');
    const modalEmployee = document.getElementById('modal-employee');
    const modalShift = document.getElementById('modal-shift');

    modalDate.textContent = dateStr;
    
    const selectedEmployeeId = document.getElementById('employee-select').value;
    if (selectedEmployeeId) {
        const user = currentUsers.find(u => u.id == selectedEmployeeId);
        modalEmployee.textContent = user ? user.name : 'Tidak diketahui';
        
        // Check existing shift for this user and date
        const shiftKey = `${dateStr}-${selectedEmployeeId}`;
        const existingShift = currentShifts[shiftKey];
        modalShift.value = existingShift ? existingShift.shift_type : '';
    } else {
        modalEmployee.textContent = 'Tidak ada karyawan dipilih';
        modalShift.value = '';
    }

    modal.style.display = 'block';
    
    // Store current context for saving
    modal.dataset.date = dateStr;
    modal.dataset.userId = selectedEmployeeId;
}

// Save shift assignment
async function saveShift() {
    const modal = document.getElementById('shift-modal');
    const dateStr = modal.dataset.date;
    const userId = modal.dataset.userId;
    const shiftType = document.getElementById('modal-shift').value;
    
    if (!userId) {
        showError('Pilih karyawan terlebih dahulu');
        return;
    }
    
    if (!shiftType) {
        showError('Pilih jenis shift');
        return;
    }
    
    try {
        showLoading(true);
        
        const response = await fetch('api_kalender.php?action=save_shift', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                user_id: parseInt(userId),
                date: dateStr,
                shift_type: shiftType
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            modal.style.display = 'none';
            await loadShiftData(currentCabangId, currentMonth, currentYear);
            showSuccess('Shift berhasil disimpan');
        } else {
            showError(result.error || 'Gagal menyimpan shift');
        }
    } catch (error) {
        console.error('Error saving shift:', error);
        showError('Terjadi kesalahan saat menyimpan shift');
    } finally {
        showLoading(false);
    }
}

// Quick assign shift (for assign shift button)
async function quickAssignShift() {
    const cabangId = document.getElementById('cabang-select').value;
    const userId = document.getElementById('employee-select').value;
    const shiftType = document.getElementById('shift-select').value;
    
    if (!cabangId) {
        showError('Pilih cabang terlebih dahulu');
        return;
    }
    
    if (!userId) {
        showError('Pilih karyawan terlebih dahulu');
        return;
    }
    
    if (!shiftType) {
        showError('Pilih jenis shift');
        return;
    }
    
    const dateStr = prompt('Masukkan tanggal (YYYY-MM-DD):');
    if (!dateStr) return;
    
    try {
        showLoading(true);
        
        const response = await fetch('api_kalender.php?action=save_shift', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                user_id: parseInt(userId),
                date: dateStr,
                shift_type: shiftType
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            await loadShiftData(currentCabangId, currentMonth, currentYear);
            showSuccess('Shift berhasil disimpan');
        } else {
            showError(result.error || 'Gagal menyimpan shift');
        }
    } catch (error) {
        console.error('Error saving shift:', error);
        showError('Terjadi kesalahan saat menyimpan shift');
    } finally {
        showLoading(false);
    }
}

// Export schedule to CSV
function exportSchedule() {
    if (!currentCabangId) {
        showError('Pilih cabang terlebih dahulu');
        return;
    }
    
    let csv = 'Tanggal,Karyawan,Shift Type,Jam Masuk,Jam Keluar\n';
    
    Object.values(currentShifts).forEach(shift => {
        csv += `${shift.date},${shift.user_name},${shift.shift_type},${shift.shift_masuk},${shift.shift_keluar}\n`;
    });
    
    if (csv === 'Tanggal,Karyawan,Shift Type,Jam Masuk,Jam Keluar\n') {
        showError('Tidak ada data shift untuk diekspor');
        return;
    }
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `jadwal_shift_${currentMonth + 1}_${currentYear}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    
    showSuccess('Jadwal berhasil diekspor');
}

// Navigation functions
function navigatePrev() {
    if (currentView === 'month') {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        loadShiftData(currentCabangId, currentMonth, currentYear);
        updateNavigation();
    }
}

function navigateNext() {
    if (currentView === 'month') {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        loadShiftData(currentCabangId, currentMonth, currentYear);
        updateNavigation();
    }
}

function updateNavigation() {
    const currentNav = document.getElementById('current-nav');
    if (currentNav && currentView === 'month') {
        const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                           'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        currentNav.textContent = `${monthNames[currentMonth]} ${currentYear}`;
    }
}

// Setup all event listeners
function setupEventListeners() {
    // Cabang selection
    document.getElementById('cabang-select').addEventListener('change', async function() {
        const cabangId = this.value;
        currentCabangId = cabangId;
        
        if (cabangId) {
            showLoading(true);
            try {
                await loadUsers(cabangId);
                await loadShiftData(cabangId, currentMonth, currentYear);
            } finally {
                showLoading(false);
            }
        } else {
            currentUsers = [];
            currentShifts = {};
            document.getElementById('employee-select').innerHTML = '<option value="">-- Pilih Karyawan --</option>';
            document.getElementById('employee-select').disabled = true;
            document.getElementById('shift-select').disabled = true;
            generateCalendar(currentMonth, currentYear);
        }
    });
    
    // Navigation buttons
    document.getElementById('prev-nav').addEventListener('click', navigatePrev);
    document.getElementById('next-nav').addEventListener('click', navigateNext);
    
    // Action buttons
    document.getElementById('assign-shift').addEventListener('click', quickAssignShift);
    document.getElementById('export-schedule').addEventListener('click', exportSchedule);
    document.getElementById('refresh-data').addEventListener('click', () => {
        if (currentCabangId) {
            loadShiftData(currentCabangId, currentMonth, currentYear);
        }
    });
    
    // Modal events
    document.getElementById('save-shift').addEventListener('click', saveShift);
    document.querySelector('.close').addEventListener('click', () => {
        document.getElementById('shift-modal').style.display = 'none';
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', (event) => {
        const modal = document.getElementById('shift-modal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}

// Utility functions for UI feedback
function showLoading(show) {
    // Simple loading indicator - you can improve this
    if (show) {
        document.body.style.cursor = 'wait';
    } else {
        document.body.style.cursor = 'default';
    }
}

function showError(message) {
    alert('Error: ' + message);
}

function showSuccess(message) {
    alert('Success: ' + message);
}

// Placeholder functions for other views (can be implemented later)
function generateWeekView(date) {
    console.log('Week view not implemented yet');
}

function generateDayView(date) {
    console.log('Day view not implemented yet');
}

function generateYearView(year) {
    console.log('Year view not implemented yet');
}
