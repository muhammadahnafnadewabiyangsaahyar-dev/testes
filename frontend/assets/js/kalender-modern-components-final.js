/**
 * Modern Calendar Components - Clean Architecture Implementation
 * No hardcoded shift types - all dynamic and API-driven
 */

class BranchSelector extends ModernKalenderArchitecture.Component {
    constructor(store, eventBus) {
        super(store, eventBus, {
            container: document.getElementById('cabang-select')?.parentNode
        });
    }
    
    setupEventListeners() {
        this.on('BRANCH_SELECTED', this.handleBranchSelected.bind(this));
        this.on('SHIFTS_LOADED', this.handleShiftsLoaded.bind(this));
        this.on('ASSIGNMENTS_LOADED', this.handleAssignmentsLoaded.bind(this));
        this.on('SHIFT_LOAD_ERROR', this.handleShiftLoadError.bind(this));
    }
    
    handleStateChange(newState) {
        const { currentBranchId, branches, isLoadingShifts, error } = newState;
        this.render();
        
        if (currentBranchId && !isLoadingShifts && !error) {
            this.loadShiftsForBranch(currentBranchId);
        }
    }
    
    async render() {
        const { branches, currentBranchId } = this.getState();
        const selectElement = document.getElementById('cabang-select');
        if (!selectElement) return;
        
        selectElement.innerHTML = '<option value="">-- Pilih Cabang --</option>';
        
        branches.forEach(branch => {
            const option = document.createElement('option');
            option.value = branch.id;
            option.textContent = branch.nama_cabang;
            option.selected = branch.id == currentBranchId;
            selectElement.appendChild(option);
        });
        
        selectElement.onchange = (event) => {
            const branchId = event.target.value;
            const branchName = event.target.options[event.target.selectedIndex]?.text;
            
            this.emit('BRANCH_SELECTED', {
                branchId: branchId ? parseInt(branchId) : null,
                branchName: branchName
            });
        };
    }
    
    async loadShiftsForBranch(branchId) {
        try {
            this.setState({ isLoadingShifts: true, error: null }, 'BranchSelector:loadShifts');
            
            const shiftService = this.getService('branchConfig');
            if (shiftService) {
                const response = await shiftService.getBranchShifts(branchId);
                
                this.setState({
                    shifts: response.data || [],
                    isLoadingShifts: false
                }, 'BranchSelector:shiftsLoaded');
                
                this.emit('SHIFTS_LOADED', {
                    branchId,
                    shifts: response.data || []
                });
            } else {
                throw new Error('Branch configuration service not available');
            }
            
        } catch (error) {
            this.setState({
                error: `Failed to load shifts: ${error.message}`,
                isLoadingShifts: false
            }, 'BranchSelector:loadError');
            
            this.emit('SHIFT_LOAD_ERROR', { error: error.message });
        }
    }
    
    getService(name) {
        return this.store.getState().services?.[name];
    }
    
    handleBranchSelected(data) {
        this.setState({
            currentBranchId: data.branchId,
            currentBranchName: data.branchName,
            assignments: {}
        }, 'BranchSelector:branchSelected');
    }
    
    handleShiftsLoaded(data) {
        console.log('📋 Shifts loaded for branch:', data.branchId, data.shifts.length);
    }
    
    handleAssignmentsLoaded(data) {
        console.log('📅 Assignments loaded:', Object.keys(data.assignments || {}).length);
    }
    
    handleShiftLoadError(data) {
        console.error('Shift loading error:', data.error);
    }
}

class DynamicShiftTemplateManager {
    constructor() {
        this.service = new ModernKalenderArchitecture.ShiftTemplateService();
    }
    
    /**
     * No more hardcoded shift types! Everything is dynamic.
     */
    async createShiftTemplate(templateData) {
        return await this.service.create(templateData);
    }
    
    async getAllTemplates() {
        return await this.service.getAll();
    }
    
    async enableShiftForBranch(branchId, shiftId, priorityOrder = 1) {
        const branchService = new ModernKalenderArchitecture.BranchConfigService();
        return await branchService.enableShiftForBranch(branchId, shiftId, priorityOrder);
    }
}

/**
 * Calendar View Component - Dynamic and Extensible
 */
class CalendarView extends ModernKalenderArchitecture.Component {
    constructor(store, eventBus) {
        super(store, eventBus, {
            container: document.getElementById('calendar-view')
        });
    }
    
    setupEventListeners() {
        this.on('VIEW_SWITCH', this.handleViewSwitch.bind(this));
        this.on('DATE_NAVIGATE', this.handleDateNavigate.bind(this));
        this.on('BRANCH_SELECTED', this.handleBranchSelected.bind(this));
        this.on('SHIFTS_LOADED', this.handleDataUpdate.bind(this));
        this.on('ASSIGNMENTS_LOADED', this.handleDataUpdate.bind(this));
        
        this.setupViewButtons();
        this.setupNavigationButtons();
    }
    
    handleStateChange(newState) {
        const { currentView, currentDate, currentMonth, currentYear, shifts, assignments } = newState;
        
        if (currentView !== this.currentView || this.needsReRender(newState)) {
            this.currentView = currentView;
            this.render();
        }
    }
    
    needsReRender(newState) {
        const { currentDate, currentMonth, currentYear, shifts, assignments } = newState;
        const oldState = this.getState();
        
        return currentDate?.getTime() !== oldState.currentDate?.getTime() ||
               currentMonth !== oldState.currentMonth ||
               currentYear !== oldState.currentYear ||
               JSON.stringify(shifts) !== JSON.stringify(oldState.shifts) ||
               JSON.stringify(assignments) !== JSON.stringify(oldState.assignments);
    }
    
    setupViewButtons() {
        ['view-day', 'view-week', 'view-month', 'view-year'].forEach(buttonId => {
            const button = document.getElementById(buttonId);
            if (button) {
                button.onclick = () => {
                    const view = buttonId.replace('view-', '');
                    this.emit('VIEW_SWITCH', { view });
                };
            }
        });
    }
    
    setupNavigationButtons() {
        const prevButton = document.getElementById('prev-nav');
        const nextButton = document.getElementById('next-nav');
        
        if (prevButton) {
            prevButton.onclick = () => this.emit('DATE_NAVIGATE', { direction: 'prev' });
        }
        
        if (nextButton) {
            nextButton.onclick = () => this.emit('DATE_NAVIGATE', { direction: 'next' });
        }
    }
    
    async render() {
        const { currentView, currentDate, currentMonth, currentYear, shifts, assignments } = this.getState();
        
        this.hideAllViews();
        
        switch (currentView) {
            case 'month':
                await this.renderMonthView(currentMonth, currentYear, shifts, assignments);
                break;
            case 'week':
                await this.renderWeekView(currentDate, shifts, assignments);
                break;
            case 'day':
                await this.renderDayView(currentDate, shifts, assignments);
                break;
            case 'year':
                await this.renderYearView(currentYear);
                break;
        }
        
        this.updateNavigationLabels();
    }
    
    async renderMonthView(month, year, shifts, assignments) {
        const calendarBody = document.getElementById('calendar-body');
        const monthView = document.getElementById('month-view');
        
        if (!calendarBody || !monthView) return;
        
        monthView.style.display = 'block';
        calendarBody.innerHTML = '';
        
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        let date = 1;
        
        for (let i = 0; i < 6; i++) {
            const row = document.createElement('tr');
            
            for (let j = 0; j < 7; j++) {
                const cell = document.createElement('td');
                
                if (i === 0 && j < firstDay) {
                    cell.classList.add('empty');
                } else if (date > daysInMonth) {
                    cell.classList.add('empty');
                } else {
                    const dateDiv = document.createElement('div');
                    dateDiv.className = 'date-number';
                    dateDiv.textContent = date;
                    cell.appendChild(dateDiv);
                    
                    // Highlight today
                    const today = new Date();
                    if (date === today.getDate() && 
                        month === today.getMonth() && 
                        year === today.getFullYear()) {
                        cell.classList.add('today');
                    }
                    
                    // Dynamic shift summary (no hardcoding!)
                    const dateStr = this.formatDate(new Date(year, month, date));
                    const shiftsForDate = this.getShiftsForDate(assignments, dateStr);
                    
                    if (shiftsForDate.length > 0) {
                        const shiftsDiv = document.createElement('div');
                        shiftsDiv.className = 'shifts-summary';
                        shiftsDiv.textContent = `${shiftsForDate.length} shift(s)`;
                        shiftsDiv.style.cssText = `
                            font-size: 11px; 
                            color: #2196F3; 
                            margin-top: 5px;
                            cursor: pointer;
                        `;
                        
                        shiftsDiv.onclick = () => {
                            this.emit('DATE_NAVIGATE', { 
                                direction: 'goto',
                                date: new Date(year, month, date)
                            });
                            this.emit('VIEW_SWITCH', { view: 'day' });
                        };
                        
                        cell.appendChild(shiftsDiv);
                    }
                    
                    date++;
                }
                row.appendChild(cell);
            }
            
            calendarBody.appendChild(row);
            
            if (date > daysInMonth) break;
        }
    }
    
    async renderDayView(date, shifts, assignments) {
        const dayView = document.getElementById('day-view');
        const dayContent = document.getElementById('day-content');
        
        if (!dayView || !dayContent) return;
        
        dayView.style.display = 'block';
        dayContent.innerHTML = '';
        
        const dateStr = this.formatDate(date);
        const dayShifts = this.getShiftsForDate(assignments, dateStr);
        
        const { currentBranchId } = this.getState();
        if (!currentBranchId) {
            const instruction = document.createElement('div');
            instruction.style.cssText = 'padding: 20px; text-align: center; color: #ff9800; background-color: #fff3e0; border-radius: 8px; margin: 20px;';
            instruction.innerHTML = '<strong>ℹ️ Pilih cabang terlebih dahulu untuk melihat dan assign shift!</strong>';
            dayContent.appendChild(instruction);
            return;
        }
        
        // Dynamic shift cards based on actual data
        if (dayShifts.length > 0) {
            const shiftsGrouped = this.groupShiftsByTime(dayShifts);
            
            Object.values(shiftsGrouped).forEach(group => {
                const shiftCard = this.createDynamicShiftCard(group, date);
                dayContent.appendChild(shiftCard);
            });
        } else {
            const noShiftInfo = document.createElement('div');
            noShiftInfo.style.cssText = 'padding: 20px; text-align: center; color: #666; background-color: #f5f5f5; border-radius: 8px; margin: 20px;';
            noShiftInfo.innerHTML = '<strong>📅 Belum ada shift yang di-assign untuk hari ini</strong><br><small>Klik untuk assign shift baru</small>';
            dayContent.appendChild(noShiftInfo);
        }
    }
    
    // Helper methods - all dynamic, no hardcoding!
    formatDate(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }
    
    getShiftsForDate(assignments, dateStr) {
        if (!assignments) return [];
        
        return Object.values(assignments).filter(assignment => 
            assignment.assignment_date === dateStr || assignment.shift_date === dateStr
        );
    }
    
    groupShiftsByTime(shifts) {
        const grouped = {};
        
        shifts.forEach(assignment => {
            const key = `${assignment.shift_template_id}-${assignment.start_time}-${assignment.end_time}`;
            
            if (!grouped[key]) {
                grouped[key] = {
                    templateId: assignment.shift_template_id,
                    shiftName: assignment.shift_name || assignment.nama_shift || 'Unknown',
                    startTime: assignment.start_time,
                    endTime: assignment.end_time,
                    color: assignment.color_hex || '#2196F3',
                    icon: assignment.icon_emoji || '📅',
                    employees: []
                };
            }
            
            grouped[key].employees.push({
                name: assignment.nama_lengkap || assignment.employee_name || 'Unknown',
                id: assignment.user_id
            });
        });
        
        return grouped;
    }
    
    createDynamicShiftCard(group, date) {
        const shiftCard = document.createElement('div');
        shiftCard.className = 'shift-card';
        shiftCard.style.cssText = `
            background-color: ${group.color}20; 
            border: 1px solid ${group.color}; 
            border-radius: 8px; 
            padding: 15px; 
            margin: 10px 0;
            cursor: pointer;
        `;
        
        shiftCard.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <div>
                    <h3 style="margin: 0; color: ${group.color};">${group.icon} ${group.shiftName}</h3>
                    <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">
                        ${this.formatTime(group.startTime)} - ${this.formatTime(group.endTime)}
                    </p>
                </div>
                <span style="background-color: ${group.color}; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px;">
                    ${group.employees.length} orang
                </span>
            </div>
            <div>
                <strong>👥 Pegawai:</strong>
                <ul style="margin: 5px 0; padding-left: 20px;">
                    ${group.employees.map(emp => `<li>${emp.name}</li>`).join('')}
                </ul>
            </div>
        `;
        
        shiftCard.onclick = () => {
            this.emit('SHIFT_EDIT', { group, date });
        };
        
        return shiftCard;
    }
    
    formatTime(timeString) {
        if (!timeString) return '--:--';
        return timeString.substring(0, 5);
    }
    
    hideAllViews() {
        ['month-view', 'week-view', 'day-view', 'year-view'].forEach(viewId => {
            const view = document.getElementById(viewId);
            if (view) view.style.display = 'none';
        });
    }
    
    updateNavigationLabels() {
        const { currentView, currentDate, currentMonth, currentYear } = this.getState();
        const currentNav = document.getElementById('current-nav');
        const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                           'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        
        if (!currentNav) return;
        
        switch (currentView) {
            case 'month':
                currentNav.textContent = `${monthNames[currentMonth]} ${currentYear}`;
                break;
            case 'day':
                currentNav.textContent = `${currentDate.getDate()} ${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
                break;
            case 'year':
                currentNav.textContent = `${currentYear}`;
                break;
        }
    }
    
    // Event handlers
    handleViewSwitch(data) {
        this.setState({ currentView: data.view }, 'CalendarView:viewSwitch');
    }
    
    handleDateNavigate(data) {
        const { currentView, currentDate, currentMonth, currentYear } = this.getState();
        
        if (data.direction === 'prev' || data.direction === 'next') {
            const direction = data.direction === 'prev' ? -1 : 1;
            
            if (currentView === 'month') {
                let newMonth = currentMonth + direction;
                let newYear = currentYear;
                
                if (newMonth < 0) {
                    newMonth = 11;
                    newYear--;
                } else if (newMonth > 11) {
                    newMonth = 0;
                    newYear++;
                }
                
                this.setState({ 
                    currentMonth: newMonth, 
                    currentYear: newYear 
                }, 'CalendarView:navigateMonth');
                
            } else if (currentView === 'day') {
                const newDate = new Date(currentDate);
                newDate.setDate(currentDate.getDate() + direction);
                this.setState({ currentDate: newDate }, 'CalendarView:navigateDay');
                
            } else if (currentView === 'year') {
                this.setState({ 
                    currentYear: currentYear + direction 
                }, 'CalendarView:navigateYear');
            }
            
        } else if (data.direction === 'goto' && data.date) {
            this.setState({ 
                currentDate: data.date,
                currentMonth: data.date.getMonth(),
                currentYear: data.date.getFullYear()
            }, 'CalendarView:gotoDate');
        }
    }
    
    handleBranchSelected(data) {
        this.setState({ assignments: {} }, 'CalendarView:branchChanged');
    }
    
    handleDataUpdate(data) {
        this.render();
    }
}

/**
 * Application Bootstrap - Shows the power of clean architecture
 */
class NewKalenderApp extends ModernKalenderArchitecture.KalenderApp {
    constructor() {
        super();
    }
    
    async init() {
        console.log('🚀 Initializing New Kalender App with Modern Architecture...');
        
        try {
            await this.loadInitialData();
            this.initializeComponents();
            
            console.log('✅ New Kalender App initialized successfully');
            console.log('🎯 Benefits:');
            console.log('  - No hardcoded shift types');
            console.log('  - Dynamic configuration via API');
            console.log('  - Clean separation of concerns');
            console.log('  - Scalable and maintainable');
            
        } catch (error) {
            console.error('❌ Failed to initialize New Kalender App:', error);
        }
    }
    
    async loadInitialData() {
        this.setState({ isLoading: true });
        
        try {
            // Simulate loading branches from API
            const branchesResponse = await this.loadBranches();
            
            this.setState({
                branches: branchesResponse.data || [],
                isLoading: false,
                services: {
                    shiftTemplates: new ModernKalenderArchitecture.ShiftTemplateService(),
                    branchConfig: new ModernKalenderArchitecture.BranchConfigService()
                }
            });
            
        } catch (error) {
            this.setState({
                error: 'Failed to load initial data',
                isLoading: false
            });
        }
    }
    
    initializeComponents() {
        // Initialize components with dependency injection
        const branchSelector = new BranchSelector(this.store, this.eventBus);
        const calendarView = new CalendarView(this.store, this.eventBus);
        
        this.components.set('branchSelector', branchSelector);
        this.components.set('calendarView', calendarView);
        
        // Setup event flow
        this.eventBus.on('SHIFTS_LOADED', (data) => {
            console.log('📋 Shifts loaded for branch:', data.branchId);
        });
        
        this.eventBus.on('SHIFT_LOAD_ERROR', (data) => {
            console.error('❌ Shift loading error:', data.error);
        });
    }
    
    async loadBranches() {
        // Mock API call - in real implementation, this would call the actual API
        return {
            status: 'success',
            data: [
                { id: 1, nama_cabang: 'Citraland Gowa' },
                { id: 2, nama_cabang: 'Adhyaksa' },
                { id: 3, nama_cabang: 'BTP' }
            ]
        };
    }
}

// Export for global use
window.ModernCalendarComponents = {
    BranchSelector,
    CalendarView,
    DynamicShiftTemplateManager,
    NewKalenderApp
};

// Auto-initialize the new app when DOM is ready
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', async () => {
        console.log('🏗️ Modern Calendar Architecture Loaded');
        console.log('📚 Key Features:');
        console.log('  - Store Pattern for State Management');
        console.log('  - Event Bus for Loose Coupling');
        console.log('  - Component-based Architecture');
        console.log('  - Dynamic Shift Configuration');
        console.log('  - Clean SOLID Principles');
        
        // Initialize the new app
        const newApp = new NewKalenderApp();
        await newApp.init();
        
        // Make it available globally for debugging
        window.NewKalenderApp = newApp;
    });
}