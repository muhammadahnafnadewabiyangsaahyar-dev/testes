# RENCANA MIGRASI BACKEND HELMEPPO
## Migrasi Aman dari Skrip PHP Root ke Backend Terstruktur

**Tanggal:** 12 November 2025  
**Versi:** 1.0  
**Status:** Rencana Eksekusi Siap Dijalankan  

---

## RINGKASAN EKSEKUTIF

Rencana ini merancang migrasi bertahap yang aman untuk memindahkan logika bisnis dari skrip PHP root ke backend terstruktur dengan arsitektur modern. Migrasi ini akan meningkatkan keamanan, maintainability, dan skalabilitas sistem KAORI HR.

### Goals Utama:
- ✅ Memisahkan business logic dari presentation layer
- ✅ Implementasi security best practices (CSRF, CORS, SQL injection prevention)
- ✅ Modular API structure dengan proper error handling
- ✅ Backward compatibility selama proses migrasi
- ✅ Zero-downtime deployment strategy

---

## FASE 1: ARSITEKTUR BACKEND TERSTRUKTUR

### 1.1 Struktur Directory Backend

```
backend/
├── config/
│   ├── app.php                 ✅ (Bootstrap & autoloader)
│   ├── database.php            ✅ (PDO connection)
│   └── config.php              ✅ (App constants)
├── src/
│   ├── Controller/
│   │   ├── AuthController.php           (NEW)
│   │   ├── AttendanceController.php     (MIGRATE)
│   │   ├── ShiftController.php          (MIGRATE)
│   │   ├── LeaveController.php          (MIGRATE)
│   │   ├── PayrollController.php        (MIGRATE)
│   │   ├── UserController.php           (MIGRATE)
│   │   ├── FileController.php           (MIGRATE)
│   │   └── DashboardController.php      (MIGRATE)
│   ├── Service/
│   │   ├── AuthService.php              (NEW)
│   │   ├── AttendanceService.php        (ENHANCE)
│   │   ├── ShiftService.php             (NEW)
│   │   ├── LeaveService.php             (ENHANCE)
│   │   ├── PayrollService.php           (ENHANCE)
│   │   ├── NotificationService.php      (ENHANCE)
│   │   ├── FileUploadService.php        (NEW)
│   │   ├── ValidationService.php        (NEW)
│   │   └── SecurityService.php          (NEW)
│   ├── Repository/
│   │   ├── UserRepository.php           (NEW)
│   │   ├── AttendanceRepository.php     (NEW)
│   │   ├── ShiftRepository.php          (NEW)
│   │   ├── LeaveRepository.php          (NEW)
│   │   ├── PayrollRepository.php        (NEW)
│   │   └── BaseRepository.php           (NEW)
│   ├── Model/
│   │   ├── User.php                     (NEW - DTO)
│   │   ├── Attendance.php               (NEW - DTO)
│   │   ├── Shift.php                    (NEW - DTO)
│   │   ├── Leave.php                    (NEW - DTO)
│   │   ├── Payroll.php                  (NEW - DTO)
│   │   └── ApiResponse.php              (NEW - DTO)
│   ├── Middleware/
│   │   ├── AuthMiddleware.php           (NEW)
│   │   ├── CorsMiddleware.php           (NEW)
│   │   ├── RateLimitMiddleware.php      (NEW)
│   │   └── ValidationMiddleware.php     (NEW)
│   ├── Helper/
│   │   ├── SecurityHelper.php           (ENHANCE)
│   │   ├── EmailHelper.php              (ENHANCE)
│   │   ├── FileHelper.php               (ENHANCE)
│   │   └── ValidationHelper.php         (NEW)
│   └── Exception/
│       ├── BusinessException.php        (NEW)
│       ├── ValidationException.php      (NEW)
│       └── UnauthorizedException.php    (NEW)
├── public/
│   ├── api/
│   │   ├── index.php                    (NEW - Front controller)
│   │   ├── auth/                        (NEW)
│   │   │   ├── login.php                (MIGRATE)
│   │   │   ├── logout.php               (MIGRATE)
│   │   │   └── me.php                   (NEW)
│   │   ├── attendance/                  (ENHANCE)
│   │   │   ├── checkin.php              (ENHANCE)
│   │   │   ├── checkout.php             (ENHANCE)
│   │   │   └── status.php               (NEW)
│   │   ├── shifts/                      (NEW)
│   │   │   ├── confirm.php              (MIGRATE)
│   │   │   ├── calendar.php             (MIGRATE)
│   │   │   └── assignments.php          (NEW)
│   │   ├── leaves/                      (NEW)
│   │   │   ├── submit.php               (MIGRATE)
│   │   │   ├── approve.php              (MIGRATE)
│   │   │   └── status.php               (NEW)
│   │   ├── payroll/                     (NEW)
│   │   │   ├── generate.php             (MIGRATE)
│   │   │   ├── slips.php                (MIGRATE)
│   │   │   └── download.php             (NEW)
│   │   ├── user/                        (NEW)
│   │   │   ├── profile.php              (MIGRATE)
│   │   │   └── upload-photo.php         (MIGRATE)
│   │   └── files/                       (NEW)
│   │       ├── upload.php               (MIGRATE)
│   │       └── download.php             (NEW)
│   └── .htaccess                        (NEW)
└── tests/
    ├── unit/                            (NEW)
    ├── integration/                     (NEW)
    └── fixtures/                        (NEW)
```

---

## FASE 2: REST API ENDPOINTS DESIGN

### 2.1 Authentication Endpoints

#### POST `/api/auth/login`
```json
Request Body: {
    "email": "user@example.com",
    "password": "password123",
    "csrf_token": "token"
}

Response Success (200): {
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "user@example.com",
            "role": "karyawan",
            "outlet": "Cabang A"
        },
        "token": "jwt_token_here",
        "expires_at": "2025-11-12T18:30:00Z"
    }
}

Response Error (401): {
    "success": false,
    "error": "Invalid credentials"
}
```

#### POST `/api/auth/logout`
```json
Headers: {
    "Authorization": "Bearer jwt_token_here",
    "Content-Type": "application/json"
}

Response Success (200): {
    "success": true,
    "message": "Logged out successfully"
}
```

#### GET `/api/auth/me`
```json
Headers: {
    "Authorization": "Bearer jwt_token_here"
}

Response Success (200): {
    "success": true,
    "data": {
        "id": 1,
        "name": "John Doe",
        "email": "user@example.com",
        "role": "karyawan",
        "permissions": ["attendance", "profile"]
    }
}
```

### 2.2 Attendance Endpoints

#### POST `/api/attendance/checkin`
```json
Request Body: {
    "latitude": -5.1477,
    "longitude": 119.4327,
    "foto_absensi_base64": "base64_encoded_image",
    "accuracy": 10.5,
    "provider": "gps",
    "csrf_token": "token"
}

Response Success (200): {
    "success": true,
    "data": {
        "status": "checked_in",
        "waktu_masuk": "16:30:00",
        "status_keterlambatan": "on_time",
        "menit_terlambat": 0
    }
}
```

#### POST `/api/attendance/checkout`
```json
Request Body: {
    "csrf_token": "token"
}

Response Success (200): {
    "success": true,
    "data": {
        "status": "checked_out",
        "waktu_keluar": "16:30:00"
    }
}
```

### 2.3 Shift Management Endpoints

#### GET `/api/shifts/confirmations`
```json
Query Params: {
    "status": "pending" // pending, confirmed, declined
}

Response Success (200): {
    "success": true,
    "data": {
        "pending_shifts": [...],
        "confirmed_shifts": [...],
        "total_pending": 3
    }
}
```

#### POST `/api/shifts/confirmations/{id}`
```json
Request Body: {
    "action": "confirmed", // confirmed, declined
    "decline_reason": "sakit", // optional if declined
    "notes": "Tidak bisa hadir", // optional
    "csrf_token": "token"
}

Response Success (200): {
    "success": true,
    "message": "Shift konfirmasi berhasil disimpan"
}
```

### 2.4 Leave Management Endpoints

#### POST `/api/leaves/submit`
```json
Request Body: {
    "perihal": "Sakit",
    "tanggal_mulai": "2025-11-15",
    "tanggal_selesai": "2025-11-16",
    "lama_izin": 2,
    "alasan": "Demam dan flu",
    "csrf_token": "token"
}

Response Success (200): {
    "success": true,
    "message": "Pengajuan izin berhasil dikirim"
}
```

#### POST `/api/leaves/approve/{id}`
```json
Request Body: {
    "action": "approved", // approved, rejected
    "catatan": "Disetujui untuk istirahat",
    "csrf_token": "token"
}

Response Success (200): {
    "success": true,
    "message": "Pengajuan izin berhasil diproses"
}
```

### 2.5 Payroll Endpoints

#### POST `/api/payroll/generate`
```json
Request Body: {
    "periode_bulan": 11,
    "periode_tahun": 2025,
    "force_regenerate": false,
    "csrf_token": "token"
}

Response Success (200): {
    "success": true,
    "data": {
        "batch_id": 123,
        "total_employees": 50,
        "generated_slips": 48,
        "failed_slips": 2,
        "status": "processing"
    }
}
```

#### GET `/api/payroll/slips`
```json
Query Params: {
    "periode_bulan": 11,
    "periode_tahun": 2025,
    "user_id": 1 // optional
}

Response Success (200): {
    "success": true,
    "data": {
        "slips": [...],
        "pagination": {
            "total": 50,
            "page": 1,
            "per_page": 10
        }
    }
}
```

---

## FASE 3: SECURITY IMPLEMENTATION

### 3.1 Authentication & Authorization

#### JWT Token Implementation
```php
// backend/src/Service/AuthService.php
class AuthService {
    private const JWT_SECRET = 'helmePPO2025_secret_key';
    private const JWT_EXPIRY = 3600; // 1 hour
    
    public function generateToken(User $user): string {
        $payload = [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
            'exp' => time() + self::JWT_EXPIRY
        ];
        
        return JWT::encode($payload, self::JWT_SECRET);
    }
    
    public function validateToken(string $token): ?User {
        try {
            $decoded = JWT::decode($token, self::JWT_SECRET, ['HS256']);
            return $this->userRepository->findById($decoded->user_id);
        } catch (\Exception $e) {
            return null;
        }
    }
}
```

#### Role-Based Access Control
```php
// backend/src/Middleware/AuthMiddleware.php
class AuthMiddleware {
    public function handle(Request $request, Closure $next) {
        $token = $request->getHeader('Authorization');
        
        if (!$token || !str_starts_with($token, 'Bearer ')) {
            throw new UnauthorizedException('Missing or invalid authorization token');
        }
        
        $jwt = substr($token, 7);
        $user = $this->authService->validateToken($jwt);
        
        if (!$user) {
            throw new UnauthorizedException('Invalid token');
        }
        
        $request->setUser($user);
        return $next($request);
    }
}
```

### 3.2 Input Validation & Sanitization

#### Request Validator
```php
// backend/src/Service/ValidationService.php
class ValidationService {
    public function validateAttendanceRequest(array $data): array {
        $validator = new Validator();
        
        $validator->required('latitude')->numeric()->between(-90, 90);
        $validator->required('longitude')->numeric()->between(-180, 180);
        $validator->optional('foto_absensi_base64')->string()->max(5242880); // 5MB
        $validator->required('csrf_token')->string()->length(32);
        
        if (!$validator->validate($data)) {
            throw new ValidationException($validator->getErrors());
        }
        
        return $validator->getSanitizedData();
    }
    
    public function sanitizeInput(string $input): string {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}
```

### 3.3 Security Headers & CORS

#### CORS Configuration
```php
// backend/src/Middleware/CorsMiddleware.php
class CorsMiddleware {
    public function handle(Request $request, Closure $next) {
        $allowedOrigins = [
            'https://helmePPO.local',
            'https://app.helmePPO.com'
        ];
        
        $origin = $request->getHeader('Origin');
        
        if (in_array($origin, $allowedOrigins)) {
            $response = $next($request);
            
            $response->setHeader('Access-Control-Allow-Origin', $origin);
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
            $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            
            return $response;
        }
        
        return $next($request);
    }
}
```

### 3.4 Rate Limiting

#### Rate Limit Implementation
```php
// backend/src/Middleware/RateLimitMiddleware.php
class RateLimitMiddleware {
    public function handle(Request $request, Closure $next) {
        $key = 'rate_limit_' . $request->getUser()->getId();
        $maxAttempts = 10;
        $timeWindow = 3600; // 1 hour
        
        $attempts = $this->redis->incr($key);
        
        if ($attempts === 1) {
            $this->redis->expire($key, $timeWindow);
        }
        
        if ($attempts > $maxAttempts) {
            throw new TooManyRequestsException('Rate limit exceeded');
        }
        
        $response = $next($request);
        $response->setHeader('X-RateLimit-Limit', $maxAttempts);
        $response->setHeader('X-RateLimit-Remaining', $maxAttempts - $attempts);
        
        return $response;
    }
}
```

---

## FASE 4: STRATEGI MIGRASI BERTAPAH

### 4.1 Phase 1: Core Infrastructure (Week 1-2)

#### Create Backend Foundation
- [x] Setup autoloader dan namespace
- [x] Create base repositories dengan PDO
- [x] Implement authentication system
- [x] Setup security middleware
- [x] Create API response wrapper

#### Priority Files untuk Migrasi:
1. **proses_konfirmasi_lembur.php** → `/api/shifts/confirmations/{id}`
2. **upload_foto.php** → `/api/user/upload-photo`
3. **api_shift_confirmation_email.php** → Enhanced dalam NotificationService

#### Migration Strategy:
```php
// Old file: proses_konfirmasi_lembur.php
// New approach: backend/public/api/shifts/confirm.php

// Temporary bridge - maintain backward compatibility
// sambil system lama masih jalan
```

### 4.2 Phase 2: Critical Business Logic (Week 3-4)

#### High Priority Migrations:
1. **mainpage.php** → DashboardController + Service
2. **profile.php** → UserController + Service  
3. **shift_confirmation.php** → ShiftController + Service
4. **slip_gaji_management.php** → PayrollController + Service

#### Migration Pattern:
```php
// Example: mainpage.php migration
// Old: Direct database queries + HTML output
// New: 
// - src/Repository/DashboardRepository.php
// - src/Service/DashboardService.php  
// - src/Controller/DashboardController.php
// - public/api/dashboard.php
```

### 4.3 Phase 3: Complex Systems (Week 5-6)

#### Complex Logic Migrations:
1. **api_kalender.php** → Enhanced ShiftController
2. **auto_generate_slipgaji.php** → PayrollService dengan queue system
3. **approve.php** → LeaveController dengan workflow
4. **docx.php** → FileService dengan template system

### 4.4 Phase 4: Frontend Integration (Week 7-8)

#### Frontend Changes:
- Update fetch calls untuk API endpoints
- Implement JWT token management
- Add CSRF token handling
- Update error handling

---

## FASE 5: BACKWARD COMPATIBILITY STRATEGY

### 5.1 Dual System During Migration

```php
// Strategy: Run both systems parallel
// Phase 1: Backend API exists, frontend still uses old PHP
// Phase 2: Frontend gradually migrated to API calls
// Phase 3: Old PHP files deprecated but still functional
```

### 5.2 Gradual Migration Path

```php
// Migration Bridge Pattern
// Old files point to new services gradually

// Example in old profile.php:
if (BACKEND_API_AVAILABLE) {
    // Use new API endpoints
    $userService = new UserService();
    $result = $userService->updateProfile($data);
} else {
    // Fallback to old logic
    // ... existing code
}
```

### 5.3 Data Migration Safety

```php
// Ensure no data loss during migration
// - Write to both old and new tables temporarily
// - Rollback mechanism if migration fails
// - Audit trail for all changes
```

---

## FASE 6: TESTING & VALIDATION

### 6.1 Test Coverage Strategy

#### Unit Tests (70% coverage target)
```php
// Example test structure
tests/
├── unit/
│   ├── Service/
│   │   ├── AuthServiceTest.php
│   │   ├── AttendanceServiceTest.php
│   │   └── UserServiceTest.php
│   └── Repository/
│       ├── UserRepositoryTest.php
│       └── AttendanceRepositoryTest.php
```

#### Integration Tests (80% coverage target)
```php
integration/
├── api/
│   ├── auth/
│   │   └── LoginTest.php
│   ├── attendance/
│   │   └── CheckinTest.php
│   └── shifts/
│       └── ConfirmationTest.php
```

#### End-to-End Tests (Critical paths)
```javascript
// Playwright/Cypress tests
e2e/
├── authentication.spec.js
├── attendance-flow.spec.js
├── shift-management.spec.js
└── payroll-generation.spec.js
```

### 6.2 Performance Testing

#### Load Testing
- Concurrent user sessions (max 100 users)
- API response time < 500ms
- Database query optimization
- File upload handling (max 5MB)

#### Security Testing
- SQL injection prevention
- XSS protection
- CSRF token validation
- Rate limiting effectiveness

---

## FASE 7: DEPLOYMENT STRATEGY

### 7.1 Staged Deployment

#### Development Environment
- Local development setup
- Feature branch testing
- Code review process

#### Staging Environment  
- Production-like testing
- Integration testing
- Performance benchmarking
- Security scanning

#### Production Deployment
- Blue-green deployment
- Rollback mechanism
- Monitoring setup
- Health checks

### 7.2 Zero-Downtime Migration

```bash
# Deployment script
#!/bin/bash

# 1. Deploy new backend to staging
# 2. Run integration tests
# 3. Switch traffic gradually (10% → 50% → 100%)
# 4. Monitor for issues
# 5. Rollback if problems detected
```

### 7.3 Monitoring & Alerting

#### Application Monitoring
- API response times
- Error rates
- Database performance
- Memory usage

#### Business Metrics
- User activity patterns
- Attendance submission rates
- Error frequency by endpoint
- System availability

---

## FASE 8: TIMELINE & ESTIMASI

### 8.1 Development Timeline

| Phase | Duration | Activities | Deliverables |
|-------|----------|------------|--------------|
| **Phase 1** | 2 weeks | Infrastructure setup, Auth system, Core API | Backend foundation, JWT auth, Basic APIs |
| **Phase 2** | 2 weeks | Critical business logic migration | Dashboard, User profile, Shift confirmation |
| **Phase 3** | 2 weeks | Complex system migration | Calendar API, Payroll generation, Leave approval |
| **Phase 4** | 2 weeks | Frontend integration, Testing | Updated frontend, Full test coverage |
| **Phase 5** | 1 week | Deployment, Monitoring | Production deployment, monitoring setup |
| **Total** | **9 weeks** | **Complete migration** | **Modern, secure, maintainable backend** |

### 8.2 Resource Requirements

#### Development Team
- **1 Backend Developer** (Full-time)
- **1 Frontend Developer** (Part-time during Phase 4)
- **1 DevOps Engineer** (Part-time during deployment)
- **1 QA Engineer** (Part-time during testing)

#### Infrastructure
- **Development server** (existing)
- **Staging server** (existing)
- **Redis for rate limiting** (new)
- **Monitoring tools** (existing)

### 8.3 Risk Assessment

#### High Risk Items
1. **Data consistency during migration**
   - *Mitigation*: Transaction-based migration, rollback mechanism
2. **Performance degradation**
   - *Mitigation*: Load testing, query optimization
3. **Security vulnerabilities**
   - *Mitigation*: Security audit, penetration testing

#### Medium Risk Items
1. **Timeline delays**
   - *Mitigation*: Agile approach, feature prioritization
2. **User resistance to changes**
   - *Mitigation*: Gradual rollout, training, documentation

---

## FASE 9: SUCCESS METRICS

### 9.1 Technical Metrics

| Metric | Current | Target | Measurement |
|--------|---------|--------|-------------|
| **API Response Time** | N/A | <500ms | Application monitoring |
| **Code Coverage** | 0% | >80% | Unit test coverage |
| **Security Vulnerabilities** | Unknown | 0 High/Critical | Security scan |
| **Database Query Time** | Unknown | <100ms | Query monitoring |

### 9.2 Business Metrics

| Metric | Current | Target | Measurement |
|--------|---------|--------|-------------|
| **User Satisfaction** | Unknown | >90% | User feedback |
| **System Availability** | Unknown | 99.5% | Uptime monitoring |
| **Support Tickets** | Unknown | <50/month | Support system |
| **Development Velocity** | Unknown | +50% | Sprint velocity |

---

## FASE 10: DOCUMENTATION & TRAINING

### 10.1 Technical Documentation

#### API Documentation
- OpenAPI 3.0 specification
- Endpoint descriptions
- Request/response examples
- Authentication guide

#### Code Documentation
- Architecture decision records
- Code style guide
- Deployment procedures
- Troubleshooting guide

### 10.2 User Documentation

#### End User Guide
- New interface walkthrough
- Feature differences
- FAQ section
- Video tutorials

#### Admin Guide
- New admin functions
- System monitoring
- User management
- Troubleshooting

---

## KESIMPULAN & NEXT STEPS

### Immediate Actions (Week 1)
1. ✅ Setup backend directory structure
2. ✅ Create authentication service
3. ✅ Implement basic API endpoints
4. ✅ Setup development environment

### Week 2-3 Goals
- Complete Phase 1 migrations
- Begin Phase 2 business logic migration
- Setup automated testing
- Implement monitoring

### Success Indicators
- All critical business logic migrated
- Security measures implemented
- Performance targets met
- Zero critical bugs in production

### Long-term Vision
- Modern, scalable architecture
- Enhanced security posture
- Improved development velocity
- Better user experience

---

**Prepared by:** Kilo Code - Backend Architecture Specialist  
**Reviewed by:** Claude Code Architecture Reviewer  
**Next Review:** Weekly progress meetings  
**Status:** Ready for Implementation