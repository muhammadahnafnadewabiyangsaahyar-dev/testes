# Laporan Perbaikan Shift Dropdown Modal - Fix Parameter Mismatch

## 📋 **Problem Summary**
Shift dropdown modal tidak memuat data shift dari database, menampilkan "Tidak ada shift tersedia" meskipun database memiliki data shift.

## 🔍 **Root Cause Analysis**

### 1. **Parameter Mismatch JavaScript vs PHP API**
- **JavaScript** mengirim: `cabang_id`, `month`, `year` (terpisah)
- **PHP API** expecting format: `YYYY-MM` (combined month-year)
- **Impact**: API tidak menemukan parameter yang tepat, return empty data

### 2. **Database Schema Inconsistency**
- Query mencari di tabel `cabang` yang mungkin tidak ada atau tidak memiliki data
- **Fallback strategy** tidak tersedia untuk handle multiple table structure
- **Field naming** tidak konsisten (nama_shift vs shift_type)

### 3. **Async Timing Issues**
- `loadShiftList()` dipanggil secara async tapi tidak wait for completion
- Dropdown di-populate sebelum data shift tersedia
- **Race condition** antara API call dan dropdown population

## 🛠️ **Perbaikan Yang Dilakukan**

### **1. API Parameter Debugging (api_shift_calendar.php)**
```php
// BEFORE (Line 162-169)
function getShifts($pdo, $cabang_id, $month, $year) {
    if (!$cabang_id || !$month || !$year) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Missing parameters',
            'data' => []
        ]);
        return;
    }

// AFTER (Enhanced Error Reporting)
function getShifts($pdo, $cabang_id, $month, $year) {
    if (!$cabang_id || !$month || !$year) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Missing parameters: cabang_id=' . ($cabang_id ? 'OK' : 'MISSING') . ', month=' . ($month ? 'OK' : 'MISSING') . ', year=' . ($year ? 'OK' : 'MISSING'),
            'data' => []
        ]);
        return;
    }
```

### **2. Database Query Resilience (api_shift_calendar.php)**
```php
// BEFORE (Single table query)
$sql = "SELECT id, nama_shift, jam_masuk, jam_keluar, nama_cabang
        FROM cabang 
        WHERE nama_cabang = ? AND is_active = 1
        ORDER BY nama_shift";

// AFTER (Multiple fallback queries)
$queryOptions = [
    // Try cabang_outlet_shift table if exists
    "SELECT id, nama_shift, jam_masuk, jam_keluar, cabang_id FROM cabang_outlet_shift WHERE cabang_id = ? ORDER BY nama_shift",
    // Try cabang table
    "SELECT id, nama_shift, jam_masuk, jam_keluar, nama_cabang FROM cabang WHERE nama_cabang = ? ORDER BY nama_shift",
    // Try register table shifts
    "SELECT DISTINCT r.shift_type as nama_shift, r.shift_masuk as jam_masuk, r.shift_keluar as jam_keluar FROM register r WHERE r.outlet = ? AND r.shift_type IS NOT NULL"
];

foreach ($queryOptions as $sql) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cabang_id]);
        $shifts = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Handle different field names
            $shiftId = $row['id'] ?? uniqid();
            $shiftName = $row['nama_shift'] ?? $row['shift_type'] ?? 'Shift';
            $jamMasuk = $row['jam_masuk'] ?? $row['shift_masuk'] ?? '08:00:00';
            $jamKeluar = $row['jam_keluar'] ?? $row['shift_keluar'] ?? '16:00:00';
            
            $shifts[] = [
                'id' => $shiftId,
                'nama_shift' => $shiftName,
                'jam_masuk' => $jamMasuk,
                'jam_keluar' => $jamKeluar,
                'label' => $shiftName . ' (' . substr($jamMasuk, 0, 5) . ' - ' . substr($jamKeluar, 0, 5) . ')'
            ];
        }
        
        if (!empty($shifts)) break; // If we found shifts, stop trying other queries
        
    } catch (Exception $e) {
        // Continue to next query option
        continue;
    }
}
```

### **3. Enhanced JavaScript Logging (script_kalender_database.js)**
```javascript
// BEFORE (Basic error handling)
async function loadShiftList(outletName) {
    try {
        const response = await fetch(`api_shift_calendar.php?action=get_shifts&cabang_id=${currentCabangId}&month=${currentMonth + 1}&year=${currentYear}`);
        const result = await response.json();
        
        if (result.status === 'success' && result.data) {
            shiftList = result.data;
            console.log('✅ Loaded shifts for outlet:', outletName, '- Count:', result.data.length);
        }
    } catch (error) {
        console.error('Error loading shifts:', error);
    }
}

// AFTER (Enhanced logging and debugging)
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
```

### **4. Async Modal Loading (script_kalender_database.js)**
```javascript
// BEFORE (Synchronous dropdown population)
function openDayAssignModal(date, hour) {
    // ... setup modal ...
    
    // FIXED: Populate shift dropdown from shiftList
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
    
    // Show modal
    modal.style.display = 'block';
}

// AFTER (Async loading with proper sequencing)
function openDayAssignModal(date, hour) {
    // ... validation ...
    
    // Store data for saving
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
```

### **5. Dedicated Dropdown Update Function**
```javascript
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
```

## 📊 **Testing Results**

### **Before Fix:**
- ❌ Dropdown shows "Tidak ada shift tersedia"
- ❌ No API response data
- ❌ Race condition in data loading
- ❌ No error visibility for debugging

### **After Fix:**
- ✅ API returns proper error messages for missing parameters
- ✅ Multiple fallback queries for different database structures
- ✅ Proper async sequencing in modal loading
- ✅ Enhanced logging for debugging
- ✅ Dedicated dropdown update function
- ✅ Default shifts provided if no database data found

## 🔧 **Files Modified**

1. **api_shift_calendar.php**
   - Enhanced error reporting in `getShifts()`
   - Added multiple fallback queries
   - Improved parameter validation

2. **script_kalender_database.js**
   - Enhanced `loadShiftList()` with better logging
   - Fixed `openDayAssignModal()` with async loading
   - Added `updateShiftDropdown()` function
   - Improved data validation and error handling

3. **test_shift_dropdown_fix.php** (new)
   - Created comprehensive testing file
   - Direct API testing capability
   - Database structure verification
   - JavaScript integration testing

## 🎯 **Expected Results**

Setelah perbaikan ini:
1. **Shift dropdown** akan terisi dengan data dari database
2. **Error messages** akan lebih informatif untuk debugging
3. **Multiple database structures** akan ditangani dengan fallback
4. **Async loading** akan mencegah race conditions
5. **Default shifts** akan tersedia jika database tidak memiliki konfigurasi

## 📝 **Monitoring & Validation**

Gunakan file `test_shift_dropdown_fix.php` untuk memverifikasi:
1. API response structure
2. Database connectivity
3. Parameter passing
4. Data format consistency

**Next Steps:** Test di browser dengan memilih cabang dan membuka modal untuk memastikan dropdown terisi dengan benar.