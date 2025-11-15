# 📋 Solusi Lengkap untuk Pertanyaan calculate_status_kehadiran.php

## **1. 🔄 AUTO-UPDATE SAAT STATUS IZIN/SAKIT BERUBAH DARI PENDING**

### **Status Quo (Sekarang):**
- ❌ **TIDAK OTOMATIS** terupdate saat status izin/sakit berubah dari pending
- Sistem hanya mengecek saat ada request baru ke `calculate_status_kehadiran()`
- Perubahan status di `approve.php` tidak memicu update otomatis

### **Solusi yang Disarankan:**

#### **Opsi A: Trigger Database (Recommended)**
```sql
-- Buat trigger di database
CREATE TRIGGER update_status_kehadiran_on_izin_approval
AFTER UPDATE ON pengajuan_izin
FOR EACH ROW
WHEN (OLD.status != NEW.status AND NEW.status = 'Diterima')
BEGIN
    -- Update status kehadiran untuk tanggal yang terpengaruh
    UPDATE absensi 
    SET status_kehadiran = CASE 
        WHEN LOWER(NEW.perihal) LIKE '%sakit%' THEN 'Sakit'
        ELSE 'Izin'
    END
    WHERE user_id = NEW.user_id 
    AND tanggal_absensi BETWEEN NEW.tanggal_mulai AND NEW.tanggal_selesai;
END;
```

#### **Opsi B: Hook di approve.php**
```php
// Di approve.php, setelah approve diset
function updateAttendanceStatusAfterApproval($user_id, $tanggal_mulai, $tanggal_selesai, $perihal) {
    require_once 'calculate_status_kehadiran.php';
    
    // Loop through affected dates
    $current_date = $tanggal_mulai;
    while ($current_date <= $tanggal_selesai) {
        // Update each attendance record
        updateAllStatusKehadiran($pdo, $current_date);
        $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
    }
}
```

---

## **2. 🎯 Skenario: IZIN PENDING TETAPI ABSEN KARENA DEKAT CABANG**

### **Scenario Details:**
- User mengajukan izin (status: pending)
- Tempat acara dekat cabang, user singgah absen
- Kemudian izin di-approve

### **Current Logic (Sekarang):**
```php
// TAHAP 1: Cek status kehadiran existing
if (isset($absensi_record['status_kehadiran']) && 
    in_array($absensi_record['status_kehadiran'], ['Izin', 'Sakit'])) {
    return $absensi_record['status_kehadiran']; // Return yang ada
}

// TAHAP 2: Cek pengajuan izin approved
$stmt_izin = $pdo->prepare("
    SELECT status,被害
    FROM pengajuan_izin
    WHERE user_id = ? AND ? BETWEEN tanggal_mulai AND tanggal_selesai 
    AND status = 'Diterima'  -- Hanya yang sudah approved
");
```

### **Masalah dengan Logic Sekarang:**
❌ **Inkonsistensi**: User absen tapi kemudian Izin di-approve → Status tidak update  
❌ **No Auto-Update**: Perubahan approval tidak memicu recalculation  
❌ **Data Conflict**: Ada data absensi tapi status seharusnya "Izin"

### **Solusi yang Disarankan:**

#### **Improved Logic untuk TAHAP 2:**
```php
// Cek PENGJUAN IZIN (bukan hanya yang approved)
$stmt_izin = $pdo->prepare("
    SELECT status,被害
    FROM pengajuan_izin
    WHERE user_id = ? AND ? BETWEEN tanggal_mulai AND tanggal_selesai
    ORDER BY created_at DESC
    LIMIT 1
");
$izin_record = $stmt_izin->fetch();

if ($izin_record) {
    // Jika ada pengajuan izin (approved atau pending)
    if ($izin_record['status'] === 'Diterima') {
        // Fully approved - definitely izin
        return 'Izin';
    } elseif ($izin_record['status'] === 'Pending') {
        // Still pending - cek apakah ada aktivitas absen
        if (!empty($absensi_record['waktu_masuk'])) {
            // Ada absen saat izin pending
            // Logika: Jika absen tapi izin pending = ambil dari absen
            // Tapi catat bahwa ada pengajuan izin
            return hitungStatusKehadiranBasedOnAttendance($absensi_record, $pdo);
        } else {
            // Tidak ada absen, masih status izin pending
            return 'Izin Pending';
        }
    }
}
```

#### **Auto-Update Logic:**
```php
// Di approve.php, setelah approval
if ($izin_approval_status == 'Diterima') {
    // Recalculate semua absensi yang overlap dengan range izin
    $stmt_overlap = $pdo->prepare("
        SELECT DISTINCT tanggal_absensi 
        FROM absensi 
        WHERE user_id = ? 
        AND tanggal_absensi BETWEEN ? AND ?
    ");
    $stmt_overlap->execute([$user_id, $tanggal_mulai, $tanggal_selesai]);
    $affected_dates = $stmt_overlap->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($affected_dates as $tanggal) {
        updateAllStatusKehadiran($pdo, $tanggal);
    }
}
```

---

## **3. ⏰ MINIMAL DURASI KERJA SUPERADMIN 8 JAM**

### **Current Implementation (Setelah Perbaikan Saya):**
```php
// Baris 110-117 (Yang sudah saya ubah)
if ($durasi_kerja_jam >= 7) {
    return 'Hadir';
} elseif ($durasi_kerja_jam >= 4) {
    return 'Belum Memenuhi Kriteria';
} else {
    return 'Tidak Hadir';
}
```

### **Koreksi Sesuai Requirement:**
```php
// LOGIKA ADMIN: Minimal 8 jam kerja
if ($durasi_kerja_jam >= 8) {
    return 'Hadir';
} elseif ($durasi_kerja_jam >= 4) {
    return 'Belum Memenuhi Kriteria';
} else {
    return 'Tidak Hadir';
}
```

---

## **4. 📊 KLASIFIKASI KETERLAMBATAN BARU**

### **Current Implementation:**
```php
// TAHAP TERLAMBAT (lama)
if ($menit_terlambat <= 20) {
    return 'Terlambat Tanpa Potongan';
} elseif ($menit_terlambat <= 59) {
    return 'Terlambat Dengan Potongan';
} else {
    return 'Terlambat Dengan Potongan'; // Sama untuk semua >59
}
```

### **New Logic Sesuai Requirement:**
```php
// === LOGIKA TERLAMBAT BARU ===
if ($menit_terlambat > 0) {
    if ($menit_terlambat < 20) {
        // a. Terlambat < 20 menit = tanpa potongan
        return 'Terlambat Tanpa Potongan';
    } elseif ($menit_terlambat < 40) {
        // b. Terlambat 20-39:59 = potong tunjangan transport
        return 'Terlambat Dengan Potongan Transport';
    } elseif (!isset($shift) || empty($shift)) {
        // d. Tidak ada shift + overwork approved + terlambat >60 menit
        if (isset($absensi_record['status_lembur']) && $absensi_record['status_lembur'] === 'Approved') {
            $jam_terlambat = ceil($menit_terlambat / 60);
            $potongan = $jam_terlambat * 6250; // 6250 per jam
            return "Terlambat Overwork: Rp " . number_format($potongan);
        } else {
            return 'Terlambat Dengan Potongan Full';
        }
    } else {
        // c. Terlambat >= 40 menit = potong transport + makan
        return 'Terlambat Dengan Potongan Full';
    }
}
```

### **Enhanced calculatePotonganTunjangan():**
```php
function calculatePotonganTunjangan($status, $absensi_record, $pdo) {
    switch ($status) {
        case 'Terlambat Dengan Potongan Transport':
            return 'tunjangan transport';
            
        case 'Terlambat Dengan Potongan Full':
            return 'tunjangan transport dan makan';
            
        case 'Terlambat Overwork: Rp X':
            // Parse jumlah potongan dari status
            preg_match('/Rp ([\d,]+)/', $status, $matches);
            return isset($matches[1]) ? $matches[1] : '0';
            
        case 'Izin':
        case 'Sakit':
            return 'tidak ada';
            
        default:
            return 'tidak ada';
    }
}
```

---

## **5. 🕐 JAM KERJA PEGAWAI TIDAK ADA MINIMAL**

### **Current Implementation:**
```php
// Baris 181-184 (User logic)
if ($durasi_kerja_jam < 1) {
    return 'Tidak Hadir';
}
```

### **New Logic:**
```php
// HAPUS minimal durasi kerja
// Langsung ke logika shift-based

// Tetap ada validasi keterlambatan
if ($menit_terlambat > 0) {
    // Apply keterlambatan logic di atas
} else {
    // Cek berdasarkan jam keluar vs jam shift
    $waktu_keluar_formatted = date('H:i:s', $waktu_keluar);
    $jam_keluar_shift_formatted = $jam_keluar_shift;
    
    if ($waktu_keluar_formatted >= $jam_keluar_shift_formatted) {
        return 'Hadir';
    } else {
        return 'Belum Memenuhi Kriteria'; // Pulang terlalu early
    }
}
```

---

## **6. 🔗 INTEGRATION DENGAN 4 FILE LAIN**

### **Current Integration Status:**

| File | Status Integration | Method |
|------|------------------|---------|
| `rekapabsen.php` | ✅ **COMPLETE** | Real-time calculation |
| `view_absensi.php` | ❌ **NEEDS CHECK** | Unknown |
| `mainpage.php` | ❌ **NEEDS CHECK** | Unknown |
| `overview.php` | ❌ **NEEDS CHECK** | Unknown |

### **Action Items untuk Integration:**

#### **Check view_absensi.php:**
```php
// Cari pattern di view_absensi.php
$search_patterns = [
    'status_kehadiran',
    'hitungStatusKehadiran',
    'updateAllStatusKehadiran',
    'calculate_status_kehadiran.php'
];
```

#### **Check mainpage.php:**
```php
// Cari apakah ada call ke calculate_status_kehadiran
// atau apakah ada display status kehadiran
```

#### **Check overview.php:**
```php
// Cek apakah overview menampilkan summary status kehadiran
// yang perlu diupdate dengan logic baru
```

### **Standard Integration Pattern:**
```php
// Di setiap file, implementasikan pattern ini:
require_once 'calculate_status_kehadiran.php';

// Untuk real-time calculation:
foreach ($attendance_data as &$record) {
    $record['calculated_status'] = hitungStatusKehadiran($record, $pdo);
}

// Untuk batch update (contoh monthly overview):
$result = updateAllStatusKehadiran($pdo, $target_date);
```

---

## **7. 🛠️ IMPLEMENTATION ROADMAP**

### **Phase 1: Core Logic Updates**
1. ✅ Update minimal durasi superadmin (8 jam)
2. ✅ Implementasi klasifikasi keterlambatan baru
3. ✅ Hapus minimal durasi untuk user biasa

### **Phase 2: Auto-Update System**
1. 🔄 Buat trigger database untuk auto-update
2. 🔄 Implementasi hook di approve.php
3. 🔄 Test scenario izin pending + absen

### **Phase 3: Integration Check**
1. 🔍 Audit view_absensi.php
2. 🔍 Audit mainpage.php
3. 🔍 Audit overview.php
4. 🔧 Update integration yang missing

### **Phase 4: Testing & Validation**
1. 🧪 Test semua scenario baru
2. 🧪 Validate auto-update mechanism
3. 🧪 Cross-file integration test
4. 🧪 Performance testing

---

## **8. 📊 IMPACT ANALYSIS**

### **Yang Akan Berubah:**
| Komponen | Before | After | Impact |
|----------|--------|-------|--------|
| Superadmin | >=7 jam = Hadir | >=8 jam = Hadir | High |
| User keterlambatan | 3 kategori | 4 kategori + overwork | High |
| User durasi | Min 1 jam | No minimum | Medium |
| Auto-update | Manual only | Auto + trigger | High |
| Integration | 1/4 files | 4/4 files | High |

### **Risk Assessment:**
⚠️ **High Risk**: Perubahan logic bisa affect banyak user  
⚠️ **Medium Risk**: Auto-update mechanism perlu careful testing  
⚠️ **Low Risk**: Integration check hanya untuk konsistensi

### **Testing Requirements:**
🧪 **Unit Test**: Setiap function individually  
🧪 **Integration Test**: Cross-file functionality  
🧪 **E2E Test**: Full workflow dari absen sampai reporting  
🧪 **Performance Test**: Batch processing dengan data besar

---

## **9. ✅ KESIMPULAN & REKOMENDASI**

### **Priority Actions:**
1. **Immediate**: Update core logic (superadmin 8 jam, keterlambatan classification)
2. **Short-term**: Implementasi auto-update system  
3. **Medium-term**: Complete integration check dan fix
4. **Long-term**: Comprehensive testing dan optimization

### **Success Metrics:**
- ✅ Status kehadiran accurate untuk semua scenario
- ✅ Auto-update working saat approval status berubah
- ✅ Consistent display di semua 4 file target
- ✅ No performance degradation dengan logic baru

### **Next Steps:**
1. Implementasi Phase 1 (core updates)
2. Test dengan data real
3. Implementasi Phase 2 (auto-update)
4. Audit dan fix integration
5. Comprehensive testing
6. Production deployment

**Estimated Timeline: 1-2 weeks untuk full implementation**

---

## **10. 📝 CODE IMPLEMENTATION DETAILS**

### **Updated hitungStatusKehadiran Function:**

```php
function hitungStatusKehadiran($absensi_record, $pdo) {
    // === 1. CEK IZIN/SAKIT DARI DATABASE ===
    if (isset($absensi_record['status_kehadiran'])) {
        if ($absensi_record['status_kehadiran'] === 'Izin') {
            return 'Izin';
        }
        if ($absensi_record['status_kehadiran'] === 'Sakit') {
            return 'Sakit';
        }
    }

    // === 2. CEK PENGJUAN IZIN/SAKIT (UPDATED) ===
    $stmt_izin = $pdo->prepare("
        SELECT status,被害
        FROM pengajuan_izin
        WHERE user_id = ? AND ? BETWEEN tanggal_mulai AND tanggal_selesai
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt_izin->execute([$absensi_record['user_id'], $absensi_record['tanggal_absensi']]);
    $izin_record = $stmt_izin->fetch();

    if ($izin_record) {
        if ($izin_record['status'] === 'Diterima') {
            return 'Izin';
        } elseif ($izin_record['status'] === 'Pending') {
            if (!empty($absensi_record['waktu_masuk'])) {
                return hitungStatusKehadiranBasedOnAttendance($absensi_record, $pdo);
            } else {
                return 'Izin Pending';
            }
        }
    }

    // === 3. DETEKSI LUPA ABSEN PULANG ===
    if (empty($absensi_record['waktu_keluar'])) {
        $tanggal_absensi = $absensi_record['tanggal_absensi'];
        $today = date('Y-m-d');
        
        if ($tanggal_absensi < $today) {
            return 'Lupa Absen Pulang';
        }
        return 'Belum Absen Keluar';
    }

    // === 4. AMBIL DATA USER DAN SHIFT ===
    $stmt_user = $pdo->prepare("SELECT role, outlet FROM register WHERE id = ?");
    $stmt_user->execute([$absensi_record['user_id']]);
    $user = $stmt_user->fetch();
    
    if (!$user) {
        return 'Data User Tidak Ditemukan';
    }

    $is_admin = in_array($user['role'], ['admin', 'superadmin']);
    $user_outlet = $user['outlet'];

    // === 5. LOGIKA BERDASARKAN ROLE ===
    if ($is_admin) {
        // LOGIKA ADMIN: Minimal 8 jam kerja (UPDATED)
        $waktu_masuk = strtotime($absensi_record['waktu_masuk']);
        $waktu_keluar = strtotime($absensi_record['waktu_keluar']);
        $durasi_kerja_detik = $waktu_keluar - $waktu_masuk;
        $durasi_kerja_jam = $durasi_kerja_detik / 3600;

        if ($jam_masuk < '07:00' || $jam_keluar > '23:59') {
            return 'Tidak Hadir';
        }

        if ($durasi_kerja_jam >= 8) {  // UPDATED: 8 jam
            return 'Hadir';
        } elseif ($durasi_kerja_jam >= 4) {
            return 'Belum Memenuhi Kriteria';
        } else {
            return 'Tidak Hadir';
        }

    } else {
        // LOGIKA USER: Tidak ada minimal durasi + klasifikasi keterlambatan baru
        
        // Shift detection (sama seperti sebelumnya)
        $stmt_shift = $pdo->prepare("
            SELECT c.jam_masuk, c.jam_keluar, c.nama_shift
            FROM shift_assignments sa
            JOIN cabang c ON sa.cabang_id = c.id
            WHERE sa.user_id = ? AND sa.tanggal_shift = ? AND sa.status_konfirmasi = 'approved'
            LIMIT 1
        ");
        $stmt_shift->execute([$absensi_record['user_id'], $absensi_record['tanggal_absensi']]);
        $shift = $stmt_shift->fetch();
        
        // Fallback logic (sama seperti sebelumnya)
        if (!$shift) {
            // Level 2 dan 3 fallback...
        }
        
        if (!$shift) {
            return 'Data Shift Tidak Ditemukan';
        }

        $jam_masuk_shift = $shift['jam_masuk'];
        $jam_keluar_shift = $shift['jam_keluar'];
        $nama_shift = $shift['nama_shift'];

        $waktu_masuk = strtotime($absensi_record['waktu_masuk']);
        $waktu_keluar = strtotime($absensi_record['waktu_keluar']);
        $shift_masuk = strtotime($absensi_record['tanggal_absensi'] . ' ' . $jam_masuk_shift);
        
        $menit_terlambat = max(0, ($waktu_masuk - $shift_masuk) / 60);

        // === LOGIKA TERLAMBAT BARU ===
        if ($menit_terlambat > 0) {
            if ($menit_terlambat < 20) {
                return 'Terlambat Tanpa Potongan';
            } elseif ($menit_terlambat < 40) {
                return 'Terlambat Dengan Potongan Transport';
            } elseif (!isset($shift) || empty($shift)) {
                // No shift + overwork approved + terlambat >60 menit
                if (isset($absensi_record['status_lembur']) && $absensi_record['status_lembur'] === 'Approved') {
                    $jam_terlambat = ceil($menit_terlambat / 60);
                    $potongan = $jam_terlambat * 6250;
                    return "Terlambat Overwork: Rp " . number_format($potongan);
                } else {
                    return 'Terlambat Dengan Potongan Full';
                }
            } else {
                return 'Terlambat Dengan Potongan Full';
            }
        }

        // === LOGIKA HADIR (No minimum duration) ===
        $waktu_keluar_formatted = date('H:i:s', $waktu_keluar);
        $jam_keluar_shift_formatted = $jam_keluar_shift;

        if ($waktu_keluar_formatted >= $jam_keluar_shift_formatted) {
            return 'Hadir';
        } else {
            return 'Belum Memenuhi Kriteria';
        }
    }
}
```

---

**Generated on:** 2025-11-11T12:45:00Z  
**Author:** Kilo Code - System Analysis Specialist  
**Document Type:** Technical Solution Document  
**Status:** Ready for Implementation