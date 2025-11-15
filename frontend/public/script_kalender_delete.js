// ============ KALENDER DELETE MODULE ============
(function(window) {
    'use strict';
    
    const KalenderDelete = {};
    
    // Current shift being viewed/deleted
    let currentShiftGroup = null;
    
    // Open delete modal for shift group
    KalenderDelete.openDeleteModal = function(shiftGroup, dateStr) {
        if (!shiftGroup || !shiftGroup.employees || shiftGroup.employees.length === 0) {
            console.error('Invalid shift group');
            return;
        }
        
        currentShiftGroup = shiftGroup;
        
        const modal = document.getElementById('day-delete-modal');
        if (!modal) {
            console.error('Delete modal not found');
            return;
        }
        
        const modalTitle = modal.querySelector('.modal-title');
        const modalContent = modal.querySelector('.modal-shift-info');
        const employeeList = modal.querySelector('.modal-employee-list');
        
        const firstAssignment = shiftGroup.shift;
        const isLocked = shiftGroup.employees.some(emp => {
            const status = emp.status_konfirmasi || 'pending';
            return status === 'approved' || status === 'izin' || status === 'sakit' || status === 'reschedule';
        });
        
        // Set modal title
        if (modalTitle) {
            modalTitle.textContent = `Hapus Shift - ${dateStr}`;
        }
        
        // Set shift info
        if (modalContent) {
            const jamMasuk = window.KalenderUtils.formatTime(firstAssignment.jam_masuk);
            const jamKeluar = window.KalenderUtils.formatTime(firstAssignment.jam_keluar);
            
            modalContent.innerHTML = `
                <div style="background-color: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <div style="font-size: 16px; font-weight: bold; color: #333; margin-bottom: 8px;">
                        ${firstAssignment.nama_shift || firstAssignment.shift_type || 'Shift'}
                    </div>
                    <div style="color: #666; font-size: 14px;">
                        ⏰ ${jamMasuk} - ${jamKeluar}
                    </div>
                </div>
            `;
        }
        
        // Build employee list
        if (employeeList) {
            employeeList.innerHTML = '';
            
            if (isLocked) {
                const lockWarning = document.createElement('div');
                lockWarning.style.cssText = 'background-color: #fff3e0; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #ff9800;';
                lockWarning.innerHTML = '<strong>⚠️ Perhatian:</strong> Shift ini mengandung pegawai dengan status terkunci (Approved/Izin/Sakit/Reschedule). Hanya pegawai dengan status Pending yang bisa dihapus.';
                employeeList.appendChild(lockWarning);
            }
            
            shiftGroup.employees.forEach(emp => {
                const empStatus = emp.status_konfirmasi || 'pending';
                const isEmpLocked = empStatus === 'approved' || empStatus === 'izin' || empStatus === 'sakit' || empStatus === 'reschedule';
                
                let statusIcon = '⏱';
                let statusText = 'Pending';
                let statusColor = '#ff9800';
                
                if (empStatus === 'approved') {
                    statusIcon = '✅';
                    statusText = 'Approved';
                    statusColor = '#4CAF50';
                } else if (empStatus === 'declined') {
                    statusIcon = '❌';
                    statusText = 'Declined';
                    statusColor = '#f44336';
                } else if (empStatus === 'izin') {
                    statusIcon = '📝';
                    statusText = 'Izin';
                    statusColor = '#2196F3';
                } else if (empStatus === 'sakit') {
                    statusIcon = '🏥';
                    statusText = 'Sakit';
                    statusColor = '#9C27B0';
                } else if (empStatus === 'reschedule') {
                    statusIcon = '🔄';
                    statusText = 'Reschedule';
                    statusColor = '#FF5722';
                }
                
                const empDiv = document.createElement('div');
                empDiv.style.cssText = `display: flex; align-items: center; justify-content: space-between; padding: 12px; margin-bottom: 8px; background-color: ${isEmpLocked ? '#f5f5f5' : 'white'}; border-radius: 6px; border: 1px solid ${isEmpLocked ? '#ddd' : '#e0e0e0'};`;
                
                empDiv.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                        <input type="checkbox" 
                               class="delete-employee-checkbox" 
                               data-assignment-id="${emp.id}" 
                               data-user-id="${emp.user_id}"
                               ${isEmpLocked ? 'disabled' : ''}>
                        <span style="font-size: 14px; color: #333; ${isEmpLocked ? 'opacity: 0.6;' : ''}">${emp.nama_lengkap || emp.pegawai_name || 'Unknown'}</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 12px; padding: 4px 8px; border-radius: 4px; background-color: ${statusColor}20; color: ${statusColor}; font-weight: 600;">
                            ${statusIcon} ${statusText}
                        </span>
                        ${isEmpLocked ? '<span style="font-size: 12px; color: #666;">🔒 Terkunci</span>' : ''}
                    </div>
                `;
                
                employeeList.appendChild(empDiv);
            });
            
            // Add select all checkbox if there are unlocked employees
            const unlockedCount = shiftGroup.employees.filter(emp => {
                const status = emp.status_konfirmasi || 'pending';
                return !(status === 'approved' || status === 'izin' || status === 'sakit' || status === 'reschedule');
            }).length;
            
            if (unlockedCount > 0) {
                const selectAllDiv = document.createElement('div');
                selectAllDiv.style.cssText = 'margin-top: 15px; padding-top: 15px; border-top: 2px solid #e0e0e0;';
                selectAllDiv.innerHTML = `
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 600; color: #333;">
                        <input type="checkbox" id="select-all-employees" style="cursor: pointer;">
                        <span>Pilih semua pegawai (${unlockedCount} pegawai bisa dihapus)</span>
                    </label>
                `;
                
                employeeList.appendChild(selectAllDiv);
                
                // Add select all functionality
                const selectAllCheckbox = selectAllDiv.querySelector('#select-all-employees');
                selectAllCheckbox.addEventListener('change', function() {
                    const checkboxes = employeeList.querySelectorAll('.delete-employee-checkbox:not([disabled])');
                    checkboxes.forEach(cb => cb.checked = this.checked);
                });
            }
        }
        
        // Show modal
        modal.style.display = 'block';
    };
    
    // Close delete modal
    KalenderDelete.closeDeleteModal = function() {
        const modal = document.getElementById('day-delete-modal');
        if (modal) {
            modal.style.display = 'none';
            currentShiftGroup = null;
        }
    };
    
    // Confirm and delete selected employees from shift
    KalenderDelete.confirmDelete = async function(reloadCallback) {
        const checkboxes = document.querySelectorAll('.delete-employee-checkbox:checked:not([disabled])');
        
        if (checkboxes.length === 0) {
            alert('❌ Pilih minimal 1 pegawai untuk dihapus');
            return;
        }
        
        const confirmMsg = `⚠️ Anda akan menghapus ${checkboxes.length} pegawai dari shift ini.\n\nApakah Anda yakin?`;
        if (!confirm(confirmMsg)) {
            return;
        }
        
        try {
            let successCount = 0;
            let failCount = 0;
            
            for (const checkbox of checkboxes) {
                const assignmentId = checkbox.dataset.assignmentId;
                
                const result = await window.KalenderAPI.deleteAssignment(assignmentId);
                
                if (result.status === 'success') {
                    successCount++;
                } else {
                    failCount++;
                    console.error('Failed to delete assignment:', assignmentId, result);
                }
            }
            
            if (successCount > 0) {
                alert(`✅ Berhasil menghapus ${successCount} pegawai dari shift${failCount > 0 ? `\n⚠️ ${failCount} gagal dihapus` : ''}`);
                KalenderDelete.closeDeleteModal();
                if (reloadCallback) reloadCallback();
            } else {
                alert('❌ Gagal menghapus shift');
            }
        } catch (error) {
            console.error('Error deleting shift assignments:', error);
            alert('❌ Terjadi kesalahan saat menghapus shift');
        }
    };
    
    // Export to window
    window.KalenderDelete = KalenderDelete;
    
    console.log('✅ KalenderDelete module loaded');
    
})(window);
