# 🚀 NEXT STEPS - IMPLEMENTASI SISTEM IZIN KAORI

## 📋 LANGKAH-LANGKAH IMPLEMENTASI

### **1. JALANKAN MIGRASI DATABASE** ⏳

```bash
# Login ke server dan masuk ke direktori aplikasi
cd /path/to/aplikasi

# Jalankan script migrasi database
php simple_migration.php
```

**Yang akan terjadi:**
- ✅ Table `pengajuan_izin` akan di-enhanced
- ✅ Kolom baru akan ditambahkan (jenis_izin, require_dokumen_medis, dll)
- ✅ Directories upload akan dibuat otomatis
- ✅ Data existing akan dipindahkan dengan aman

**Expected Output:**
```
Starting simplified database migration (no supervisor workflow)...
✓ Added column: jenis_izin
✓ Added column: require_dokumen_medis
✓ Added column: dokumen_medis_file
...
🎉 SIMPLIFIED LEAVE SYSTEM MIGRATION COMPLETED!
```

### **2. DEPLOY FILE-FILE YANG SUDAH DIPERBAIKI** 📁

#### **A. Replace suratizin.php (If not already done)**
```bash
# Backup file lama (optional)
cp suratizin.php suratizin_backup.php

# File sudah ready - tidak perlu replacement
# suratizin.php sudah di-update dengan versi terbaru
```

#### **B. Upload form_input_fixes.css**
```bash
# File form_input_fixes.css sudah dibuat
# Pastikan ada di root directory aplikasi
ls -la form_input_fixes.css
```

#### **C. Verify Directory Structure**
```bash
# Pastikan directories upload ada
ls -la uploads/
# Should show:
# - uploads/
# - uploads/dokumen_medis/ (new)
# - uploads/surat_izin/
# - uploads/tanda_tangan/

# Set permission jika perlu
chmod 755 uploads/dokumen_medis/
chmod 755 uploads/surat_izin/
chmod 755 uploads/tanda_tangan/
```

### **3. TEST SISTEM** 🧪

#### **A. Test Database Connection**
```bash
# Test dengan file kecil
php -r "
try {
    include 'connect.php';
    echo 'Database connection: OK\n';
    $stmt = $pdo->query('SELECT COUNT(*) FROM pengajuan_izin');
    echo 'Table access: OK\n';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . '\n';
}
"
```

#### **B. Test Web Interface**
1. **Login ke aplikasi** sebagai user
2. **Navigate ke halaman izin**: `suratizin.php`
3. **Test Card Selection**: 
   - ✅ Klik "Izin Biasa" - should show white container
   - ✅ Klik "Izin Sakit" - should show red container
4. **Test Form Input**:
   - ✅ Ketik di text input - values should appear
   - ✅ Select date - should work
   - ✅ Upload file - should work
5. **Test Business Rules**:
   - ✅ Test izin sakit 1 hari - no document required
   - ✅ Test izin sakit ≥2 hari - document required warning

#### **C. Test Form Submission**
```bash
# Test basic submission
curl -X POST -F "jenis_izin=izin" \
     -F "perihal=Test" \
     -F "tanggal_izin=2025-11-15" \
     -F "tanggal_selesai=2025-11-15" \
     -F "lama_izin=1" \
     -F "alasan_izin=Test reason" \
     -F "signature_data=data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==" \
     http://your-domain.com/suratizin.php
```

### **4. MONITORING & DEBUGGING** 🔍

#### **A. Check PHP Error Logs**
```bash
tail -f /var/log/php_errors.log
# atau
tail -f /Applications/XAMPP/xamppfiles/htdocs/aplikasi/logs/error.log
```

#### **B. Check Database Data**
```sql
-- Login ke MySQL/MariaDB
mysql -u username -p database_name

-- Check new columns exist
DESCRIBE pengajuan_izin;

-- Check data exists
SELECT COUNT(*) FROM pengajuan_izin;
SELECT jenis_izin, COUNT(*) FROM pengajuan_izin GROUP BY jenis_izin;
```

#### **C. Test Signature Pad**
```bash
# Test signature functionality
php -r "
try {
    include 'connect.php';
    echo 'Signature test: ' . (file_exists('uploads/tanda_tangan/') ? 'OK' : 'Missing dir') . '\n';
} catch (Exception \$e) {
    echo 'Error: ' . \$e->getMessage() . '\n';
}
"
```

### **5. PRODUCTION DEPLOYMENT** 🚀

#### **A. Pre-Deployment Checklist**
- [ ] Database migration berhasil
- [ ] File permissions correct
- [ ] Upload directories writable
- [ ] form_input_fixes.css loaded
- [ ] No PHP errors in logs

#### **B. Deploy to Production**
```bash
# 1. Backup current system
tar -czf backup_$(date +%Y%m%d_%H%M%S).tar.gz .

# 2. Deploy new files
# Copy suratizin.php, form_input_fixes.css, simple_migration.php

# 3. Run migration on production
php simple_migration.php

# 4. Test in production
# Open browser dan test functionality
```

#### **C. Post-Deployment**
- [ ] Monitor error logs for 24 hours
- [ ] Test with real user scenarios
- [ ] Verify notification system
- [ ] Check file uploads work
- [ ] Confirm email/telegram notifications

### **6. USER TRAINING** 👥

#### **A. Create User Guide**
- [ ] Screenshot of separated containers
- [ ] Step-by-step process untuk izin biasa vs sakit
- [ ] Medical document requirements explanation
- [ ] Troubleshooting guide

#### **B. Communication to Users**
- [ ] Email announcement tentang new features
- [ ] Training session (optional)
- [ ] FAQ document

---

## 🆘 TROUBLESHOOTING

### **If Migration Fails:**
```bash
# Check database connection
php -r "include 'connect.php'; echo 'OK';"

# Check table structure
mysql -u user -p -e "DESCRIBE pengajuan_izin;"

# Manual migration backup
cp simple_migration.php simple_migration_backup.php
```

### **If Form Input Still Not Working:**
1. **Check form_input_fixes.css is loaded**
2. **Clear browser cache**
3. **Check CSS file path**
4. **Verify upload permissions**

### **If Container Not Showing:**
1. **Check JavaScript is enabled**
2. **Check browser console for errors**
3. **Verify card click events working**

### **If File Upload Fails:**
```bash
# Check directory permissions
ls -la uploads/
chmod 755 uploads/
chmod 777 uploads/dokumen_medis/
```

---

## ✅ SUCCESS CRITERIA

**System siap production jika:**
- [ ] Migration berhasil tanpa error
- [ ] Form input menampilkan data dengan benar
- [ ] Container terpisah terlihat dan berfungsi
- [ ] Business rules bekerja (dokumen medis requirement)
- [ ] File upload berfungsi
- [ ] No PHP errors dalam logs
- [ ] User dapat submit izin tanpa masalah

---

## 📞 SUPPORT CONTACTS

Jika ada masalah, check:
1. **Error logs** di `/Applications/XAMPP/xamppfiles/htdocs/aplikasi/logs/`
2. **Database status** dengan menjalankan `simple_migration.php`
3. **File permissions** untuk directories upload
4. **Browser console** untuk JavaScript errors

**Status saat ini:** Sistem siap untuk implementasi! 🎉