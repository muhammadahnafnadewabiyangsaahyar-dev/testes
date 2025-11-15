// =============================================
// HYBRID SCRIPT: ORIGINAL FEATURES + DATABASE
// =============================================
// Menggabungkan SEMUA fitur original dengan database integration

// ============ ORIGINAL VARIABLES ============
let scheduleData = JSON.parse(localStorage.getItem('scheduleData')) || {};
let employees = JSON.parse(localStorage.getItem('employees')) || ['Karyawan 1', 'Karyawan 2', 'Karyawan 3'];
let holidays = JSON.parse(localStorage.getItem('holidays')) || [];
let employeePreferences = JSON.parse(localStorage.getItem('employeePreferences')) || {};
let timeZone = localStorage.getItem('timeZone') || 'Asia/Jakarta';

// ============ DATABASE VARIABLES ============
let currentCabangId = null;
let dbUsers = [];
let dbShifts = {};
let useDatabaseMode = false; // Toggle antara localStorage dan database

// ============ COMMON VARIABLES ============
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();
let currentView = 'month';
let currentDate = new Date();

// ============ ORIGINAL SHIFT DETAILS ============
const shiftDetails = {
    'Shift 1: Pagi': { hours: 8, start: '08:00', end: '16:00' },
    'Shift 2: Siang': { hours: 8, start: '16:00', end: '00:00' },
    'Shift 3: Malam': { hours: 8, start: '00:00', end: '08:00' },
    'Off': { hours: 0, start: '-', end: '-' }
};

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// ============ INITIALIZATION ============
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
    setupAllEventListeners();
    updateEmployeeSelect();
    generateCalendar(currentMonth, currentYear);
    updateSummaries();
});

async function initializeApp() {
    // Load cabang untuk database mode
    await loadCabangOptions();
}

// ============ DATABASE INTEGRATION ============
async function loadCabangOptions() {
    try {
        const response = await fetch('api_kalender.php?action=get_cabang');
        if (!response.ok) return;
        
        const text = await response.text();
        const data = JSON.parse(text);
        
        if (data.cabang && data.cabang.length > 0) {
            const select = document.getElementById('cabang-select');
            if (select) {
                select.innerHTML = '<option value="">-- Mode LocalStorage (Original) --</option>';
                data.cabang.forEach(cabang => {
                    const option = document.createElement('option');
                    option.value = cabang.id;
                    option.textContent = `Database: ${cabang.nama}`;
                    select.appendChild(option);
                });
            }
        }
    } catch (error) {
        console.log('Database tidak tersedia, menggunakan localStorage mode');
    }
}

async function loadDatabaseUsers(cabangId) {
    try {
        const response = await fetch(`api_kalender.php?action=get_users&cabang_id=${cabangId}`);
        const data = await response.json();
        dbUsers = data.users || [];
        
        // Update employee select dengan database users
        const select = document.getElementById('employee-select');
        select.innerHTML = '<option value="">-- Pilih Karyawan --</option>';
        dbUsers.forEach(user => {
            const option = document.createElement('option');
            option.value = user.id;
            option.textContent = user.name;
            option.dataset.dbUser = 'true';
            select.appendChild(option);
        });
    } catch (error) {
        console.error('Error loading database users:', error);
    }
}

async function loadDatabaseShifts(cabangId, month, year) {
    try {
        const response = await fetch(`api_kalender.php?action=get_shifts&cabang_id=${cabangId}&month=${month + 1}&year=${year}`);
        const data = await response.json();
        
        dbShifts = {};
        if (data.shifts) {
            data.shifts.forEach(shift => {
                const key = `${shift.date}-${shift.user_id}`;
                dbShifts[key] = shift;
            });
        }
        generateCalendar(month, year);
    } catch (error) {
        console.error('Error loading database shifts:', error);
    }
}

async function saveDatabaseShift(userId, date, shiftType) {
    try {
        const response = await fetch('api_kalender.php?action=save_shift', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: parseInt(userId), date: date, shift_type: shiftType })
        });
        const result = await response.json();
        if (result.success) {
            await loadDatabaseShifts(currentCabangId, currentMonth, currentYear);
            return true;
        }
        return false;
    } catch (error) {
        console.error('Error saving database shift:', error);
        return false;
    }
}

// ============ ORIGINAL CALENDAR GENERATION ============
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

function generateMonthView(month, year) {
    const calendarBody = document.getElementById('calendar-body');
    const monthYear = document.getElementById('month-year');
    calendarBody.innerHTML = '';
    const firstDay = new Date(year, month).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    let date = 1;

    if (monthYear) {
        monthYear.textContent = `${monthNames[month]} ${year}`;
    }

    for (let i = 0; i < 6; i++) {
        const row = document.createElement('tr');

        for (let j = 0; j < 7; j++) {
            if (i === 0 && j < firstDay) {
                const cell = document.createElement('td');
                cell.innerHTML = '';
                row.appendChild(cell);
            } else if (date > daysInMonth) {
                break;
            } else {
                const cell = document.createElement('td');
                cell.innerHTML = date;
                cell.classList.add('calendar-day');
                cell.dataset.date = `${year}-${String(month + 1).padStart(2, '0')}-${String(date).padStart(2, '0')}`;

                const dateKey = cell.dataset.date;

                // ============ DISPLAY SHIFTS ============
                if (useDatabaseMode && currentCabangId) {
                    // Database mode: show shifts from database
                    dbUsers.forEach(user => {
                        const shiftKey = `${dateKey}-${user.id}`;
                        const shift = dbShifts[shiftKey];
                        if (shift) {
                            cell.innerHTML += `<br><small style="background-color: #4caf50; color: white; padding: 2px 4px; border-radius: 3px;">${user.name}: ${shift.shift_type}</small>`;
                        }
                    });
                } else {
                    // LocalStorage mode: original behavior
                    if (scheduleData[dateKey]) {
                        const shift = scheduleData[dateKey].shift;
                        const employee = scheduleData[dateKey].employee;
                        const details = shiftDetails[shift];
                        cell.innerHTML += `<br><small>${shift} (${employee})<br>${details.start} - ${details.end}</small>`;
                    }
                }

                // Highlight holidays
                if (holidays.includes(dateKey)) {
                    cell.classList.add('holiday');
                }

                // Highlight today
                if (date === new Date().getDate() && year === new Date().getFullYear() && month === new Date().getMonth()) {
                    cell.classList.add('today');
                }

                // Click event: switch to day view
                cell.addEventListener('click', () => {
                    const [year, month, day] = dateKey.split('-').map(Number);
                    currentDate = new Date(year, month - 1, day);
                    switchView('day');
                });

                row.appendChild(cell);
                date++;
            }
        }
        calendarBody.appendChild(row);
    }
}

// ============ ORIGINAL MODAL FUNCTIONS ============
function openModal(dateKey, cell) {
    const modal = document.getElementById('shift-modal');
    const modalDate = document.getElementById('modal-date');
    const modalEmployee = document.getElementById('modal-employee');
    const modalShift = document.getElementById('modal-shift');
    const saveButton = document.getElementById('save-shift');

    modalDate.textContent = dateKey;
    const selectedEmployee = document.getElementById('employee-select').value;
    
    if (useDatabaseMode) {
        const user = dbUsers.find(u => u.id == selectedEmployee);
        modalEmployee.textContent = user ? user.name : 'Tidak ada karyawan dipilih';
        
        // Check existing database shift
        const shiftKey = `${dateKey}-${selectedEmployee}`;
        const existingShift = dbShifts[shiftKey];
        modalShift.value = existingShift ? existingShift.shift_type : '';
    } else {
        modalEmployee.textContent = selectedEmployee || 'Tidak ada karyawan dipilih';
        if (scheduleData[dateKey]) {
            modalShift.value = scheduleData[dateKey].shift;
        } else {
            modalShift.value = '';
        }
    }

    modal.style.display = 'block';
    modal.dataset.date = dateKey;
    modal.dataset.employee = selectedEmployee;

    saveButton.onclick = async () => {
        if (!selectedEmployee) {
            alert('Pilih karyawan terlebih dahulu!');
            return;
        }
        if (!modalShift.value) {
            alert('Pilih shift!');
            return;
        }

        if (useDatabaseMode) {
            // Save to database
            const success = await saveDatabaseShift(selectedEmployee, dateKey, modalShift.value);
            if (success) {
                modal.style.display = 'none';
            } else {
                alert('Gagal menyimpan ke database');
            }
        } else {
            // Save to localStorage (original)
            scheduleData[dateKey] = {
                employee: selectedEmployee,
                shift: modalShift.value
            };
            localStorage.setItem('scheduleData', JSON.stringify(scheduleData));
            generateCalendar(currentMonth, currentYear);
            updateSummaries();
            modal.style.display = 'none';
        }
    };
}

document.querySelector('.close').addEventListener('click', () => {
    document.getElementById('shift-modal').style.display = 'none';
});

// ============ ORIGINAL FEATURES: ADD EMPLOYEE ============
document.getElementById('add-employee')?.addEventListener('click', () => {
    const newEmployee = prompt('Masukkan nama karyawan baru:');
    if (newEmployee && !employees.includes(newEmployee)) {
        employees.push(newEmployee);
        localStorage.setItem('employees', JSON.stringify(employees));
        updateEmployeeSelect();
        updateSummaries();
    }
});

// ============ ORIGINAL FEATURES: EXPORT CSV ============
document.getElementById('export-schedule')?.addEventListener('click', () => {
    let csv = 'Tanggal,Karyawan,Shift\n';
    
    if (useDatabaseMode) {
        Object.values(dbShifts).forEach(shift => {
            csv += `${shift.date},${shift.user_name},${shift.shift_type}\n`;
        });
    } else {
        Object.keys(scheduleData).forEach(date => {
            const data = scheduleData[date];
            csv += `${date},${data.employee},${data.shift}\n`;
        });
    }
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'jadwal_shift.csv';
    a.click();
});

// ============ ORIGINAL FEATURES: ALL BUTTONS ============
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

function updateEmployeeSelect() {
    const select = document.getElementById('employee-select');
    if (!useDatabaseMode) {
        select.innerHTML = '<option value="">-- Pilih Karyawan --</option>';
        employees.forEach(emp => {
            const option = document.createElement('option');
            option.value = emp;
            option.textContent = emp;
            select.appendChild(option);
        });
    }
}

// ============ ORIGINAL FEATURES: SUMMARIES ============
function updateSummaries() {
    const employeeSummaryBody = document.getElementById('employee-summary-body');
    const shiftSummaryBody = document.getElementById('shift-summary-body');
    const filterInput = document.getElementById('summary-filter');

    if (!employeeSummaryBody || !shiftSummaryBody) return;

    employeeSummaryBody.innerHTML = '';
    shiftSummaryBody.innerHTML = '';

    let startDate, endDate;
    if (currentView === 'month') {
        startDate = new Date(currentYear, currentMonth, 1);
        endDate = new Date(currentYear, currentMonth + 1, 0);
    } else if (currentView === 'week') {
        startDate = new Date(currentDate);
        startDate.setDate(currentDate.getDate() - currentDate.getDay() + 1);
        endDate = new Date(startDate);
        endDate.setDate(startDate.getDate() + 6);
    } else if (currentView === 'day') {
        startDate = new Date(currentDate);
        endDate = new Date(currentDate);
    } else if (currentView === 'year') {
        startDate = new Date(currentYear, 0, 1);
        endDate = new Date(currentYear, 11, 31);
    }

    const employeeStats = {};
    const shiftStats = {};

    employees.forEach(emp => {
        employeeStats[emp] = { shifts: 0, hours: 0, workDays: 0, offDays: 0 };
    });

    Object.keys(shiftDetails).forEach(shift => {
        shiftStats[shift] = 0;
    });

    for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
        const dateKey = d.toISOString().split('T')[0];
        if (scheduleData[dateKey]) {
            const data = scheduleData[dateKey];
            const emp = data.employee;
            const shift = data.shift;
            if (employeeStats[emp]) {
                employeeStats[emp].shifts++;
                employeeStats[emp].hours += shiftDetails[shift].hours;
                employeeStats[emp].workDays++;
            }
            if (!shiftStats[shift]) shiftStats[shift] = 0;
            shiftStats[shift]++;
        } else {
            employees.forEach(emp => {
                employeeStats[emp].offDays++;
            });
        }
    }

    const filterText = filterInput ? filterInput.value.toLowerCase() : '';
    const filteredEmployees = Object.keys(employeeStats).filter(emp => emp.toLowerCase().includes(filterText));

    filteredEmployees.forEach(emp => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${emp}</td>
            <td>${employeeStats[emp].shifts}</td>
            <td>${employeeStats[emp].hours}</td>
            <td>${employeeStats[emp].workDays}</td>
            <td>${employeeStats[emp].offDays}</td>
        `;
        employeeSummaryBody.appendChild(row);
    });

    Object.keys(shiftStats).forEach(shift => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${shift}</td>
            <td>${shiftStats[shift]}</td>
        `;
        shiftSummaryBody.appendChild(row);
    });
}

document.getElementById('summary-filter')?.addEventListener('input', updateSummaries);

document.getElementById('download-summary')?.addEventListener('click', () => {
    const format = document.getElementById('download-format').value;
    const data = [];
    data.push(['Karyawan', 'Jumlah Shift', 'Jumlah Jam Kerja', 'Hari Kerja', 'Hari Libur']);
    const rows = document.querySelectorAll('#employee-summary-body tr');
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        data.push([cells[0].textContent, cells[1].textContent, cells[2].textContent, cells[3].textContent, cells[4].textContent]);
    });

    if (format === 'csv') {
        const csvContent = data.map(row => row.join(',')).join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'ringkasan_shift.csv';
        a.click();
    } else if (format === 'txt') {
        const txtContent = data.map(row => row.join('\t')).join('\n');
        const blob = new Blob([txtContent], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'ringkasan_shift.txt';
        a.click();
    }
});

// ============ ALL ORIGINAL UTILITY FUNCTIONS ============
function addHoliday() {
    const holidayDate = prompt('Masukkan tanggal hari libur (YYYY-MM-DD):');
    if (holidayDate && !holidays.includes(holidayDate)) {
        holidays.push(holidayDate);
        localStorage.setItem('holidays', JSON.stringify(holidays));
        generateCalendar(currentMonth, currentYear);
    }
}

function searchEmployee() {
    const query = prompt('Cari karyawan:');
    if (query) {
        const filtered = employees.filter(emp => emp.toLowerCase().includes(query.toLowerCase()));
        alert('Karyawan ditemukan: ' + filtered.join(', '));
    }
}

function filterByStatus() {
    const status = prompt('Filter berdasarkan status (Masuk, Izin, Sakit, Cuti, Lembur, dll):');
    if (status) {
        const filteredDates = Object.keys(scheduleData).filter(date => {
            const data = scheduleData[date];
            return status === 'Masuk' && data.shift !== 'Off';
        });
        alert('Tanggal dengan status ' + status + ': ' + filteredDates.join(', '));
    }
}

function filterByDateRange() {
    const start = prompt('Tanggal mulai (YYYY-MM-DD):');
    const end = prompt('Tanggal akhir (YYYY-MM-DD):');
    if (start && end) {
        const filtered = Object.keys(scheduleData).filter(date => date >= start && date <= end);
        alert('Shift dalam rentang: ' + filtered.map(date => `${date}: ${scheduleData[date].shift} (${scheduleData[date].employee})`).join('\n'));
    }
}

function notifyUpcomingShifts() {
    const today = new Date().toISOString().split('T')[0];
    const upcoming = Object.keys(scheduleData).filter(date => date > today && date <= new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]);
    if (upcoming.length > 0) {
        alert('Shift mendatang:\n' + upcoming.map(date => `${date}: ${scheduleData[date].shift} (${scheduleData[date].employee})`).join('\n'));
    } else {
        alert('Tidak ada shift mendatang dalam 7 hari.');
    }
}

function alertLowShifts() {
    const employeeStats = {};
    employees.forEach(emp => {
        employeeStats[emp] = { workDays: 0 };
    });
    Object.values(scheduleData).forEach(data => {
        if (data.shift !== 'Off') {
            employeeStats[data.employee].workDays++;
        }
    });
    const lowShiftEmployees = Object.keys(employeeStats).filter(emp => employeeStats[emp].workDays < 5);
    if (lowShiftEmployees.length > 0) {
        alert('Karyawan dengan shift kurang: ' + lowShiftEmployees.join(', '));
    } else {
        alert('Semua karyawan memiliki shift cukup.');
    }
}

function backupData() {
    const data = { scheduleData, employees, holidays, employeePreferences, timeZone };
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'jadwal_backup.json';
    a.click();
}

function restoreData() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.json';
    input.onchange = (e) => {
        const file = e.target.files[0];
        const reader = new FileReader();
        reader.onload = (event) => {
            try {
                const data = JSON.parse(event.target.result);
                scheduleData = data.scheduleData || {};
                employees = data.employees || [];
                holidays = data.holidays || [];
                employeePreferences = data.employeePreferences || {};
                timeZone = data.timeZone || 'Asia/Jakarta';
                localStorage.setItem('scheduleData', JSON.stringify(scheduleData));
                localStorage.setItem('employees', JSON.stringify(employees));
                localStorage.setItem('holidays', JSON.stringify(holidays));
                localStorage.setItem('employeePreferences', JSON.stringify(employeePreferences));
                localStorage.setItem('timeZone', timeZone);
                updateEmployeeSelect();
                generateCalendar(currentMonth, currentYear);
                updateSummaries();
                alert('Data berhasil direstore!');
            } catch (error) {
                alert('Error restoring data: ' + error.message);
            }
        };
        reader.readAsText(file);
    };
    input.click();
}

function setEmployeePreferences() {
    const emp = document.getElementById('employee-select').value;
    if (!emp) {
        alert('Pilih karyawan terlebih dahulu!');
        return;
    }
    const pref = prompt('Masukkan preferensi shift (pisahkan dengan koma, e.g., Shift 1: Pagi, Shift 2: Siang):');
    if (pref) {
        employeePreferences[emp] = pref.split(',').map(s => s.trim());
        localStorage.setItem('employeePreferences', JSON.stringify(employeePreferences));
        alert('Preferensi disimpan!');
    }
}

function setTimeZone() {
    const tz = prompt('Masukkan zona waktu (e.g., Asia/Jakarta):', timeZone);
    if (tz) {
        timeZone = tz;
        localStorage.setItem('timeZone', timeZone);
        alert('Zona waktu diset ke ' + tz);
    }
}

function notifyManager() {
    alert('Notifikasi dikirim ke manajer: Perubahan jadwal terakhir menit!');
}

function notifyEmployeeChange() {
    const emp = document.getElementById('employee-select').value;
    if (emp) {
        alert(`Email notifikasi perubahan jadwal dikirim ke ${emp}!`);
    } else {
        alert('Pilih karyawan terlebih dahulu!');
    }
}

function notifyEmployeeAssigned() {
    const emp = document.getElementById('employee-select').value;
    if (emp) {
        alert(`Email notifikasi shift ditentukan dikirim ke ${emp}!`);
    } else {
        alert('Pilih karyawan terlebih dahulu!');
    }
}

// ============ ORIGINAL VIEW FUNCTIONS ============
function generateWeekView(date) {
    const weekCalendar = document.getElementById('week-calendar');
    const timeColumn = document.getElementById('time-column');
    const daysColumn = document.getElementById('days-column');
    // const weekRange = document.getElementById('week-range'); // REMOVED: Redundant with current-nav

    if (!timeColumn || !daysColumn) return;

    timeColumn.innerHTML = '';
    daysColumn.innerHTML = '';

    const startOfWeek = new Date(date);
    startOfWeek.setDate(date.getDate() - date.getDay() + 1);
    const endOfWeek = new Date(startOfWeek);
    endOfWeek.setDate(startOfWeek.getDate() + 6);

    // weekRange.textContent = `${startOfWeek.toLocaleDateString('id-ID')} - ${endOfWeek.toLocaleDateString('id-ID')}`; // REMOVED: Redundant with current-nav

    for (let hour = 0; hour < 24; hour++) {
        const timeSlot = document.createElement('div');
        timeSlot.className = 'time-slot';
        timeSlot.textContent = `${String(hour).padStart(2, '0')}:00`;
        timeColumn.appendChild(timeSlot);
    }

    for (let i = 0; i < 7; i++) {
        const dayDate = new Date(startOfWeek);
        dayDate.setDate(startOfWeek.getDate() + i);
        const dayColumn = document.createElement('div');
        dayColumn.className = 'day-column';

        // const dayHeader = document.createElement('div'); // REMOVED: Redundant with current-nav
        // dayHeader.className = 'day-header'; // REMOVED: Redundant with current-nav
        // dayHeader.textContent = dayDate.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric' }); // REMOVED: Redundant with current-nav
        // dayColumn.appendChild(dayHeader); // REMOVED: Redundant with current-nav

        const dateKey = dayDate.toISOString().split('T')[0];

        if (scheduleData[dateKey]) {
            const emp = scheduleData[dateKey].employee;
            const shift = scheduleData[dateKey].shift;
            const shiftEvent = document.createElement('div');
            shiftEvent.className = 'shift-event';
            shiftEvent.textContent = `${shift} - ${emp}`;
            shiftEvent.style.top = `${getHourPosition(shiftDetails[shift].start)}px`;
            shiftEvent.style.height = `${shiftDetails[shift].hours * 60}px`;
            shiftEvent.addEventListener('click', () => openModal(dateKey, shiftEvent));
            dayColumn.appendChild(shiftEvent);
        }

        daysColumn.appendChild(dayColumn);
    }
}

function generateDayView(date) {
    const dayCalendar = document.getElementById('day-calendar');
    const timeColumn = document.getElementById('day-time-column');
    const dayContent = document.getElementById('day-content');
    // const dayDateSpan = document.getElementById('day-date'); // REMOVED: Redundant with current-nav

    if (!timeColumn || !dayContent) return;

    timeColumn.innerHTML = '';
    dayContent.innerHTML = '';

    // dayDateSpan.textContent = date.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }); // REMOVED: Redundant with current-nav

    for (let hour = 0; hour < 24; hour++) {
        const timeSlot = document.createElement('div');
        timeSlot.className = 'time-slot';
        timeSlot.textContent = `${String(hour).padStart(2, '0')}:00`;
        timeColumn.appendChild(timeSlot);
    }

    const dateKey = date.toISOString().split('T')[0];

    if (scheduleData[dateKey]) {
        const emp = scheduleData[dateKey].employee;
        const shift = scheduleData[dateKey].shift;
        const shiftEvent = document.createElement('div');
        shiftEvent.className = 'shift-event';
        shiftEvent.textContent = `${shift} - ${emp}`;
        shiftEvent.style.top = `${getHourPosition(shiftDetails[shift].start)}px`;
        shiftEvent.style.height = `${shiftDetails[shift].hours * 60}px`;
        shiftEvent.addEventListener('click', () => openModal(dateKey, shiftEvent));
        dayContent.appendChild(shiftEvent);
    }
}

function generateYearView(year) {
    const yearGrid = document.getElementById('year-grid');
    if (!yearGrid) return;
    yearGrid.innerHTML = '';

    for (let month = 0; month < 12; month++) {
        const monthMini = document.createElement('div');
        monthMini.className = 'month-mini';
        monthMini.style.cursor = 'pointer';
        monthMini.addEventListener('click', () => {
            currentMonth = month;
            switchView('month');
        });

        const monthTitle = document.createElement('h4');
        monthTitle.textContent = monthNames[month];
        monthMini.appendChild(monthTitle);

        const daysInMonth = new Date(year, month + 1, 0).getDate();
        let shiftCount = 0;
        for (let day = 1; day <= daysInMonth; day++) {
            const dateKey = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            if (scheduleData[dateKey]) shiftCount++;
        }

        const shiftInfo = document.createElement('p');
        shiftInfo.textContent = `${shiftCount} hari shift`;
        monthMini.appendChild(shiftInfo);

        yearGrid.appendChild(monthMini);
    }
}

function getHourPosition(timeString) {
    if (timeString === '-') return 0;
    const [hours, minutes] = timeString.split(':').map(Number);
    return (hours * 60 + minutes) * 60 / 60;
}

function switchView(view) {
    currentView = view;
    document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
    const viewBtn = document.getElementById(`view-${view}`);
    if (viewBtn) viewBtn.classList.add('active');

    document.querySelectorAll('.view-container').forEach(container => container.style.display = 'none');
    const viewContainer = document.getElementById(`${view}-view`);
    if (viewContainer) viewContainer.style.display = 'block';

    updateNavigation();
    generateCalendar(currentMonth, currentYear);
}

document.getElementById('view-month')?.addEventListener('click', () => switchView('month'));
document.getElementById('view-week')?.addEventListener('click', () => switchView('week'));
document.getElementById('view-day')?.addEventListener('click', () => switchView('day'));
document.getElementById('view-year')?.addEventListener('click', () => switchView('year'));

function updateNavigation() {
    const navigation = document.getElementById('navigation');
    const prevLabel = document.getElementById('prev-label');
    const nextLabel = document.getElementById('next-label');
    const currentNav = document.getElementById('current-nav');

    if (!navigation || !currentNav) return;

    if (currentView === 'month') {
        navigation.style.display = 'block';
        if (prevLabel) prevLabel.textContent = 'Bulan Sebelumnya';
        if (nextLabel) nextLabel.textContent = 'Bulan Berikutnya';
        currentNav.textContent = `${monthNames[currentMonth]} ${currentYear}`;
    } else if (currentView === 'week') {
        navigation.style.display = 'block';
        if (prevLabel) prevLabel.textContent = 'Minggu Sebelumnya';
        if (nextLabel) nextLabel.textContent = 'Minggu Berikutnya';
        const startOfWeek = new Date(currentDate);
        startOfWeek.setDate(currentDate.getDate() - currentDate.getDay() + 1);
        const endOfWeek = new Date(startOfWeek);
        endOfWeek.setDate(startOfWeek.getDate() + 6);
        currentNav.textContent = `${startOfWeek.toLocaleDateString('id-ID')} - ${endOfWeek.toLocaleDateString('id-ID')}`;
    } else if (currentView === 'day') {
        navigation.style.display = 'block';
        if (prevLabel) prevLabel.textContent = 'Hari Sebelumnya';
        if (nextLabel) nextLabel.textContent = 'Hari Berikutnya';
        currentNav.textContent = currentDate.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    } else if (currentView === 'year') {
        navigation.style.display = 'block';
        if (prevLabel) prevLabel.textContent = 'Tahun Sebelumnya';
        if (nextLabel) nextLabel.textContent = 'Tahun Berikutnya';
        currentNav.textContent = currentYear;
    }
}

function setupAllEventListeners() {
    // Cabang selection (database mode toggle)
    const cabangSelect = document.getElementById('cabang-select');
    if (cabangSelect) {
        cabangSelect.addEventListener('change', async function() {
            const cabangId = this.value;
            if (cabangId) {
                useDatabaseMode = true;
                currentCabangId = cabangId;
                await loadDatabaseUsers(cabangId);
                await loadDatabaseShifts(cabangId, currentMonth, currentYear);
            } else {
                useDatabaseMode = false;
                currentCabangId = null;
                updateEmployeeSelect();
                generateCalendar(currentMonth, currentYear);
            }
        });
    }

    // Navigation
    document.getElementById('prev-nav')?.addEventListener('click', () => {
        if (currentView === 'month') {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            if (useDatabaseMode && currentCabangId) {
                loadDatabaseShifts(currentCabangId, currentMonth, currentYear);
            }
        } else if (currentView === 'week') {
            currentDate.setDate(currentDate.getDate() - 7);
        } else if (currentView === 'day') {
            currentDate.setDate(currentDate.getDate() - 1);
        } else if (currentView === 'year') {
            currentYear--;
        }
        updateNavigation();
        generateCalendar(currentMonth, currentYear);
    });

    document.getElementById('next-nav')?.addEventListener('click', () => {
        if (currentView === 'month') {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            if (useDatabaseMode && currentCabangId) {
                loadDatabaseShifts(currentCabangId, currentMonth, currentYear);
            }
        } else if (currentView === 'week') {
            currentDate.setDate(currentDate.getDate() + 7);
        } else if (currentView === 'day') {
            currentDate.setDate(currentDate.getDate() + 1);
        } else if (currentView === 'year') {
            currentYear++;
        }
        updateNavigation();
        generateCalendar(currentMonth, currentYear);
    });

    // Summary toggle
    document.getElementById('toggle-summary')?.addEventListener('click', () => {
        document.getElementById('calendar-view').style.display = 'none';
        document.getElementById('summary-tables').style.display = 'block';
        document.getElementById('toggle-summary').style.display = 'none';
        updateSummaries();
    });

    document.getElementById('hide-summary')?.addEventListener('click', () => {
        document.getElementById('summary-tables').style.display = 'none';
        document.getElementById('calendar-view').style.display = 'block';
        document.getElementById('toggle-summary').style.display = 'block';
    });

    // Summary navigation
    document.getElementById('prev-summary')?.addEventListener('click', () => {
        if (currentView === 'month') {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
        } else if (currentView === 'week') {
            currentDate.setDate(currentDate.getDate() - 7);
        } else if (currentView === 'day') {
            currentDate.setDate(currentDate.getDate() - 1);
        } else if (currentView === 'year') {
            currentYear--;
        }
        updateSummaries();
    });

    document.getElementById('next-summary')?.addEventListener('click', () => {
        if (currentView === 'month') {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
        } else if (currentView === 'week') {
            currentDate.setDate(currentDate.getDate() + 7);
        } else if (currentView === 'day') {
            currentDate.setDate(currentDate.getDate() + 1);
        } else if (currentView === 'year') {
            currentYear++;
        }
        updateSummaries();
    });
}
