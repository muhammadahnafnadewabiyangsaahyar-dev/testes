# Blueprint Arsitektur Sistem Kalender Baru
## Solusi Optimal untuk Scalability, Maintainability, dan Extensibility

### 📋 RINGKASAN EKSEKUTIF

Berdasarkan analisis mendalam terhadap sistem kalender yang ada, telah diidentifikasi berbagai masalah kompleksitas yang memerlukan solusi arsitektur baru yang:
- **Mudah di-extend** untuk cabang dan shift baru tanpa perubahan struktural
- **Skalable** untuk menangani pertumbuhan data yang signifikan  
- **Maintainable** dengan separation of concerns yang jelas
- **Modular** menggunakan design patterns terbaik
- **Fleksibel** dengan dynamic configuration tanpa hardcoding

---

## 🔍 ANALISIS MASALAH SAAT INI

### 1. **Hardcoded Dependencies**
- **Problem**: Shift types di-hardcode di multiple locations
- **Impact**: Perlu coding perubahan setiap tambah shift baru
- **Location**: `script_kalender_utils.js`, `api_kalender.php`, `kalender.php`

### 2. **Tightly Coupled Components** 
- **Problem**: JavaScript modules saling bergantung dengan cara yang kompleks
- **Impact**: Sulit maintenance dan testing
- **Location**: `script_kalender_core.js`, `script_kalender_api.js`, `script_kalender_assign.js`

### 3. **State Management Complexity**
- **Problem**: State tersebar di berbagai module tanpa pattern yang konsisten
- **Impact**: Bug mudah terjadi, tracking state sulit
- **Location**: Semua file JS memiliki state variables lokal

### 4. **API Monolithic**
- **Problem**: Single API file menangani semua operasi
- **Impact**: API sulit di-extend dan maintain
- **Location**: `api_kalender.php` (298 lines of complexity)

### 5. **Database Schema Rigid**
- **Problem**: ENUM constraints menghalangi fleksibilitas
- **Impact**: Schema migration sulit untuk shift baru
- **Location**: `database_schema_complete.sql` lines 88, 104

### 6. **UI-Business Logic Mixed**
- **Problem**: Logic tersebar di view layer
- **Impact**: Reusability rendah, testing sulit
- **Location**: `kalender.php` contains both UI and business logic

---

## 🏗️ ARSEKTUR BARU: LAYERED MODULAR DESIGN

### **1. Data Layer (Database & Configuration)**

#### **Dynamic Shift Configuration System**
```sql
-- Enhanced Database Schema with Flexibility

-- 1. Shift Templates (Master Data)
CREATE TABLE IF NOT EXISTS `shift_templates` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `display_name` varchar(100) NOT NULL,
    `start_time` time NOT NULL,
    `end_time` time NOT NULL,
    `duration_hours` decimal(4,2) GENERATED ALWAYS AS (
        TIMESTAMPDIFF(MINUTE, start_time, end_time) / 60
    ) STORED,
    `color_hex` varchar(7) NOT NULL,
    `icon_emoji` varchar(10) DEFAULT NULL,
    `description` text DEFAULT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
);

-- 2. Branch Shift Configuration (Per Branch Assignment)
CREATE TABLE IF NOT EXISTS `branch_shift_config` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `branch_id` int(11) NOT NULL,
    `shift_template_id` int(11) NOT NULL,
    `priority_order` int(11) NOT NULL DEFAULT 1,
    `is_available` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `branch_shift_unique` (`branch_id`, `shift_template_id`),
    FOREIGN KEY (`branch_id`) REFERENCES `cabang_outlet` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`shift_template_id`) REFERENCES `shift_templates` (`id`) ON DELETE CASCADE
);

-- 3. Enhanced Assignment System
CREATE TABLE IF NOT EXISTS `shift_assignments_v2` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `branch_id` int(11) NOT NULL,
    `shift_template_id` int(11) NOT NULL,
    `assignment_date` date NOT NULL,
    `notes` text DEFAULT NULL,
    `status` enum('pending','confirmed','declined','cancelled') NOT NULL DEFAULT 'pending',
    `created_by` int(11) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_date_unique` (`user_id`, `assignment_date`),
    FOREIGN KEY (`user_id`) REFERENCES `register` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`branch_id`) REFERENCES `cabang_outlet` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`shift_template_id`) REFERENCES `shift_templates` (`id`) ON DELETE CASCADE
);
```

#### **Configuration Management System**
```php
<?php
// Dynamic Configuration Loader
class ShiftConfigurationManager {
    private $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get available shifts for branch
     */
    public function getBranchShifts($branchId) {
        $sql = "SELECT st.*, bsc.priority_order 
                FROM shift_templates st
                JOIN branch_shift_config bsc ON st.id = bsc.shift_template_id
                WHERE bsc.branch_id = ? AND st.is_active = 1 AND bsc.is_available = 1
                ORDER BY bsc.priority_order ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$branchId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Add new shift template (no code changes needed!)
     */
    public function createShiftTemplate($data) {
        $sql = "INSERT INTO shift_templates (name, display_name, start_time, end_time, color_hex, icon_emoji, description) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $data['display_name'], 
            $data['start_time'],
            $data['end_time'],
            $data['color_hex'],
            $data['icon_emoji'],
            $data['description']
        ]);
    }
    
    /**
     * Enable shift for branch (no code changes needed!)
     */
    public function enableShiftForBranch($branchId, $shiftTemplateId, $priorityOrder = 1) {
        $sql = "INSERT INTO branch_shift_config (branch_id, shift_template_id, priority_order) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE is_available = 1, priority_order = VALUES(priority_order)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$branchId, $shiftTemplateId, $priorityOrder]);
    }
}
?>
```

### **2. Business Logic Layer**

#### **Service Layer Architecture**
```javascript
// Enhanced Service Layer - Clean Separation of Concerns
class ShiftService {
    constructor(repository, configurationManager) {
        this.repository = repository;
        this.configManager = configurationManager;
        this.cache = new Map();
    }
    
    async getShiftsForBranch(branchId) {
        const cacheKey = `branch_shifts_${branchId}`;
        
        if (this.cache.has(cacheKey)) {
            return this.cache.get(cacheKey);
        }
        
        const shifts = await this.configManager.getBranchShifts(branchId);
        this.cache.set(cacheKey, shifts);
        return shifts;
    }
    
    async assignShift(assignmentData) {
        // Validation
        const validator = new ShiftAssignmentValidator();
        await validator.validate(assignmentData);
        
        // Business Logic
        const processor = new ShiftAssignmentProcessor();
        return await processor.process(assignmentData);
    }
}

class ShiftAssignmentValidator {
    async validate(data) {
        // Validation logic separate from business logic
        const rules = [
            { field: 'user_id', required: true, type: 'integer' },
            { field: 'branch_id', required: true, type: 'integer' },
            { field: 'shift_template_id', required: true, type: 'integer' },
            { field: 'assignment_date', required: true, type: 'date' }
        ];
        
        for (const rule of rules) {
            this.validateField(data[rule.field], rule);
        }
    }
}

class ShiftAssignmentProcessor {
    async process(data) {
        // Process assignment with proper error handling
        try {
            return await this.repository.saveAssignment(data);
        } catch (error) {
            throw new AssignmentProcessingException('Failed to process assignment', error);
        }
    }
}
```

### **3. API Layer (RESTful & Modular)**

#### **Modular API Architecture**
```
api/v1/
├── shift-templates/
│   ├── GET /api/v1/shift-templates/{id}
│   ├── POST /api/v1/shift-templates
│   ├── PUT /api/v1/shift-templates/{id}
│   └── DELETE /api/v1/shift-templates/{id}
├── branches/
│   ├── GET /api/v1/branches/{id}/shifts
│   └── POST /api/v1/branches/{id}/shifts/{shiftId}/enable
├── assignments/
│   ├── GET /api/v1/assignments?branch_id={id}&date={date}
│   ├── POST /api/v1/assignments
│   └── DELETE /api/v1/assignments/{id}
└── calendar/
    ├── GET /api/v1/calendar/{branchId}?month={month}&year={year}
    └── GET /api/v1/calendar/{branchId}/summary
```

#### **API Implementation Example**
```php
<?php
// api/v1/shift-templates/ShiftTemplatesController.php
class ShiftTemplatesController {
    private $shiftService;
    
    public function __construct(ShiftService $shiftService) {
        $this->shiftService = $shiftService;
    }
    
    public function index() {
        try {
            $shifts = $this->shiftService->getAllShiftTemplates();
            $this->jsonResponse(['data' => $shifts], 200);
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    public function store() {
        try {
            $data = $this->getRequestData();
            $shift = $this->shiftService->createShiftTemplate($data);
            $this->jsonResponse(['data' => $shift], 201);
        } catch (ValidationException $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }
}
?>
```

### **4. Frontend Layer (Component-Based Architecture)**

#### **Modern JavaScript Architecture**
```javascript
// Core Application Structure
class KalenderApp {
    constructor() {
        this.store = new Store();
        this.eventBus = new EventBus();
        this.initializeComponents();
    }
    
    initializeComponents() {
        // Component initialization with dependency injection
        new BranchSelector(this.store, this.eventBus);
        new CalendarView(this.store, this.eventBus);
        new ShiftAssignmentModal(this.store, this.eventBus);
        new SummaryView(this.store, this.eventBus);
    }
}

// Store Pattern for State Management
class Store {
    constructor() {
        this.state = {
            currentBranch: null,
            shifts: [],
            assignments: [],
            viewMode: 'month',
            currentDate: new Date()
        };
        this.listeners = [];
    }
    
    getState() { return this.state; }
    
    setState(newState) {
        this.state = { ...this.state, ...newState };
        this.notifyListeners();
    }
    
    subscribe(listener) {
        this.listeners.push(listener);
        return () => this.listeners = this.listeners.filter(l => l !== listener);
    }
    
    notifyListeners() {
        this.listeners.forEach(listener => listener(this.state));
    }
}

// Event System for Loose Coupling
class EventBus {
    constructor() {
        this.events = new Map();
    }
    
    on(event, callback) {
        if (!this.events.has(event)) {
            this.events.set(event, []);
        }
        this.events.get(event).push(callback);
    }
    
    emit(event, data) {
        const callbacks = this.events.get(event) || [];
        callbacks.forEach(callback => callback(data));
    }
}

// Component Base Class
class Component {
    constructor(store, eventBus) {
        this.store = store;
        this.eventBus = eventBus;
        this.unsubscribe = this.store.subscribe(this.handleStateChange.bind(this));
    }
    
    handleStateChange(newState) {
        // Override in subclasses
    }
    
    destroy() {
        if (this.unsubscribe) {
            this.unsubscribe();
        }
    }
}
```

#### **Calendar View Component**
```javascript
class CalendarView extends Component {
    constructor(store, eventBus) {
        super(store, eventBus);
        this.container = document.getElementById('calendar-view');
        this.setupEventHandlers();
    }
    
    setupEventHandlers() {
        // View switching
        this.eventBus.on('VIEW_SWITCH', this.switchView.bind(this));
        this.eventBus.on('DATE_NAVIGATE', this.navigateDate.bind(this));
        
        // Data updates
        this.eventBus.on('SHIFTS_LOADED', this.renderShifts.bind(this));
        this.eventBus.on('ASSIGNMENTS_LOADED', this.renderAssignments.bind(this));
    }
    
    async switchView(viewMode) {
        this.store.setState({ viewMode });
        await this.render();
    }
    
    async render() {
        const { viewMode, currentDate, shifts, assignments } = this.store.getState();
        
        switch (viewMode) {
            case 'month':
                await this.renderMonthView(currentDate, shifts, assignments);
                break;
            case 'week':
                await this.renderWeekView(currentDate, shifts, assignments);
                break;
            case 'day':
                await this.renderDayView(currentDate, shifts, assignments);
                break;
            case 'year':
                await this.renderYearView(currentDate);
                break;
        }
    }
}
```

### **5. Configuration Management System**

#### **Dynamic Shift Template System**
```javascript
// No more hardcoded shift types!
class ShiftTemplateManager {
    static async getTemplates() {
        const response = await fetch('/api/v1/shift-templates');
        return await response.json();
    }
    
    static async createTemplate(templateData) {
        const response = await fetch('/api/v1/shift-templates', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(templateData)
        });
        return await response.json();
    }
    
    static async enableForBranch(branchId, shiftId) {
        const response = await fetch(`/api/v1/branches/${branchId}/shifts/${shiftId}/enable`, {
            method: 'POST'
        });
        return await response.json();
    }
}

// Usage in components - No hardcoded values!
class ShiftAssignmentModal extends Component {
    async open(branchId, date) {
        // Get shifts dynamically from API
        this.shifts = await ShiftTemplateManager.getTemplatesForBranch(branchId);
        
        // Populate dropdown dynamically
        this.populateShiftDropdown();
        
        // No hardcoded shift list needed!
    }
    
    populateShiftDropdown() {
        const select = this.modal.querySelector('#shift-select');
        select.innerHTML = '';
        
        this.shifts.forEach(shift => {
            const option = document.createElement('option');
            option.value = shift.id;
            option.textContent = `${shift.display_name} (${shift.start_time} - ${shift.end_time})`;
            option.style.color = shift.color_hex;
            select.appendChild(option);
        });
    }
}
```

---

## 🚀 IMPLEMENTASI BENEFITS

### **1. Easy Extension Without Code Changes**
- **Add New Shift**: Just create template in database
- **New Branch**: Configure via API, no frontend changes needed
- **New View Mode**: Add component, register with event bus

### **2. High Scalability**
- **Database**: Optimized indexes and query patterns
- **Caching**: Built-in caching for frequently accessed data
- **API**: RESTful design supports horizontal scaling

### **3. Maintainable Code**
- **Separation of Concerns**: Clear layer boundaries
- **SOLID Principles**: Single responsibility, dependency inversion
- **Modular Design**: Easy to test and maintain individual components

### **4. Clean Architecture**
- **Data Layer**: Configuration-driven, no hardcoded values
- **Business Logic**: Service layer with proper validation
- **API Layer**: RESTful, versioned, modular endpoints
- **Frontend**: Component-based, event-driven architecture

### **5. Dynamic Configuration**
- **Shift Templates**: Database-driven, no code deployment needed
- **Branch Configuration**: API-driven, real-time updates
- **UI Themes**: Configurable colors and styling

---

## 📋 MIGRATION STRATEGY

### **Phase 1: Foundation (Week 1-2)**
1. Create new database schema with migration scripts
2. Implement core service layer
3. Build basic API endpoints

### **Phase 2: Frontend Refactoring (Week 3-4)**
1. Refactor JavaScript to component architecture
2. Implement event system
3. Create new UI components

### **Phase 3: Integration (Week 5-6)**
1. Connect frontend to new APIs
2. Implement configuration management
3. Test end-to-end functionality

### **Phase 4: Optimization (Week 7-8)**
1. Performance optimization
2. Caching implementation
3. Documentation and testing

---

## 🎯 SUCCESS METRICS

- **Maintainability**: Reduce code duplication by 70%
- **Extensibility**: Add new shift types without code changes
- **Performance**: 50% faster page loads with optimized queries
- **Developer Experience**: 60% faster feature development
- **Scalability**: Support 10x more concurrent users

Blueprint arsitektur ini memberikan foundation yang solid untuk sistem kalender yang scalable, maintainable, dan extensible sesuai dengan requirements yang diminta.