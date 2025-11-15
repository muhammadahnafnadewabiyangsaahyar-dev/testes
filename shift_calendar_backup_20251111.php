<?php
session_start();
include 'connect.php';
include 'functions_role.php';

if (!isset($_SESSION['user_id']) || !isAdminOrSuperadmin($_SESSION['role'])) {
    header('Location: index.php?error=unauthorized');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalender Shift - KAORI Indonesia</title>
    <link rel="stylesheet" href="style_modern.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    <!-- Custom Calendar CSS -->
    <link rel="stylesheet" href="style_calendar.css">

    <style>
        .calendar-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }

        .calendar-controls {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-group label {
            font-weight: bold;
            color: #333;
        }

        .filter-group select {
            padding: 8px 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            min-width: 150px;
        }

        .btn-calendar {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-success {
            background: #4CAF50;
            color: white;
        }

        .btn-success:hover {
            background: #45a049;
        }

        .btn-warning {
            background: #FF9800;
            color: white;
        }

        .btn-warning:hover {
            background: #e68900;
        }

        .calendar-wrapper {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }

        /* DayPilot custom styles */
        .scheduler_default_rowheader_inner {
            padding: 5px 10px;
        }

        .scheduler_default_cell {
            cursor: pointer;
        }

        .scheduler_default_cell.scheduler_default_cell_business {
            background-color: #f8f9fa;
        }

        .scheduler_default_event {
            border-radius: 4px;
            border: 1px solid #ddd;
            font-size: 12px;
            padding: 2px 5px;
        }

        .scheduler_default_event_inner {
            padding: 2px 4px;
        }

        .shift-event {
            font-weight: bold;
            text-align: center;
        }

        .shift-pagi { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .shift-middle { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .shift-sore { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }

        .shift-confirmed { opacity: 1; }
        .shift-pending { opacity: 0.7; border-style: dashed; }
        .shift-declined { opacity: 0.5; background: #f44336 !important; color: white; }

        .legend {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .modal-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }

        .modal-header h3 {
            margin: 0;
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .shift-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .shift-info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .shift-info-item:last-child {
            margin-bottom: 0;
        }

        .shift-info-label {
            font-weight: bold;
            color: #666;
        }

        .shift-info-value {
            color: #333;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="main-title">📅 Manajemen Shift</div>
    <div class="subtitle-container">
        <p class="subtitle">Kelola jadwal shift karyawan dengan kalender interaktif</p>
    </div>

    <div class="content-container">
        <div id="alert-message" class="alert" style="display: none;"></div>

        <div class="calendar-container">
            <div class="calendar-header">
                <h2><i class="fas fa-calendar-alt"></i> Kalender Shift</h2>

                <div class="calendar-controls">
                    <div class="filter-group">
                        <label for="cabang-filter">Cabang:</label>
                        <select id="cabang-filter">
                            <option value="">Semua Cabang</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="view-type">Tampilan:</label>
                        <select id="view-type">
                            <option value="Days">Hari</option>
                            <option value="Week">Minggu</option>
                            <option value="Month" selected>Bulan</option>
                        </select>
                    </div>

                    <button id="prev-btn" class="btn-calendar btn-primary">
                        <i class="fas fa-chevron-left"></i> Sebelumnya
                    </button>

                    <button id="today-btn" class="btn-calendar btn-success">
                        <i class="fas fa-calendar-day"></i> Hari Ini
                    </button>

                    <button id="next-btn" class="btn-calendar btn-primary">
                        Selanjutnya <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>

            <div class="calendar-wrapper">
                <div id="custom-calendar" class="calendar-grid">
                    <!-- Calendar content will be generated by JavaScript -->
                </div>
                <div id="loading-overlay" class="loading-overlay" style="display: none;">
                    <div class="loading-spinner"></div>
                </div>
            </div>

            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color shift-pagi"></div>
                    <span>Shift Pagi</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color shift-middle"></div>
                    <span>Shift Middle</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color shift-sore"></div>
                    <span>Shift Sore</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: linear-gradient(45deg, #4CAF50, #45a049);"></div>
                    <span>Dikonfirmasi</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: linear-gradient(45deg, #FF9800, #e68900); opacity: 0.7;"></div>
                    <span>Menunggu</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #f44336;"></div>
                    <span>Ditolak</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Shift Assignment -->
    <div id="shift-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">Assign Shift</h3>
            </div>

            <div id="shift-info" class="shift-info" style="display: none;">
                <div class="shift-info-item">
                    <span class="shift-info-label">Tanggal:</span>
                    <span class="shift-info-value" id="info-date"></span>
                </div>
                <div class="shift-info-item">
                    <span class="shift-info-label">Pegawai:</span>
                    <span class="shift-info-value" id="info-employee"></span>
                </div>
                <div class="shift-info-item">
                    <span class="shift-info-label">Shift:</span>
                    <span class="shift-info-value" id="info-shift"></span>
                </div>
                <div class="shift-info-item">
                    <span class="shift-info-label">Status:</span>
                    <span class="shift-info-value" id="info-status"></span>
                </div>
            </div>

            <form id="shift-form">
                <input type="hidden" id="shift-id" name="id">
                <input type="hidden" id="action-type" name="action" value="create">

                <div class="form-group">
                    <label for="employee-select">Pegawai:</label>
                    <select id="employee-select" name="user_id" required>
                        <option value="">Pilih Pegawai</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="cabang-select">Cabang & Shift:</label>
                    <select id="cabang-select" name="cabang_id" required>
                        <option value="">Pilih Cabang & Shift</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="shift-date">Tanggal:</label>
                    <input type="date" id="shift-date" name="tanggal_shift" required>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-calendar btn-primary" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn-calendar btn-success">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Global variables
        let currentDate = new Date();
        let currentCabang = '';
        let currentView = 'month'; // month, week, day
        let calendarData = { shifts: [], employees: [] };

        // Initialize calendar
        document.addEventListener('DOMContentLoaded', function() {
            initCalendar();
            loadCabangOptions();
            setupEventListeners();
        });

        function initCalendar() {
            renderCalendar();
            loadShifts();
        }

        function renderCalendar() {
            const calendarEl = document.getElementById('custom-calendar');

            if (currentView === 'month') {
                renderMonthView(calendarEl);
            } else if (currentView === 'week') {
                renderWeekView(calendarEl);
            } else if (currentView === 'day') {
                renderDayView(calendarEl);
            }
        }

        function renderMonthView(container) {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const startDate = new Date(firstDay);
            startDate.setDate(startDate.getDate() - firstDay.getDay()); // Start from Sunday

            container.innerHTML = `
                <div class="resource-column">
                    <div class="resource-header">Pegawai</div>
                    <div class="resource-list" id="resource-list">
                        <!-- Employee list will be populated here -->
                    </div>
                </div>
                <div class="calendar-column">
                    <div class="calendar-header-row">
                        <div class="calendar-header-cell">Minggu</div>
                        <div class="calendar-header-cell">Senin</div>
                        <div class="calendar-header-cell">Selasa</div>
                        <div class="calendar-header-cell">Rabu</div>
                        <div class="calendar-header-cell">Kamis</div>
                        <div class="calendar-header-cell">Jumat</div>
                        <div class="calendar-header-cell">Sabtu</div>
                    </div>
                    <div class="calendar-body" id="calendar-body">
                        <!-- Calendar days will be populated here -->
                    </div>
                </div>
            `;

            // Generate calendar days
            const calendarBody = document.getElementById('calendar-body');
            let html = '';

            for (let week = 0; week < 6; week++) {
                html += '<div class="calendar-week">';
                for (let day = 0; day < 7; day++) {
                    const currentDate = new Date(startDate);
                    currentDate.setDate(startDate.getDate() + (week * 7) + day);

                    const isCurrentMonth = currentDate.getMonth() === month;
                    const isToday = currentDate.toDateString() === new Date().toDateString();
                    const dateStr = currentDate.toISOString().split('T')[0];

                    html += `
                        <div class="calendar-day-cell ${isCurrentMonth ? '' : 'other-month'} ${isToday ? 'today' : ''}"
                             data-date="${dateStr}">
                            <div class="calendar-day-number">${currentDate.getDate()}</div>
                            <div class="day-events" data-date="${dateStr}">
                                <!-- Events will be populated here -->
                            </div>
                        </div>
                    `;
                }
                html += '</div>';
            }

            calendarBody.innerHTML = html;

            // Add click handlers for day cells
            document.querySelectorAll('.calendar-day-cell').forEach(cell => {
                cell.addEventListener('click', function(e) {
                    if (!e.target.classList.contains('shift-event')) {
                        const date = this.dataset.date;
                        showShiftModal(null, date);
                    }
                });
            });
        }

        function renderWeekView(container) {
            // Similar structure but for week view
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            const startOfWeek = new Date(currentDate);
            startOfWeek.setDate(currentDate.getDate() - currentDate.getDay());

            container.innerHTML = `
                <div class="resource-column">
                    <div class="resource-header">Pegawai</div>
                    <div class="resource-list" id="resource-list">
                        <!-- Employee list will be populated here -->
                    </div>
                </div>
                <div class="calendar-column">
                    <div class="calendar-header-row">
                        ${Array.from({length: 7}, (_, i) => {
                            const date = new Date(startOfWeek);
                            date.setDate(startOfWeek.getDate() + i);
                            return `<div class="calendar-header-cell">${date.toLocaleDateString('id-ID', {weekday: 'long', day: 'numeric'})}</div>`;
                        }).join('')}
                    </div>
                    <div class="calendar-body" id="calendar-body">
                        <div class="calendar-week">
                            ${Array.from({length: 7}, (_, i) => {
                                const date = new Date(startOfWeek);
                                date.setDate(startOfWeek.getDate() + i);
                                const dateStr = date.toISOString().split('T')[0];
                                const isToday = date.toDateString() === new Date().toDateString();
                                return `
                                    <div class="calendar-day-cell ${isToday ? 'today' : ''}" data-date="${dateStr}">
                                        <div class="calendar-day-number">${date.getDate()}</div>
                                        <div class="day-events" data-date="${dateStr}"></div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                </div>
            `;

            // Add click handlers
            document.querySelectorAll('.calendar-day-cell').forEach(cell => {
                cell.addEventListener('click', function(e) {
                    if (!e.target.classList.contains('shift-event')) {
                        const date = this.dataset.date;
                        showShiftModal(null, date);
                    }
                });
            });
        }

        function renderDayView(container) {
            const dateStr = currentDate.toISOString().split('T')[0];

            container.innerHTML = `
                <div class="resource-column">
                    <div class="resource-header">Pegawai</div>
                    <div class="resource-list" id="resource-list">
                        <!-- Employee list will be populated here -->
                    </div>
                </div>
                <div class="calendar-column">
                    <div class="calendar-header-row">
                        <div class="calendar-header-cell">${currentDate.toLocaleDateString('id-ID', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'})}</div>
                    </div>
                    <div class="calendar-body" id="calendar-body">
                        <div class="calendar-day-single">
                            <div class="calendar-day-cell today" data-date="${dateStr}">
                                <div class="calendar-day-number">${currentDate.getDate()}</div>
                                <div class="day-events" data-date="${dateStr}"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Add click handler
            document.querySelector('.calendar-day-cell').addEventListener('click', function(e) {
                if (!e.target.classList.contains('shift-event')) {
                    const date = this.dataset.date;
                    showShiftModal(null, date);
                }
            });
        }

        function loadCabangOptions() {
            fetch('api_shift_calendar.php?action=get_cabang')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const select = document.getElementById('cabang-filter');
                        data.data.forEach(cabang => {
                            const option = document.createElement('option');
                            option.value = cabang.id;
                            option.textContent = cabang.nama_cabang;
                            select.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('Error loading cabang:', error));
        }

        function loadShifts() {
            showLoading();

            const cabangId = document.getElementById('cabang-filter').value;
            const cabangName = cabangId ? document.querySelector(`#cabang-filter option[value="${cabangId}"]`).textContent : '';

            // Load employees and shifts simultaneously
            Promise.all([
                fetch(`api_shift_calendar.php?action=get_pegawai&outlet=${cabangName}`).then(r => r.json()),
                fetch(`api_shift_calendar.php?action=get_assignments&cabang_id=${cabangId}&month=${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}`).then(r => r.json())
            ])
            .then(([employeeData, shiftData]) => {
                if (employeeData.status === 'success') {
                    calendarData.employees = employeeData.data;
                    renderEmployeeList();
                }

                if (shiftData.status === 'success') {
                    calendarData.shifts = shiftData.data;
                    renderShiftEvents();
                }
            })
            .catch(error => console.error('Error loading data:', error))
            .finally(() => hideLoading());
        }

        function renderEmployeeList() {
            const resourceList = document.getElementById('resource-list');
            if (!resourceList) return;

            resourceList.innerHTML = calendarData.employees.map(emp =>
                `<div class="resource-item" data-employee-id="${emp.id}">${emp.name}</div>`
            ).join('');

            // Add click handlers for employee selection
            document.querySelectorAll('.resource-item').forEach(item => {
                item.addEventListener('click', function() {
                    document.querySelectorAll('.resource-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        }

        function renderShiftEvents() {
            // Clear existing events
            document.querySelectorAll('.shift-event').forEach(event => event.remove());

            calendarData.shifts.forEach(shift => {
                const dateStr = shift.tanggal_shift;
                const dayElement = document.querySelector(`.day-events[data-date="${dateStr}"]`);

                if (dayElement) {
                    const eventEl = document.createElement('div');
                    eventEl.className = `shift-event shift-${shift.nama_shift} shift-${shift.status_konfirmasi}`;
                    eventEl.textContent = `${shift.nama_lengkap} - ${shift.nama_shift}`;
                    eventEl.dataset.shiftId = shift.id;
                    eventEl.dataset.shiftData = JSON.stringify(shift);

                    eventEl.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const shiftData = JSON.parse(this.dataset.shiftData);
                        showShiftModal(shiftData);
                    });

                    dayElement.appendChild(eventEl);
                }
            });
        }

        function showLoading() {
            document.getElementById('loading-overlay').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loading-overlay').style.display = 'none';
        }

        function setupEventListeners() {
            // Filter change
            document.getElementById('cabang-filter').addEventListener('change', function() {
                currentCabang = this.value;
                loadShifts();
            });

            // View type change
            document.getElementById('view-type').addEventListener('change', function() {
                changeView(this.value.toLowerCase());
            });

            // Navigation buttons
            document.getElementById('prev-btn').addEventListener('click', function() {
                navigateCalendar('prev');
            });

            document.getElementById('next-btn').addEventListener('click', function() {
                navigateCalendar('next');
            });

            document.getElementById('today-btn').addEventListener('click', function() {
                navigateCalendar('today');
            });

            // Form submission
            document.getElementById('shift-form').addEventListener('submit', function(e) {
                e.preventDefault();
                saveShift();
            });
        }

        function changeView(viewType) {
            currentView = viewType;
            renderCalendar();
            loadShifts();
        }

        function navigateCalendar(direction) {
            const newDate = new Date(currentDate);

            switch(direction) {
                case 'prev':
                    if (currentView === 'month') {
                        newDate.setMonth(newDate.getMonth() - 1);
                    } else if (currentView === 'week') {
                        newDate.setDate(newDate.getDate() - 7);
                    } else {
                        newDate.setDate(newDate.getDate() - 1);
                    }
                    break;
                case 'next':
                    if (currentView === 'month') {
                        newDate.setMonth(newDate.getMonth() + 1);
                    } else if (currentView === 'week') {
                        newDate.setDate(newDate.getDate() + 7);
                    } else {
                        newDate.setDate(newDate.getDate() + 1);
                    }
                    break;
                case 'today':
                    currentDate = new Date();
                    renderCalendar();
                    loadShifts();
                    return;
            }

            currentDate = newDate;
            renderCalendar();
            loadShifts();
        }

        function showShiftModal(eventData = null, date = null, resource = null) {
            const modal = document.getElementById('shift-modal');
            const form = document.getElementById('shift-form');
            const info = document.getElementById('shift-info');

            if (eventData) {
                // Edit existing shift
                document.getElementById('modal-title').textContent = 'Edit Shift';
                document.getElementById('action-type').value = 'update';
                document.getElementById('shift-id').value = eventData.id;

                // Show shift info
                info.style.display = 'block';
                document.getElementById('info-date').textContent = new Date(eventData.tanggal_shift).toLocaleDateString('id-ID');
                document.getElementById('info-employee').textContent = eventData.nama_lengkap;
                document.getElementById('info-shift').textContent = `${eventData.nama_shift} (${eventData.jam_masuk}-${eventData.jam_keluar})`;
                document.getElementById('info-status').textContent = eventData.status_konfirmasi;

                // Pre-fill form
                document.getElementById('employee-select').value = eventData.user_id;
                document.getElementById('cabang-select').value = eventData.cabang_id;
                document.getElementById('shift-date').value = eventData.tanggal_shift;

            } else {
                // Create new shift
                document.getElementById('modal-title').textContent = 'Assign Shift Baru';
                document.getElementById('action-type').value = 'create';
                document.getElementById('shift-id').value = '';

                info.style.display = 'none';

                // Pre-fill date if provided
                if (date) {
                    document.getElementById('shift-date').value = date;
                }
                if (resource) {
                    document.getElementById('employee-select').value = resource;
                }
            }

            modal.style.display = 'block';
            loadFormOptions();
        }

        function closeModal() {
            document.getElementById('shift-modal').style.display = 'none';
            document.getElementById('shift-form').reset();
        }

        function loadFormOptions() {
            const cabangSelect = document.getElementById('cabang-select');
            const employeeSelect = document.getElementById('employee-select');

            // Load cabang options
            fetch('api_shift_calendar.php?action=get_cabang')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        cabangSelect.innerHTML = '<option value="">Pilih Cabang & Shift</option>';
                        data.data.forEach(cabang => {
                            const option = document.createElement('option');
                            option.value = cabang.id;
                            option.textContent = cabang.nama_cabang;
                            cabangSelect.appendChild(option);
                        });
                    }
                });

            // Load employee options
            const cabangFilter = document.getElementById('cabang-filter');
            const cabangName = cabangFilter.value ? cabangFilter.options[cabangFilter.selectedIndex].text : '';

            fetch(`api_shift_calendar.php?action=get_pegawai&outlet=${cabangName}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        employeeSelect.innerHTML = '<option value="">Pilih Pegawai</option>';
                        data.data.forEach(emp => {
                            const option = document.createElement('option');
                            option.value = emp.id;
                            option.textContent = emp.name;
                            employeeSelect.appendChild(option);
                        });
                    }
                });
        }

        function saveShift() {
            const formData = new FormData(document.getElementById('shift-form'));

            fetch('api_shift_calendar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showAlert(data.message, 'success');
                    closeModal();
                    loadShifts();
                } else {
                    showAlert(data.message || 'Terjadi kesalahan', 'error');
                }
            })
            .catch(error => {
                console.error('Error saving shift:', error);
                showAlert('Terjadi kesalahan saat menyimpan', 'error');
            });
        }

        function deleteShift(shiftId) {
            if (confirm('Apakah Anda yakin ingin menghapus shift ini?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', shiftId);

                fetch('api_shift_calendar.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showAlert(data.message, 'success');
                        loadShifts();
                    } else {
                        showAlert(data.message || 'Terjadi kesalahan', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error deleting shift:', error);
                    showAlert('Terjadi kesalahan saat menghapus', 'error');
                });
            }
        }

        function showAlert(message, type) {
            const alert = document.getElementById('alert-message');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            alert.style.display = 'block';

            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('shift-modal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>

</body>
</html>