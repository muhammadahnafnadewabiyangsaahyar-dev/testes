# FRONTEND INTEGRATION GUIDE HELMEPPO
## Panduan Perubahan Frontend untuk Backend API Integration

**Tanggal:** 12 November 2025  
**Versi:** 1.0  
**Tujuan:** Panduan perubahan frontend untuk migrasi ke backend API  

---

## RINGKASAN PERUBAHAN FRONTEND

Dokumen ini menjelaskan semua perubahan yang diperlukan pada frontend untuk berintegrasi dengan backend API baru. Migrasi ini akan meningkatkan keamanan, modularitas, dan performa sistem.

---

## 1. ARSITEKTUR BARU FRONTEND

### 1.1 Struktur Directory Frontend

```
frontend/
├── public/
│   ├── index.php              (NEW - Entry point)
│   ├── assets/
│   │   ├── css/
│   │   │   ├── style_modern.css (ENHANCE)
│   │   │   ├── api-client.css   (NEW)
│   │   │   └── components.css   (NEW)
│   │   └── js/
│   │       ├── api-client.js    (NEW - API wrapper)
│   │       ├── auth-manager.js  (NEW - JWT management)
│   │       ├── csrf-manager.js  (NEW - CSRF handling)
│   │       ├── components/      (NEW)
│   │       │   ├── AttendanceForm.js
│   │       │   ├── ShiftConfirmation.js
│   │       │   └── LeaveRequest.js
│   │       └── pages/           (NEW)
│   │           ├── dashboard.js
│   │           ├── profile.js
│   │           └── attendance.js
├── views/                     (NEW - Clean separation)
│   ├── layout/
│   │   ├── header.php         (ENHANCE - Add API links)
│   │   ├── footer.php         (ENHANCE - Add API status)
│   │   └── navbar.php         (ENHANCE - Add API indicators)
│   ├── pages/
│   │   ├── dashboard.php      (REFACTOR - Use API)
│   │   ├── profile.php        (REFACTOR - Use API)
│   │   ├── attendance.php     (REFACTOR - Use API)
│   │   └── shifts/            (NEW)
│   └── components/            (NEW)
│       ├── AttendanceForm.php
│       ├── ShiftCard.php
│       └── LeaveForm.php
├── api/                       (NEW - Frontend API utilities)
│   ├── client.js              (NEW - HTTP client wrapper)
│   ├── auth.js                (NEW - Authentication utilities)
│   ├── validation.js          (NEW - Client-side validation)
│   └── error-handler.js       (NEW - Error handling utilities)
└── config/                    (NEW)
    ├── api-endpoints.js       (NEW - Endpoint definitions)
    └── app-config.js          (NEW - Frontend configuration)
```

---

## 2. API CLIENT IMPLEMENTATION

### 2.1 Core API Client

```javascript
// frontend/api/client.js
class ApiClient {
    constructor(config = {}) {
        this.baseURL = config.baseURL || '/backend/public/api';
        this.timeout = config.timeout || 30000;
        this.csrfToken = this.getCSRFToken();
        
        // Set up request interceptors
        this.setupInterceptors();
    }
    
    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        const config = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.csrfToken,
                ...options.headers
            },
            credentials: 'include',
            ...options
        };
        
        // Add auth token if available
        const authToken = this.getAuthToken();
        if (authToken) {
            config.headers['Authorization'] = `Bearer ${authToken}`;
        }
        
        try {
            const response = await fetch(url, config);
            const data = await response.json();
            
            if (!response.ok) {
                throw new ApiError(data.message || 'Request failed', response.status, data);
            }
            
            return data;
        } catch (error) {
            if (error.name === 'ApiError') {
                throw error;
            }
            throw new ApiError('Network error: ' + error.message);
        }
    }
    
    // Convenience methods
    async get(endpoint, options = {}) {
        return this.request(endpoint, { method: 'GET', ...options });
    }
    
    async post(endpoint, data, options = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data),
            ...options
        });
    }
    
    async put(endpoint, data, options = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data),
            ...options
        });
    }
    
    async delete(endpoint, options = {}) {
        return this.request(endpoint, { method: 'DELETE', ...options });
    }
}

// Error handling class
class ApiError extends Error {
    constructor(message, status, data) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.data = data;
    }
}

// Global API client instance
window.apiClient = new ApiClient();
```

### 2.2 Authentication Management

```javascript
// frontend/api/auth.js
class AuthManager {
    constructor() {
        this.tokenKey = 'helmePPO_auth_token';
        this.userKey = 'helmePPO_user_data';
        this.loginUrl = '/backend/public/api/auth/login';
        this.logoutUrl = '/backend/public/api/auth/logout';
        this.meUrl = '/backend/public/api/auth/me';
    }
    
    async login(email, password) {
        try {
            const response = await window.apiClient.post('/auth/login', {
                email,
                password,
                csrf_token: this.getCSRFToken()
            });
            
            if (response.success) {
                this.setAuthData(response.data.token, response.data.user);
                return { success: true, user: response.data.user };
            }
            
            return { success: false, message: response.message };
        } catch (error) {
            return { success: false, message: error.message };
        }
    }
    
    async logout() {
        try {
            await window.apiClient.post('/auth/logout');
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            this.clearAuthData();
            window.location.href = '/index.php';
        }
    }
    
    async validateSession() {
        try {
            const response = await window.apiClient.get('/auth/me');
            if (response.success) {
                this.setUserData(response.data);
                return true;
            }
            return false;
        } catch (error) {
            this.clearAuthData();
            return false;
        }
    }
    
    isAuthenticated() {
        return !!this.getAuthToken();
    }
    
    getUser() {
        const userData = localStorage.getItem(this.userKey);
        return userData ? JSON.parse(userData) : null;
    }
    
    setAuthData(token, user) {
        localStorage.setItem(this.tokenKey, token);
        localStorage.setItem(this.userKey, JSON.stringify(user));
        window.apiClient.setAuthToken(token);
    }
    
    clearAuthData() {
        localStorage.removeItem(this.tokenKey);
        localStorage.removeItem(this.userKey);
        window.apiClient.clearAuthToken();
    }
    
    getAuthToken() {
        return localStorage.getItem(this.tokenKey);
    }
    
    getCSRFToken() {
        return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    }
}

// Global auth manager
window.authManager = new AuthManager();
```

---

## 3. COMPONENT REFACTORING

### 3.1 Attendance Component

#### Old Approach (process_absensi.php direct POST)
```javascript
// Old - Direct form submission
document.getElementById('absen-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    fetch('proses_absensi.php', {
        method: 'POST',
        body: formData
    }).then(response => response.json())
      .then(result => {
          // Handle result
      });
});
```

#### New Approach (API Client)
```javascript
// frontend/assets/js/components/AttendanceForm.js
class AttendanceForm {
    constructor(formId) {
        this.form = document.getElementById(formId);
        this.setupEventListeners();
    }
    
    setupEventListeners() {
        this.form.addEventListener('submit', this.handleSubmit.bind(this));
    }
    
    async handleSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(this.form);
        const data = {
            latitude: formData.get('latitude'),
            longitude: formData.get('longitude'),
            foto_absensi_base64: formData.get('foto_absensi_base64'),
            tipe_absen: formData.get('tipe_absen'),
            csrf_token: window.authManager.getCSRFToken()
        };
        
        try {
            const endpoint = data.tipe_absen === 'masuk' 
                ? '/attendance/checkin' 
                : '/attendance/checkout';
                
            const response = await window.apiClient.post(endpoint, data);
            
            if (response.success) {
                this.showSuccess(response.data);
                this.updateUIAfterAttendance(data.tipe_absen);
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            this.showError(error.message);
        }
    }
    
    showSuccess(data) {
        const message = data.status === 'checked_in' 
            ? 'Absen masuk berhasil!'
            : 'Absen keluar berhasil!';
            
        this.showNotification(message, 'success');
    }
    
    showError(message) {
        this.showNotification(message, 'error');
    }
    
    showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
    
    updateUIAfterAttendance(tipe) {
        // Update UI based on attendance type
        if (tipe === 'masuk') {
            document.getElementById('checkin-btn').disabled = true;
            document.getElementById('checkout-btn').disabled = false;
        } else {
            document.getElementById('checkin-btn').disabled = false;
            document.getElementById('checkout-btn').disabled = true;
        }
    }
}
```

### 3.2 Shift Confirmation Component

```javascript
// frontend/assets/js/components/ShiftConfirmation.js
class ShiftConfirmation {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.shifts = [];
        this.loadShifts();
    }
    
    async loadShifts() {
        try {
            const response = await window.apiClient.get('/shifts/confirmations?status=pending');
            
            if (response.success) {
                this.shifts = response.data.pending_shifts;
                this.renderShifts();
            }
        } catch (error) {
            console.error('Failed to load shifts:', error);
        }
    }
    
    renderShifts() {
        this.container.innerHTML = '';
        
        this.shifts.forEach(shift => {
            const shiftCard = this.createShiftCard(shift);
            this.container.appendChild(shiftCard);
        });
    }
    
    createShiftCard(shift) {
        const card = document.createElement('div');
        card.className = 'shift-card';
        card.innerHTML = `
            <div class="shift-info">
                <h3>${shift.tanggal_shift}</h3>
                <p>${shift.nama_cabang}</p>
                <p>${shift.jam_masuk} - ${shift.jam_keluar}</p>
            </div>
            <div class="shift-actions">
                <button onclick="shiftConfirmation.confirm(${shift.id})" class="btn-confirm">
                    Konfirmasi
                </button>
                <button onclick="shiftConfirmation.decline(${shift.id})" class="btn-decline">
                    Tolak
                </button>
            </div>
        `;
        
        return card;
    }
    
    async confirm(shiftId) {
        try {
            const response = await window.apiClient.post(`/shifts/confirmations/${shiftId}`, {
                action: 'confirmed',
                csrf_token: window.authManager.getCSRFToken()
            });
            
            if (response.success) {
                this.loadShifts(); // Reload shifts
                this.showNotification('Shift berhasil dikonfirmasi', 'success');
            }
        } catch (error) {
            this.showNotification('Gagal mengkonfirmasi shift', 'error');
        }
    }
    
    async decline(shiftId, reason, notes = '') {
        try {
            const response = await window.apiClient.post(`/shifts/confirmations/${shiftId}`, {
                action: 'declined',
                decline_reason: reason,
                notes: notes,
                csrf_token: window.authManager.getCSRFToken()
            });
            
            if (response.success) {
                this.loadShifts(); // Reload shifts
                this.showNotification('Shift berhasil ditolak', 'success');
            }
        } catch (error) {
            this.showNotification('Gagal menolak shift', 'error');
        }
    }
    
    showNotification(message, type) {
        // Implementation similar to AttendanceForm
    }
}

// Initialize global instance
window.shiftConfirmation = new ShiftConfirmation('shifts-container');
```

---

## 4. SECURITY ENHANCEMENTS

### 4.1 CSRF Token Management

```javascript
// frontend/api/csrf-manager.js
class CSRFManager {
    constructor() {
        this.tokenKey = 'helmePPO_csrf_token';
        this.generateToken();
    }
    
    generateToken() {
        // Generate random CSRF token
        const token = this.createRandomToken();
        localStorage.setItem(this.tokenKey, token);
        
        // Set token in meta tag for server-side access
        let meta = document.querySelector('meta[name="csrf-token"]');
        if (!meta) {
            meta = document.createElement('meta');
            meta.name = 'csrf-token';
            document.head.appendChild(meta);
        }
        meta.content = token;
    }
    
    getToken() {
        return localStorage.getItem(this.tokenKey);
    }
    
    createRandomToken() {
        return Array.from(crypto.getRandomValues(new Uint8Array(32)))
            .map(b => b.toString(16).padStart(2, '0'))
            .join('');
    }
    
    refreshToken() {
        this.generateToken();
    }
}

window.csrfManager = new CSRFManager();
```

### 4.2 Client-Side Validation

```javascript
// frontend/api/validation.js
class FormValidator {
    static validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    static validatePassword(password) {
        return password.length >= 8;
    }
    
    static validateRequired(value) {
        return value !== null && value !== undefined && value.trim() !== '';
    }
    
    static validateFileSize(file, maxSize = 5 * 1024 * 1024) { // 5MB
        return file.size <= maxSize;
    }
    
    static validateFileType(file, allowedTypes = ['image/jpeg', 'image/png']) {
        return allowedTypes.includes(file.type);
    }
    
    static showFieldError(field, message) {
        field.classList.add('error');
        let errorDiv = field.parentNode.querySelector('.error-message');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            field.parentNode.appendChild(errorDiv);
        }
        errorDiv.textContent = message;
    }
    
    static clearFieldError(field) {
        field.classList.remove('error');
        const errorDiv = field.parentNode.querySelector('.error-message');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
}
```

---

## 5. MIGRATION CHECKLIST

### 5.1 Pre-Migration Tasks

- [ ] Create new frontend directory structure
- [ ] Set up API client infrastructure
- [ ] Implement authentication management
- [ ] Create CSRF token system
- [ ] Setup error handling framework

### 5.2 Component Migration

- [ ] Migrate attendance form
- [ ] Migrate shift confirmation
- [ ] Migrate leave request form
- [ ] Migrate profile management
- [ ] Migrate payroll viewing

### 5.3 Testing Tasks

- [ ] Test authentication flow
- [ ] Test API error handling
- [ ] Test CSRF protection
- [ ] Test rate limiting
- [ ] Test mobile responsiveness

---

## KESIMPULAN

Perubahan frontend ini akan:

1. **Meningkatkan Keamanan** dengan CSRF protection dan secure authentication
2. **Memperbaiki Performa** dengan caching dan lazy loading
3. **Meningkatkan User Experience** dengan better error handling dan notifications
4. **Memudahkan Maintenance** dengan modular architecture
5. **Mendukung Mobile** dengan responsive API calls

### Next Steps:
1. Implement core API client infrastructure
2. Migrate high-priority components first
3. Test thoroughly in staging environment
4. Gradual rollout to production
5. Monitor performance and user feedback

---

**Prepared by:** Kilo Code - Frontend Architecture Specialist  
**Reviewed by:** Claude Code Architecture Reviewer  
**Integration Status:** Ready for Implementation