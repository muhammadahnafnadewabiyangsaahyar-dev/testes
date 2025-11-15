// ============ KALENDER ASSIGN MODULE ============
(function(window) {
    'use strict';
    
    const KalenderAssign = {};
    
    // Pegawai data for search
    let pegawaiData = [];
    
    // Open day assign modal
    KalenderAssign.openDayAssignModal = function(date, hour, currentCabangId, currentCabangName, shiftList, shiftAssignments) {
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
        
        // Populate shift dropdown
        if (modalShiftSelect) {
            modalShiftSelect.innerHTML = '<option value="">-- Pilih Shift --</option>';
            
            if (shiftList && shiftList.length > 0) {
                shiftList.forEach(shift => {
                    const option = document.createElement('option');
                    option.value = shift.id;
                    option.textContent = `${shift.nama_shift} (${shift.jam_masuk} - ${shift.jam_keluar})`;
                    option.dataset.jamMasuk = shift.jam_masuk;
                    option.dataset.jamKeluar = shift.jam_keluar;
                    option.dataset.namaShift = shift.nama_shift;
                    modalShiftSelect.appendChild(option);
                });
            } else {
                const option = document.createElement('option');
                option.value = "";
                option.textContent = "Tidak ada shift tersedia";
                option.disabled = true;
                modalShiftSelect.appendChild(option);
            }
        }
        
        // Store data - Fix timezone bug
        modal.dataset.date = window.KalenderUtils.formatDate(date);
        modal.dataset.hour = hour;
        
        // Load pegawai list
        loadPegawaiForDayAssign(currentCabangName, modal.dataset.date, shiftAssignments);
        
        // Show modal
        modal.style.display = 'block';
    };
    
    // Close day assign modal
    KalenderAssign.closeDayAssignModal = function() {
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
    };
    
    // Load pegawai for day assign
    async function loadPegawaiForDayAssign(cabangName, date, shiftAssignments) {
        if (!cabangName) {
            alert('❌ Pilih cabang terlebih dahulu!');
            return;
        }
        
        try {
            const data = await window.KalenderAPI.loadPegawai(cabangName);
            pegawaiData = data;
            
            const container = document.getElementById('pegawai-cards-container');
            if (container) {
                container.innerHTML = '';
                
                if (data.length === 0) {
                    container.innerHTML = '<p style="text-align: center; padding: 20px; color: #999;">Tidak ada pegawai di cabang ini</p>';
                } else {
                    data.forEach(pegawai => {
                        const card = createPegawaiCard(pegawai, date, shiftAssignments);
                        if (card) { // Only append if card was created (null = already has shift)
                            container.appendChild(card);
                        }
                    });
                    
                    // Check if all employees already have shifts
                    if (container.children.length === 0) {
                        container.innerHTML = '<p style="text-align: center; padding: 20px; color: #4CAF50; background-color: #e8f5e9; border-radius: 8px;">✅ Semua pegawai sudah memiliki shift untuk tanggal ini.<br><small>Gunakan modal Delete Shift untuk menghapus assignment jika perlu.</small></p>';
                    }
                }
                
                updateSelectedCount();
            }
        } catch (error) {
            console.error('Error loading pegawai for day assign:', error);
            const container = document.getElementById('pegawai-cards-container');
            if (container) {
                container.innerHTML = '<p style="text-align: center; padding: 20px; color: #f44336;">Error memuat data pegawai</p>';
            }
        }
    }
    
    // Create pegawai card
    function createPegawaiCard(pegawai, date, shiftAssignments) {
        const card = document.createElement('div');
        card.className = 'pegawai-card';
        card.dataset.pegawaiId = pegawai.id;
        card.dataset.pegawaiName = (pegawai.name || pegawai.nama_lengkap || '').toLowerCase();
        
        const shiftAssignment = checkIfPegawaiHasShift(pegawai.id, date, shiftAssignments);
        const hasShift = !!shiftAssignment;
        
        // IMPORTANT: Don't show card if pegawai already has shift
        // They should only appear in delete modal
        if (hasShift) {
            return null; // Don't create card for employees with existing shifts
        }
        
        const displayName = pegawai.name || pegawai.nama_lengkap || 'Tidak ada nama';
        const displayPosisi = pegawai.posisi || '-';
        const displayOutlet = pegawai.outlet || '-';
        
        card.innerHTML = `
            <input type="checkbox" class="pegawai-checkbox" data-pegawai-id="${pegawai.id}">
            <div class="pegawai-card-content">
                <div class="pegawai-card-name">${displayName}</div>
                <div class="pegawai-card-info">${displayPosisi} • ${displayOutlet}</div>
            </div>
        `;
        
        // Toggle selection on card click
        card.addEventListener('click', function(e) {
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
    
    // Toggle card selection
    function toggleCardSelection(card, isSelected) {
        if (isSelected) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
        updateSelectedCount();
    }
    
    // Update selected count
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
    
    // Check if pegawai has shift
    function checkIfPegawaiHasShift(pegawaiId, date, shiftAssignments) {
        if (!date || !shiftAssignments || typeof shiftAssignments !== 'object') {
            return null;
        }
        
        try {
            const assignments = Object.values(shiftAssignments);
            const assignment = assignments.find(assignment => 
                assignment.user_id == pegawaiId && 
                assignment.shift_date === date
            );
            
            return assignment || null;
        } catch (error) {
            console.error('checkIfPegawaiHasShift - Error:', error);
            return null;
        }
    }
    
    // Save day shift assignment
    KalenderAssign.saveDayShiftAssignment = async function(currentCabangId, shiftAssignments, reloadCallback) {
        const modal = document.getElementById('day-assign-modal');
        const date = modal?.dataset.date;
        const shiftSelect = document.getElementById('day-modal-shift-select');
        const selectedShiftId = shiftSelect?.value;
        
        if (!selectedShiftId) {
            alert('❌ Pilih shift terlebih dahulu!');
            return;
        }
        
        if (!date) {
            alert('❌ Tanggal tidak valid!');
            return;
        }
        
        // Get selected checkboxes (only new assignments)
        const selectedCheckboxes = document.querySelectorAll('.pegawai-checkbox:checked:not([disabled])');
        
        // Prepare assignments (no cancellations since we don't show existing ones)
        const assignments = Array.from(selectedCheckboxes).map(cb => {
            const pegawaiId = cb.dataset.pegawaiId;
            return {
                user_id: pegawaiId,
                cabang_id: selectedShiftId,
                tanggal_shift: date
            };
        });
        
        if (assignments.length === 0) {
            alert('ℹ️ Pilih minimal 1 pegawai untuk di-assign');
            return;
        }
        
        try {
            const result = await window.KalenderAPI.assignShifts(currentCabangId, assignments);
            
            console.log('📤 Assign API response:', result);
            
            if (result.status === 'success') {
                alert(`✅ Shift berhasil disimpan!\n- Ditambahkan: ${assignments.length} pegawai`);
                KalenderAssign.closeDayAssignModal();
                
                console.log('🔄 Reloading shift assignments...');
                if (reloadCallback) {
                    await reloadCallback();
                    console.log('✅ Reload complete');
                }
            } else {
                alert('❌ Gagal menyimpan shift: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error saving day shift assignment:', error);
            alert('❌ Terjadi kesalahan saat menyimpan shift');
        }
    };
    
    // Search pegawai
    KalenderAssign.searchPegawai = function(searchTerm) {
        const cards = document.querySelectorAll('.pegawai-card');
        const term = searchTerm.toLowerCase().trim();
        
        cards.forEach(card => {
            const name = card.dataset.pegawaiName || '';
            if (name.includes(term)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    };
    
    // Export to window
    window.KalenderAssign = KalenderAssign;
    
    console.log('✅ KalenderAssign module loaded');
    
})(window);
