# DOKUMENTASI FITUR DEVELOPMENT KAORI HR SYSTEM
## Step by Step Implementation Guide Berdasarkan Database Schema

---

## **INFORMASI DASAR**
- **Database:** MariaDB 10.4.28+ Compatible
- **Schema Version:** 3.2 (Production Ready)
- **Total Tables:** 11 Tables
- **Total Triggers:** 13 Triggers
- **Total Views:** 6 Views
- **Total Procedures:** 2 Stored Procedures
- **Development Approach:** Layer-by-Layer Implementation

---

## **ARSITEKTUR DATABASE OVERVIEW**

### **LAYER 1: FOUNDATION TABLES (3 Tables)**
- `outlets` - Master Outlet/Cabang Management
- `positions` - Master Posisi/Jabatan dengan Calculation Rules
- `users` - Consolidated User Management (Auth + Profiles + Whitelist + Telegram)

### **LAYER 2: CORE BUSINESS TABLES (5 Tables)**
- `shifts` - Master Shift Types dengan Dynamic Configuration
- `shift_assignments` - Employee Shift Scheduling (7-Status Workflow)
- `attendance` - 7 Status Types Attendance System
- `overwork_requests` - Overtime Management dengan Approval Workflow
- `leave_requests` - Leave Management (Izin + Sakit)

### **LAYER 3: FINANCIAL TABLES (2 Tables)**
- `payroll_temp` - Temporary Payroll Components (Finance Input)
- `payroll_records` - Final Payroll Archive dengan Complete Calculation
- `notification_logs` - Telegram Notification Audit Trail

---

## **ROADMAP DEVELOPMENT - 8 PHASES**

### **PHASE 1: FOUNDATION SYSTEM** 
**Priority: CRITICAL | Estimated Time: 1-2 Minggu**

#### **1.1 Outlet Management System**
**Database Tables:** `outlets`

**Fitur yang Harus Dibuat:**
- ✅ **CRUD Outlet/Cabang**
  - Form create/edit/delete outlet
  - Master data outlet dengan shift time configuration
  - GPS coordinate management untuk geofencing
  - Attendance time configuration (early start, late end minutes)

**Key Features:**
```php
// Endpoints yang harus dibuat:
GET /api/outlets                    // List all outlets
GET /api/outlets/{id}              // Get outlet detail
POST /api/outlets                  // Create new outlet
PUT /api/outlets/{id}              // Update outlet
DELETE /api/outlets/{id}           // Delete outlet
GET /api/outlets/{id}/shifts       // Get shifts for outlet
PUT /api/outlets/{id}/shift-config // Update shift time config
```

**UI Components:**
- Outlet management dashboard
- Shift configuration interface
- GPS location picker
- Attendance rules configuration

#### **1.2 Position Management System**
**Database Tables:** `positions`

**Fitur yang Harus Dibuat:**
- ✅ **CRUD Posisi/Jabatan**
  - Master data posisi dengan level (superadmin/admin/user)
  - Base salary components configuration
  - Calculation rules (overtime rate, late fee, absent penalty)
  - Working days per month configuration

**Key Features:**
```php
// Endpoints yang harus dibuat:
GET /api/positions                 // List all positions
GET /api/positions/{id}           // Get position detail
POST /api/positions               // Create new position
PUT /api/positions/{id}           // Update position
DELETE /api/positions/{id}        // Delete position
GET /api/positions/levels/{level} // Get positions by level
```

**UI Components:**
- Position management interface
- Salary component calculator
- Calculation rules editor

#### **1.3 User Management System**
**Database Tables:** `users`

**Fitur yang Harus Dibuat:**
- ✅ **User Registration & Authentication**
  - Whitelist-based registration
  - Username/email authentication
  - Role-based access control (superadmin/admin/user)
  - Profile management dengan foto dan tanda tangan

**Key Features:**
```php
// Endpoints yang harus dibuat:
POST /api/auth/register            // Register with whitelist
POST /api/auth/login               // Login user
POST /api/auth/logout              // Logout user
GET /api/auth/profile              // Get user profile
PUT /api/auth/profile              // Update user profile
POST /api/auth/upload-photo        // Upload profile photo
POST /api/auth/upload-signature    // Upload digital signature
GET /api/users                     // List users (admin only)
PUT /api/users/{id}/status         // Update user status
```

**UI Components:**
- Registration form dengan whitelist validation
- Login/logout interface
- User profile management
- Photo upload dan signature capture
- User management dashboard (admin)

---

### **PHASE 2: SHIFT MANAGEMENT SYSTEM**
**Priority: HIGH | Estimated Time: 1-2 Minggu**

#### **2.1 Master Shift Configuration**
**Database Tables:** `shifts`

**Fitur yang Harus Dibuat:**
- ✅ **Shift Master Data Management**
  - Dynamic shift creation per outlet
  - Shift time configuration (masuk/keluar)
  - Estimated hours dan break duration
  - Shift numbering system (1,2,3 untuk mapping ke outlet config)

**Key Features:**
```php
// Endpoints yang harus dibuat:
GET /api/shifts                                    // List all shifts
GET /api/shifts/{id}                              // Get shift detail
POST /api/shifts                                  // Create new shift
PUT /api/shifts/{id}                              // Update shift
DELETE /api/shifts/{id}                           // Delete shift
GET /api/outlets/{outlet_id}/shifts              // Get shifts by outlet
POST /api/shifts/bulk-create                      // Bulk create shifts for outlet
```

#### **2.2 Shift Assignment System**
**Database Tables:** `shift_assignments`

**Fitur yang Harus Dibuat:**
- ✅ **Employee Shift Scheduling**
  - Assign employees to shifts
  - 7-status workflow: d_assign → disetujui → completed
  - Approval system dengan dibuat_oleh/disetujui_oleh
  - Reschedule functionality dengan reason
  - Auto-lock setelah disetujui

**Key Features:**
```php
// Endpoints yang harus dibuat:
GET /api/shift-assignments                         // List assignments
POST /api/shift-assignments                        // Create assignment
PUT /api/shift-assignments/{id}                    // Update assignment
PUT /api/shift-assignments/{id}/approve           // Approve assignment
PUT /api/shift-assignments/{id}/reject            // Reject assignment
PUT /api/shift-assignments/{id}/reschedule        // Reschedule with reason
GET /api/users/{user_id}/shifts                   // Get user's shifts
GET /api/outlets/{outlet_id}/schedule/{date}      // Get outlet schedule
```

**UI Components:**
- Shift assignment calendar
- Employee shift scheduler
- Approval workflow interface
- Reschedule request form

---

### **PHASE 3: ATTENDANCE SYSTEM**
**Priority: HIGH | Estimated Time: 2-3 Minggu**

#### **3.1 Core Attendance Management**
**Database Tables:** `attendance`

**Fitur yang Harus Dibuat:**
- ✅ **7-Status Attendance System**
  - Attendance check-in/check-out
  - GPS location validation
  - Photo verification (masuk/keluar)
  - 7 Status types: hadir, belum_memenuhi_kriteria, tidak_hadir, terlambat_tanpa_potongan, terlambat_dengan_potongan, izin, sakit
  - Late minutes calculation
  - Overwork tracking dengan approval

**Key Features:**
```php
// Endpoints yang harus dibuat:
POST /api/attendance/checkin                      // Check in with GPS/photo
POST /api/attendance/checkout                     // Check out with GPS/photo
GET /api/attendance/user/{user_id}               // Get user attendance
GET /api/attendance/date/{date}                  // Get attendance by date
GET /api/attendance/outlet/{outlet_id}/{date}    // Get outlet attendance
PUT /api/attendance/{id}/status                  // Update attendance status
GET /api/attendance/reports/monthly/{month}/{year} // Monthly attendance report
```

**UI Components:**
- Mobile attendance interface (check-in/check-out)
- GPS location capture
- Photo capture untuk verification
- Attendance dashboard
- Late attendance management
- Attendance validation interface

#### **3.2 Attendance Logic Implementation**
**Database Features:** Triggers + Stored Procedures

**Automatic Calculations:**
- Late minutes calculation based on shift start time
- Overwork hours calculation
- Attendance status determination
- Automatic trigger untuk total_jam_kerja

**Key Implementation:**
```php
// Call stored procedure for attendance status calculation
CALL sp_calculate_attendance_status(
    user_id, 
    tanggal, 
    jam_masuk, 
    shift_start_time, 
    @status, 
    @late_minutes
);
```

---

### **PHASE 4: OVERTIME MANAGEMENT SYSTEM**
**Priority: MEDIUM | Estimated Time: 1-2 Minggu**

#### **4.1 Overwork Request System**
**Database Tables:** `overwork_requests`

**Fitur yang Harus Dibuat:**
- ✅ **Overtime Request & Approval**
  - Employee overwork request submission
  - Reason dan detail explanation
  - Approval workflow (pending → disetujui/ditolak)
  - Automatic payment calculation
  - Response time tracking

**Key Features:**
```php
// Endpoints yang harus dibuat:
POST /api/overwork-requests                       // Submit overwork request
GET /api/overwork-requests/user/{user_id}        // Get user's requests
GET /api/overwork-requests/pending               // Get pending requests (admin)
PUT /api/overwork-requests/{id}/approve          // Approve request
PUT /api/overwork-requests/{id}/reject           // Reject request
GET /api/overwork-requests/outlet/{outlet_id}    // Get outlet overwork requests
```

**UI Components:**
- Overwork request form
- Approval dashboard (admin)
- Payment calculation display
- Response time tracking

#### **4.2 Automatic Overwork Calculation**
**Database Features:** Triggers

**Automatic Features:**
- total_overwork_payment calculation (jam_overwork × overwork_rate)
- Response time calculation saat approval
- Integration dengan attendance system

---

### **PHASE 5: LEAVE MANAGEMENT SYSTEM**
**Priority: MEDIUM | Estimated Time: 1-2 Minggu**

#### **5.1 Leave Request System**
**Database Tables:** `leave_requests`

**Fitur yang Harus Dibuat:**
- ✅ **Leave Management (Izin + Sakit)**
  - Leave request submission dengan date range
  - Jenis: izin atau sakit
  - Perihal dan alasan dengan auto-formatting
  - Medical certificate upload untuk sick leave
  - Approval workflow
  - Auto-populate shift_assignment_id

**Key Features:**
```php
// Endpoints yang harus dibuat:
POST /api/leave-requests                          // Submit leave request
GET /api/leave-requests/user/{user_id}           // Get user's leave requests
GET /api/leave-requests/pending                  // Get pending requests (admin)
PUT /api/leave-requests/{id}/approve             // Approve leave
PUT /api/leave-requests/{id}/reject              // Reject leave
POST /api/leave-requests/{id}/upload-certificate // Upload medical certificate
GET /api/leave-requests/outlet/{outlet_id}       // Get outlet leave requests
```

**UI Components:**
- Leave request form
- Medical certificate upload
- Approval interface (admin)
- Leave calendar view
- Auto-populated shift assignment display

#### **5.2 Leave Integration Features**
**Database Features:** Triggers

**Automatic Features:**
- Auto-populate shift_assignment_id based on leave dates
- Status change tracking
- Integration dengan attendance system

---

### **PHASE 6: PAYROLL MANAGEMENT SYSTEM**
**Priority: HIGH | Estimated Time: 2-3 Minggu**

#### **6.1 Payroll Temp Management**
**Database Tables:** `payroll_temp`

**Fitur yang Harus Dibuat:**
- ✅ **Finance Input System**
  - Variable components input: hutang_toko, kasbon, bonus_marketing, insentif_omset
  - Status workflow: generated → finance_input → ready_for_users → archived
  - Timeline tracking dengan timestamps
  - Position-based payroll period management

**Key Features:**
```php
// Endpoints yang harus dibuat:
GET /api/payroll-temp                             // List payroll temp records
POST /api/payroll-temp                            // Generate payroll temp
PUT /api/payroll-temp/{id}                        // Update variable components
PUT /api/payroll-temp/{id}/status                 // Update status workflow
GET /api/payroll-temp/period/{bulan}/{tahun}      // Get payroll by period
GET /api/payroll-temp/position/{position_id}      // Get payroll by position
```

**UI Components:**
- Finance input interface
- Variable components calculator
- Payroll status workflow
- Period-based payroll management

#### **6.2 Payroll Records Generation**
**Database Tables:** `payroll_records`

**Fitur yang Harus Dibuat:**
- ✅ **Final Payroll Calculation**
  - Base components dari positions table
  - Variable components dari payroll_temp
  - Overwork bonus integration
  - Late deduction calculation
  - Total gaji, total potongan, gaji_bersih calculation
  - Attendance summary integration
  - JSON breakdown untuk audit

**Key Features:**
```php
// Endpoints yang harus dibuat:
POST /api/payroll-records/generate                // Generate payroll records
GET /api/payroll-records/user/{user_id}          // Get user payroll
GET /api/payroll-records/period/{bulan}/{tahun}  // Get period payroll
PUT /api/payroll-records/{id}/status             // Update status
GET /api/payroll-records/{id}/slip               // Generate payslip PDF
GET /api/payroll-records/export/{bulan}/{tahun}  // Export payroll data
```

#### **6.3 Automated Payroll Generation**
**Database Features:** Stored Procedures + Triggers

**Automatic Features:**
```sql
-- Call stored procedure for payroll generation
CALL sp_generate_monthly_payroll(user_id, bulan, tahun, @success, @message);
```

**Calculations yang Otomatis:**
- Gaji pokok, tunjangan_makan_total, tunjangan_transportasi_total
- Overwork bonus dari approved overwork_requests
- Late deduction dari attendance late minutes
- Total gaji dan gaji_bersih calculation
- Attendance summary integration

---

### **PHASE 7: NOTIFICATION SYSTEM**
**Priority: MEDIUM | Estimated Time: 1-2 Minggu**

#### **7.1 Telegram Integration**
**Database Tables:** `notification_logs`

**Fitur yang Harus Dibuat:**
- ✅ **Telegram Notification System**
  - 8 Notification types: shift_assigned, shift_confirmed, leave_submitted, overwork_request, payroll_ready, shift_reminder, attendance_alert, system_announcement
  - Message delivery tracking
  - Failure handling dan retry logic
  - Audit trail untuk semua notifications

**Key Features:**
```php
// Endpoints yang harus dibuat:
POST /api/notifications/send                     // Send notification
GET /api/notifications/user/{user_id}           // Get user notifications
GET /api/notifications/logs                     // Get notification audit logs
PUT /api/notifications/{id}/read                // Mark as read
GET /api/notifications/statistics               // Get notification statistics
```

**UI Components:**
- Notification center
- Delivery status dashboard
- Failed notification retry interface

---

### **PHASE 8: DASHBOARD & REPORTING SYSTEM**
**Priority: MEDIUM | Estimated Time: 2-3 Minggu**

#### **8.1 Executive Dashboard**
**Database Features:** Views

**Views yang Tersedia:**
- `v_users_detail` - User dengan posisi dan outlet
- `v_attendance_summary` - Monthly attendance summary
- `v_payroll_detail` - Payroll detail dengan komponen
- `v_shift_assignments_detail` - Shift assignment dengan detail
- `v_overwork_summary` - Overwork requests summary
- `v_leave_requests_detail` - Leave requests detail

**Dashboard Features:**
```php
// Endpoints yang harus dibuat:
GET /api/dashboard/overview                      // Executive overview
GET /api/dashboard/attendance/{bulan}/{tahun}   // Attendance dashboard
GET /api/dashboard/payroll/{bulan}/{tahun}      // Payroll dashboard
GET /api/dashboard/shifts/{outlet_id}/{date}    // Shift dashboard
GET /api/reports/attendance                     // Attendance reports
GET /api/reports/payroll                        // Payroll reports
GET /api/reports/performance                    // Performance reports
```

**UI Components:**
- Executive dashboard dengan KPIs
- Attendance analytics
- Payroll summary
- Shift management overview
- Performance metrics

#### **8.2 Advanced Reporting**
**Key Reports:**
- Monthly attendance report per employee
- Payroll breakdown report
- Overwork analysis report
- Leave pattern analysis
- Outlet performance comparison
- Salary component analysis

---

## **TECHNICAL IMPLEMENTATION DETAILS**

### **API Architecture**
```php
// Base URL structure
/api/v1/
├── auth/                 # Authentication endpoints
├── outlets/             # Outlet management
├── positions/           # Position management
├── users/               # User management
├── shifts/              # Shift management
├── shift-assignments/   # Shift assignment
├── attendance/          # Attendance system
├── overwork-requests/   # Overwork management
├── leave-requests/      # Leave management
├── payroll-temp/        # Payroll temp management
├── payroll-records/     # Payroll records
├── notifications/       # Notification system
├── dashboard/           # Dashboard data
└── reports/             # Reporting system
```

### **Database Trigger Integration**
```php
// Automatic triggers yang harus diintegrasikan
- tr_users_updated_at               // Auto update updated_at
- tr_positions_updated_at           // Auto update updated_at
- tr_outlets_updated_at             // Auto update updated_at
- tr_shifts_updated_at              // Auto update updated_at
- tr_shift_assignments_updated_at   // Auto update updated_at
- tr_attendance_updated_at          // Auto update updated_at
- tr_leave_requests_updated_at      // Auto update updated_at
- tr_payroll_records_updated_at     // Auto update updated_at
- tr_payroll_temp_calculate_variable        // Auto calculate total_variable (INSERT)
- tr_payroll_temp_calculate_variable_update // Auto calculate total_variable (UPDATE)
- tr_payroll_records_calculate_gaji         // Auto calculate gaji_bersih (INSERT)
- tr_payroll_records_calculate_gaji_update  // Auto calculate gaji_bersih (UPDATE)
- tr_leave_requests_shift_assignment        // Auto populate shift_assignment_id
- tr_payroll_temp_status_update             // Auto update status timeline
- tr_overwork_requests_calculate_payment    // Auto calculate payment (INSERT)
- tr_overwork_requests_calculate_payment_update // Auto calculate payment + response_time (UPDATE)
```

### **Mobile Application Considerations**
**Key Mobile Features:**
- GPS location capture untuk attendance
- Photo capture untuk attendance verification
- Push notifications untuk shift reminders
- Offline capability untuk remote areas
- Digital signature capture
- QR code scanning untuk quick attendance

---

## **DEPLOYMENT & TESTING STRATEGY**

### **Development Phases**
1. **Phase 1-2:** Foundation + Core Business (4-6 minggu)
2. **Phase 3-5:** Attendance + Overtime + Leave (4-7 minggu)
3. **Phase 6-8:** Payroll + Notification + Dashboard (5-8 minggu)

### **Testing Strategy**
- **Unit Testing:** Individual API endpoints
- **Integration Testing:** Database triggers dan procedures
- **Mobile Testing:** GPS, photo capture, offline sync
- **Performance Testing:** Attendance spike testing
- **Security Testing:** Authentication dan authorization

### **Deployment Strategy**
- **Staging Environment:** Full feature testing
- **Database Migration:** Schema deployment
- **API Deployment:** Versioned rollout
- **Mobile App Deployment:** App store submission
- **Training:** User training materials

---

## **SUCCESS METRICS**

### **Technical Metrics**
- ✅ API Response Time < 500ms
- ✅ Database Query Optimization
- ✅ Mobile App Performance > 4.5 rating
- ✅ 99.9% Uptime
- ✅ Real-time Notification Delivery

### **Business Metrics**
- ✅ Attendance Accuracy > 95%
- ✅ Payroll Processing Time < 2 hours
- ✅ User Adoption Rate > 80%
- ✅ Shift Assignment Efficiency
- ✅ Overtime Approval Time < 24 hours

---

## **CONCLUSION**

Dokumentasi ini menyediakan roadmap komprehensif untuk pengembangan sistem Kaori HR berdasarkan database schema yang telah diperbaiki. Implementasi layer-by-layer memastikan foundation yang solid sebelum membangun fitur-fitur kompleks.

**Key Success Factors:**
1. **Foundation First** - Pastikan outlet, position, dan user management stabil
2. **Mobile-First** - Fokus pada mobile experience untuk attendance
3. **Real-time Integration** - Manfaatkan database triggers untuk real-time calculations
4. **User-Friendly** - Interface yang intuitif untuk semua level user
5. **Scalable Architecture** - Design untuk growth dan expansion

**Next Steps:**
1. Review dan approve roadmap ini
2. Setup development environment
3. Mulai Phase 1 implementation
4. Establish testing protocols
5. Begin user training preparation

---

**Prepared by:** Kilo Code (System Architect)
**Date:** 2025-11-12
**Version:** 1.0
**Status:** Ready for Implementation ✅