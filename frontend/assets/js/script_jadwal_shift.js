/**
 * Script Jadwal Shift - User View
 * Untuk melihat dan mengonfirmasi jadwal shift karyawan
 */

let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                   'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 DOM Loaded');
    console.log('📊 Shifts Data:', shiftsData);
    console.log('📅 Current Month/Year:', currentMonth, currentYear);
    
    // Check if required elements exist
    const calendarBody = document.getElementById('calendar-body');
    const monthYearDisplay = document.getElementById('current-month-year');
    
    if (!calendarBody) {
        console.error('❌ calendar-body element not found!');
        return;
    }
    if (!monthYearDisplay) {
        console.error('❌ current-month-year element not found!');
        return;
    }
    
    console.log('✅ Required elements found');
    
    try {
        generateCalendar(currentMonth, currentYear);
        setupEventListeners();
        console.log('✅ Calendar generated successfully');
    } catch (error) {
        console.error('❌ Error generating calendar:', error);
    }
});

function setupEventListeners() {
    document.getElementById('prev-month').addEventListener('click', () => {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        generateCalendar(currentMonth, currentYear);
    });
    
    document.getElementById('next-month').addEventListener('click', () => {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        generateCalendar(currentMonth, currentYear);
    });
    
    // Close modals
    document.querySelectorAll('.close').forEach(el => {
        el.addEventListener('click', function() {
            this.closest('.modal').style.display = 'none';
        });
    });
    
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    });
    
    // Confirm action button
    document.getElementById('confirm-action-btn').addEventListener('click', handleConfirmAction);
}

function generateCalendar(month, year) {
    console.log('📅 Generating calendar for:', monthNames[month], year);
    
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startDayOfWeek = firstDay.getDay(); // 0 = Sunday
    
    // Adjust to Monday start (0 = Monday, 6 = Sunday)
    const startDay = startDayOfWeek === 0 ? 6 : startDayOfWeek - 1;
    
    const calendarBody = document.getElementById('calendar-body');
    const monthYearDisplay = document.getElementById('current-month-year');
    
    if (!calendarBody || !monthYearDisplay) {
        console.error('❌ Required elements not found!');
        return;
    }
    
    monthYearDisplay.textContent = `${monthNames[month]} ${year}`;
    calendarBody.innerHTML = '';
    
    console.log('📊 Calendar info:', {
        daysInMonth,
        startDay,
        firstDay: firstDay.toDateString()
    });
    
    const today = new Date();
    const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
    
    let date = 1;
    let nextMonthDate = 1;
    
    // Generate 6 weeks
    for (let i = 0; i < 6; i++) {
        const row = document.createElement('tr');
        
        for (let j = 0; j < 7; j++) {
            const cell = document.createElement('td');
            
            if (i === 0 && j < startDay) {
                // Previous month days
                const prevMonthLastDay = new Date(year, month, 0).getDate();
                const prevDate = prevMonthLastDay - startDay + j + 1;
                cell.innerHTML = `<div class="date-number">${prevDate}</div>`;
                cell.classList.add('other-month');
            } else if (date > daysInMonth) {
                // Next month days
                cell.innerHTML = `<div class="date-number">${nextMonthDate}</div>`;
                cell.classList.add('other-month');
                nextMonthDate++;
            } else {
                // Current month days
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(date).padStart(2, '0')}`;
                
                let cellHTML = `<div class="date-number">${date}</div>`;
                
                // Check if today
                if (dateStr === todayStr) {
                    cell.classList.add('today');
                }
                
                // Check if has shift
                if (shiftsData[dateStr]) {
                    const shift = shiftsData[dateStr];
                    cell.classList.add('has-shift', shift.status_konfirmasi);
                    
                    cellHTML += `
                        <div class="shift-info">
                            <div><strong>${escapeHtml(shift.nama_shift)}</strong></div>
                            <div style="font-size: 11px; color: #666;">${escapeHtml(shift.jam_masuk)} - ${escapeHtml(shift.jam_keluar)}</div>
                            <div><span class="shift-badge ${shift.status_konfirmasi}">${getStatusText(shift.status_konfirmasi)}</span></div>
                            ${shift.status_konfirmasi === 'pending' ? `
                            <div class="shift-actions">
                                <button class="btn-confirm" onclick="confirmShift(${shift.id}, '${dateStr}', '${escapeHtml(shift.nama_shift)}')">✓ Konfirmasi</button>
                                <button class="btn-decline" onclick="declineShift(${shift.id}, '${dateStr}', '${escapeHtml(shift.nama_shift)}')">✗ Tolak</button>
                            </div>
                            ` : ''}
                            <div class="shift-actions">
                                <button class="btn-detail" onclick="showShiftDetail(${shift.id})">📋 Detail</button>
                            </div>
                        </div>
                    `;
                }
                
                cell.innerHTML = cellHTML;
                date++;
            }
            
            row.appendChild(cell);
        }
        
        calendarBody.appendChild(row);
        
        if (date > daysInMonth) break;
    }
}

function getStatusText(status) {
    const statusMap = {
        'pending': 'Menunggu',
        'confirmed': '✓ Dikonfirmasi',
        'declined': '✗ Ditolak'
    };
    return statusMap[status] || status;
}

function showShiftDetail(shiftId) {
    // Find shift data
    const shift = Object.values(shiftsData).find(s => s.id == shiftId);
    if (!shift) return;
    
    const modalBody = document.getElementById('modal-body');
    const modalFooter = document.getElementById('modal-footer');
    
    const statusClass = shift.status_konfirmasi;
    const statusText = getStatusText(shift.status_konfirmasi);
    
    modalBody.innerHTML = `
        <div class="info-row">
            <span class="label">Tanggal:</span>
            <span class="value">${formatDate(shift.tanggal_shift)}</span>
        </div>
        <div class="info-row">
            <span class="label">Cabang:</span>
            <span class="value">${escapeHtml(shift.nama_cabang)}</span>
        </div>
        <div class="info-row">
            <span class="label">Shift:</span>
            <span class="value">${escapeHtml(shift.nama_shift)}</span>
        </div>
        <div class="info-row">
            <span class="label">Jam Kerja:</span>
            <span class="value">${escapeHtml(shift.jam_masuk)} - ${escapeHtml(shift.jam_keluar)}</span>
        </div>
        <div class="info-row">
            <span class="label">Status:</span>
            <span class="value"><span class="shift-badge ${statusClass}">${statusText}</span></span>
        </div>
        ${shift.waktu_konfirmasi ? `
        <div class="info-row">
            <span class="label">Waktu Konfirmasi:</span>
            <span class="value">${formatDateTime(shift.waktu_konfirmasi)}</span>
        </div>
        ` : ''}
        ${shift.catatan_pegawai ? `
        <div class="info-row">
            <span class="label">Catatan Anda:</span>
            <span class="value">${escapeHtml(shift.catatan_pegawai)}</span>
        </div>
        ` : ''}
    `;
    
    if (shift.status_konfirmasi === 'pending') {
        modalFooter.innerHTML = `
            <button onclick="closeModal()" style="background: #6c757d; color: white;">Tutup</button>
            <button onclick="confirmShift(${shift.id}, '${shift.tanggal_shift}', '${escapeHtml(shift.nama_shift)}')" style="background: #4CAF50; color: white;">✓ Konfirmasi</button>
            <button onclick="declineShift(${shift.id}, '${shift.tanggal_shift}', '${escapeHtml(shift.nama_shift)}')" style="background: #f44336; color: white;">✗ Tolak</button>
        `;
    } else {
        modalFooter.innerHTML = `
            <button onclick="closeModal()" style="background: #6c757d; color: white;">Tutup</button>
        `;
    }
    
    document.getElementById('shift-modal').style.display = 'block';
}

function closeModal() {
    document.getElementById('shift-modal').style.display = 'none';
}

let currentShiftAction = null;

function confirmShift(shiftId, date, shiftName) {
    closeModal();
    currentShiftAction = { shiftId, status: 'confirmed' };
    document.getElementById('confirm-modal-title').textContent = '✓ Konfirmasi Shift';
    document.getElementById('catatan-konfirmasi').value = '';
    document.getElementById('catatan-konfirmasi').placeholder = 'Anda dapat menambahkan catatan konfirmasi (opsional)...';
    document.getElementById('confirm-modal').style.display = 'block';
}

function declineShift(shiftId, date, shiftName) {
    closeModal();
    currentShiftAction = { shiftId, status: 'declined' };
    document.getElementById('confirm-modal-title').textContent = '✗ Tolak Shift';
    document.getElementById('catatan-konfirmasi').value = '';
    document.getElementById('catatan-konfirmasi').placeholder = 'Harap berikan alasan penolakan...';
    document.getElementById('confirm-modal').style.display = 'block';
}

function closeConfirmModal() {
    document.getElementById('confirm-modal').style.display = 'none';
    currentShiftAction = null;
}

async function handleConfirmAction() {
    if (!currentShiftAction) return;
    
    const catatan = document.getElementById('catatan-konfirmasi').value.trim();
    
    // Validate if declining requires a reason
    if (currentShiftAction.status === 'declined' && !catatan) {
        alert('Harap berikan alasan penolakan shift.');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('shift_id', currentShiftAction.shiftId);
        formData.append('status', currentShiftAction.status);
        formData.append('catatan', catatan);
        
        const response = await fetch('api_shift_confirmation.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.status === 'success') {
            alert('✓ ' + result.message);
            closeConfirmModal();
            location.reload(); // Reload to show updated data
        } else {
            alert('✗ Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('✗ Terjadi kesalahan saat memproses konfirmasi. Silakan coba lagi.');
    }
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    return `${days[date.getDay()]}, ${date.getDate()} ${monthNames[date.getMonth()]} ${date.getFullYear()}`;
}

function formatDateTime(dateTimeStr) {
    const date = new Date(dateTimeStr);
    return `${date.getDate()}/${date.getMonth() + 1}/${date.getFullYear()} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

console.log('✓ Script Jadwal Shift loaded successfully!');
