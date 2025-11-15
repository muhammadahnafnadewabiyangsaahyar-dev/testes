/**
 * Modern Store Pattern - Centralized State Management
 * Replaces scattered state variables with centralized, reactive store
 * Part of the new modular architecture
 */

class Store {
    constructor(initialState = {}) {
        this.state = { ...initialState };
        this.listeners = new Set();
        this.middlewares = [];
    }
    
    /**
     * Get current state
     */
    getState() {
        return this.state;
    }
    
    /**
     * Set state with optional middleware support
     */
    setState(updater, source = 'unknown') {
        const oldState = this.state;
        const newState = typeof updater === 'function' ? updater(oldState) : { ...oldState, ...updater };
        
        // Apply middlewares
        let processedState = newState;
        for (const middleware of this.middlewares) {
            processedState = middleware(processedState, oldState, source) || processedState;
        }
        
        this.state = processedState;
        this.notifyListeners();
        
        // Log state changes in development
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.log(`📊 State updated from ${source}:`, {
                oldState: this.getChangedProps(oldState, processedState),
                newState: this.getChangedProps(processedState, oldState)
            });
        }
    }
    
    /**
     * Subscribe to state changes
     */
    subscribe(listener) {
        this.listeners.add(listener);
        
        // Return unsubscribe function
        return () => {
            this.listeners.delete(listener);
        };
    }
    
    /**
     * Add middleware
     */
    use(middleware) {
        this.middlewares.push(middleware);
    }
    
    /**
     * Get state slice (selector pattern)
     */
    select(selector) {
        return selector(this.state);
    }
    
    /**
     * Check if state changed
     */
    hasChanged(newState) {
        return JSON.stringify(this.state) !== JSON.stringify(newState);
    }
    
    /**
     * Internal method to notify listeners
     */
    notifyListeners() {
        const currentState = this.state;
        this.listeners.forEach(listener => {
            try {
                listener(currentState);
            } catch (error) {
                console.error('Error in store listener:', error);
            }
        });
    }
    
    /**
     * Helper to get changed properties
     */
    getChangedProps(oldState, newState) {
        const changed = {};
        const allKeys = new Set([...Object.keys(oldState), ...Object.keys(newState)]);
        
        for (const key of allKeys) {
            if (oldState[key] !== newState[key]) {
                changed[key] = {
                    old: oldState[key],
                    new: newState[key]
                };
            }
        }
        
        return changed;
    }
}

/**
 * Event Bus - Event-driven communication between components
 * Provides loose coupling between modules
 */
class EventBus {
    constructor() {
        this.events = new Map();
        this.middleware = [];
    }
    
    /**
     * Subscribe to an event
     */
    on(eventName, callback, context = null) {
        if (!this.events.has(eventName)) {
            this.events.set(eventName, new Set());
        }
        
        const callbackWrapper = context ? callback.bind(context) : callback;
        this.events.get(eventName).add(callbackWrapper);
        
        // Return unsubscribe function
        return () => this.off(eventName, callbackWrapper);
    }
    
    /**
     * Unsubscribe from an event
     */
    off(eventName, callback) {
        if (this.events.has(eventName)) {
            this.events.get(eventName).delete(callback);
        }
    }
    
    /**
     * Emit an event
     */
    emit(eventName, data = null) {
        // Apply middleware
        let processedData = data;
        for (const mw of this.middleware) {
            processedData = mw(eventName, processedData) || processedData;
        }
        
        if (!this.events.has(eventName)) {
            return false;
        }
        
        const callbacks = this.events.get(eventName);
        callbacks.forEach(callback => {
            try {
                callback(processedData);
            } catch (error) {
                console.error(`Error in event callback for '${eventName}':`, error);
            }
        });
        
        return callbacks.size;
    }
    
    /**
     * Add middleware for event processing
     */
    use(middleware) {
        this.middleware.push(middleware);
    }
    
    /**
     * Clear all listeners for an event
     */
    clear(eventName) {
        if (eventName) {
            this.events.delete(eventName);
        } else {
            this.events.clear();
        }
    }
    
    /**
     * Get listeners count for an event
     */
    listenerCount(eventName) {
        return this.events.get(eventName)?.size || 0;
    }
}

/**
 * Base Component Class - Common interface for all UI components
 */
class Component {
    constructor(store, eventBus, options = {}) {
        this.store = store;
        this.eventBus = eventBus;
        this.options = options;
        this.container = options.container || null;
        this.listeners = [];
        this.isInitialized = false;
        this.isDestroyed = false;
        
        // Bind methods to preserve context
        this.handleStateChange = this.handleStateChange.bind(this);
        this.handleEvent = this.handleEvent.bind(this);
        
        // Initialize if container exists
        if (this.container) {
            this.init();
        }
    }
    
    /**
     * Initialize component
     */
    async init() {
        if (this.isInitialized) return;
        
        try {
            // Subscribe to state changes
            this.unsubscribe = this.store.subscribe(this.handleStateChange);
            this.listeners.push(this.unsubscribe);
            
            // Subscribe to relevant events
            this.setupEventListeners();
            
            // Initial render
            await this.render();
            
            this.isInitialized = true;
            
            if (window.location.hostname === 'localhost') {
                console.log(`✅ Component ${this.constructor.name} initialized`);
            }
            
        } catch (error) {
            console.error(`Failed to initialize component ${this.constructor.name}:`, error);
            throw error;
        }
    }
    
    /**
     * Setup event listeners - override in subclasses
     */
    setupEventListeners() {
        // To be implemented by subclasses
    }
    
    /**
     * Handle state changes - override in subclasses
     */
    handleStateChange(newState) {
        // To be implemented by subclasses
    }
    
    /**
     * Handle custom events - override in subclasses
     */
    handleEvent(eventName, data) {
        // To be implemented by subclasses
    }
    
    /**
     * Render component - override in subclasses
     */
    async render() {
        // To be implemented by subclasses
    }
    
    /**
     * Subscribe to custom events
     */
    on(eventName, callback) {
        const unsubscribe = this.eventBus.on(eventName, callback, this);
        this.listeners.push(unsubscribe);
        return unsubscribe;
    }
    
    /**
     * Emit custom events
     */
    emit(eventName, data = null) {
        return this.eventBus.emit(eventName, data);
    }
    
    /**
     * Get component state from store
     */
    getState(selector = null) {
        return selector ? this.store.select(selector) : this.store.getState();
    }
    
    /**
     * Set component state
     */
    setState(updater, source = 'component') {
        this.store.setState(updater, `${this.constructor.name}:${source}`);
    }
    
    /**
     * Get DOM element
     */
    $(selector) {
        return this.container?.querySelector(selector);
    }
    
    /**
     * Get all DOM elements
     */
    $$(selector) {
        return this.container?.querySelectorAll(selector) || [];
    }
    
    /**
     * Destroy component
     */
    destroy() {
        if (this.isDestroyed) return;
        
        // Unsubscribe from all listeners
        this.listeners.forEach(unsubscribe => {
            if (typeof unsubscribe === 'function') {
                unsubscribe();
            }
        });
        this.listeners = [];
        
        // Clear event listeners
        this.eventBus.clear();
        
        // Cleanup DOM
        if (this.container && this.container.parentNode) {
            this.container.parentNode.removeChild(this.container);
        }
        
        this.isDestroyed = true;
        
        if (window.location.hostname === 'localhost') {
            console.log(`🗑️ Component ${this.constructor.name} destroyed`);
        }
    }
    
    /**
     * Check if component is destroyed
     */
    isDestroyed() {
        return this.isDestroyed;
    }
}

/**
 * Service Layer Base Class
 */
class BaseService {
    constructor(apiBaseUrl = '/api/v2') {
        this.apiBaseUrl = apiBaseUrl;
        this.defaultHeaders = {
            'Content-Type': 'application/json',
        };
    }
    
    /**
     * Make HTTP request
     */
    async request(endpoint, options = {}) {
        const url = `${this.apiBaseUrl}${endpoint}`;
        const config = {
            headers: { ...this.defaultHeaders, ...options.headers },
            ...options,
        };
        
        try {
            const response = await fetch(url, config);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (data.status === 'error') {
                throw new Error(data.message || 'API Error');
            }
            
            return data;
            
        } catch (error) {
            console.error('API Request failed:', error);
            throw error;
        }
    }
    
    /**
     * GET request
     */
    async get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return this.request(url, { method: 'GET' });
    }
    
    /**
     * POST request
     */
    async post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }
    
    /**
     * PUT request
     */
    async put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    }
    
    /**
     * DELETE request
     */
    async delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }
}

/**
 * Shift Template Service - API communication layer
 * No hardcoded shift types - all dynamic!
 */
class ShiftTemplateService extends BaseService {
    constructor() {
        super('/api/v2/shift-templates');
    }
    
    /**
     * Get all shift templates
     */
    async getAll() {
        return this.get('');
    }
    
    /**
     * Get specific shift template
     */
    async getById(id) {
        return this.get(`/${id}`);
    }
    
    /**
     * Create new shift template (no hardcoding needed!)
     */
    async create(templateData) {
        return this.post('', templateData);
    }
    
    /**
     * Update shift template
     */
    async update(id, templateData) {
        return this.put(`/${id}`, templateData);
    }
    
    /**
     * Delete shift template (soft delete)
     */
    async delete(id) {
        return this.delete(`/${id}`);
    }
}

/**
 * Branch Configuration Service
 */
class BranchConfigService extends BaseService {
    constructor() {
        super('/api/v2/branches');
    }
    
    /**
     * Get available shifts for branch
     */
    async getBranchShifts(branchId) {
        return this.get(`/${branchId}/shifts`);
    }
    
    /**
     * Enable shift for branch (no hardcoding needed!)
     */
    async enableShiftForBranch(branchId, shiftId, priorityOrder = 1) {
        return this.post(`/${branchId}/shifts/${shiftId}/enable`, { priority_order: priorityOrder });
    }
    
    /**
     * Disable shift for branch
     */
    async disableShiftForBranch(branchId, shiftId) {
        // Implementation would be similar to enable
        return this.put(`/${branchId}/shifts/${shiftId}/disable`);
    }
}

/**
 * Application Bootstrap
 * Initializes the entire application with proper dependency injection
 */
class KalenderApp {
    constructor(options = {}) {
        this.options = options;
        this.store = new Store(this.getInitialState());
        this.eventBus = new EventBus();
        this.services = this.createServices();
        this.components = new Map();
        
        // Setup middleware
        this.setupMiddleware();
    }
    
    /**
     * Get initial application state
     */
    getInitialState() {
        return {
            // UI State
            currentView: 'month',
            currentDate: new Date(),
            currentMonth: new Date().getMonth(),
            currentYear: new Date().getFullYear(),
            
            // Data State
            currentBranchId: null,
            currentBranchName: null,
            branches: [],
            shifts: [],
            assignments: {},
            
            // Loading States
            isLoading: false,
            isLoadingShifts: false,
            isLoadingAssignments: false,
            
            // Error States
            error: null,
            errors: {},
            
            // Configuration
            config: {
                enableShiftConfirmation: true,
                defaultShiftStatus: 'pending',
                cacheDuration: 3600
            }
        };
    }
    
    /**
     * Create service instances
     */
    createServices() {
        return {
            shiftTemplates: new ShiftTemplateService(),
            branchConfig: new BranchConfigService(),
        };
    }
    
    /**
     * Setup middleware for logging and error handling
     */
    setupMiddleware() {
        // Logging middleware
        this.store.use((newState, oldState, source) => {
            if (window.location.hostname === 'localhost') {
                console.log(`🔄 State changed [${source}]:`, {
                    changes: this.getStateChanges(oldState, newState)
                });
            }
        });
        
        // Error handling middleware
        this.store.use((newState, oldState, source) => {
            // Clear previous errors when operations succeed
            if (oldState.error && !newState.error) {
                return newState;
            }
            return newState;
        });
    }
    
    /**
     * Initialize application
     */
    async init() {
        try {
            console.log('🚀 Initializing Kalender App...');
            
            // Load initial data
            await this.loadInitialData();
            
            // Initialize components
            this.initializeComponents();
            
            console.log('✅ Kalender App initialized successfully');
            
        } catch (error) {
            console.error('❌ Failed to initialize Kalender App:', error);
            throw error;
        }
    }
    
    /**
     * Load initial application data
     */
    async loadInitialData() {
        this.setState({ isLoading: true });
        
        try {
            // Load branches
            const branchesResponse = await this.loadBranches();
            
            this.setState({
                branches: branchesResponse.data || [],
                isLoading: false
            });
            
        } catch (error) {
            this.setState({
                error: 'Failed to load initial data',
                isLoading: false
            });
        }
    }
    
    /**
     * Load branches from API
     */
    async loadBranches() {
        // This would call the existing branches API
        // For now, return mock data
        return {
            status: 'success',
            data: [
                { id: 1, nama_cabang: 'Citraland Gowa' },
                { id: 2, nama_cabang: 'Adhyaksa' },
                { id: 3, nama_cabang: 'BTP' }
            ]
        };
    }
    
    /**
     * Initialize all components
     */
    initializeComponents() {
        // Components will be initialized as needed
        // This provides the foundation for lazy loading
    }
    
    /**
     * Get state changes helper
     */
    getStateChanges(oldState, newState) {
        const changes = {};
        const allKeys = new Set([...Object.keys(oldState), ...Object.keys(newState)]);
        
        for (const key of allKeys) {
            if (JSON.stringify(oldState[key]) !== JSON.stringify(newState[key])) {
                changes[key] = {
                    old: oldState[key],
                    new: newState[key]
                };
            }
        }
        
        return changes;
    }
    
    /**
     * Get current state
     */
    getState() {
        return this.store.getState();
    }
    
    /**
     * Update state
     */
    setState(updater, source = 'app') {
        this.store.setState(updater, source);
    }
    
    /**
     * Subscribe to state changes
     */
    subscribe(listener) {
        return this.store.subscribe(listener);
    }
    
    /**
     * Get service instance
     */
    getService(serviceName) {
        return this.services[serviceName];
    }
    
    /**
     * Emit event
     */
    emit(eventName, data) {
        return this.eventBus.emit(eventName, data);
    }
    
    /**
     * Subscribe to event
     */
    on(eventName, callback) {
        return this.eventBus.on(eventName, callback);
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        Store,
        EventBus,
        Component,
        BaseService,
        ShiftTemplateService,
        BranchConfigService,
        KalenderApp
    };
} else {
    window.ModernKalenderArchitecture = {
        Store,
        EventBus,
        Component,
        BaseService,
        ShiftTemplateService,
        BranchConfigService,
        KalenderApp
    };
}