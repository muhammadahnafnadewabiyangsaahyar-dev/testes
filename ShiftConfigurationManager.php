<?php
/**
 * Enhanced Shift Configuration Manager
 * 
 * Handles dynamic shift configuration without hardcoding
 * Part of the new modular architecture for scalable calendar system
 * 
 * Features:
 * - Dynamic shift templates
 * - Per-branch configuration
 * - Caching for performance
 * - Validation and error handling
 * - No code changes needed for new shifts
 */

class ShiftConfigurationManager {
    private $pdo;
    private $cacheManager;
    private $logger;
    
    // Cache keys for better performance
    const CACHE_KEY_BRANCH_SHIFTS = 'branch_shifts_%d';
    const CACHE_KEY_ALL_TEMPLATES = 'all_shift_templates';
    const CACHE_DURATION = 3600; // 1 hour
    
    public function __construct(PDO $pdo, CacheManager $cacheManager = null, Logger $logger = null) {
        $this->pdo = $pdo;
        $this->cacheManager = $cacheManager ?: new SimpleCacheManager();
        $this->logger = $logger ?: new NullLogger();
    }
    
    /**
     * Get available shifts for branch (dynamic, no hardcoding)
     * 
     * @param int $branchId
     * @return array
     * @throws Exception
     */
    public function getBranchShifts($branchId) {
        if (!$branchId || !is_numeric($branchId)) {
            throw new InvalidArgumentException('Invalid branch ID');
        }
        
        $cacheKey = sprintf(self::CACHE_KEY_BRANCH_SHIFTS, $branchId);
        
        try {
            // Try to get from cache first
            $cached = $this->cacheManager->get($cacheKey);
            if ($cached !== null) {
                $this->logger->info('Loaded branch shifts from cache', ['branch_id' => $branchId]);
                return $cached;
            }
            
            // Get from database
            $sql = "SELECT 
                        st.id,
                        st.name,
                        st.display_name,
                        st.start_time,
                        st.end_time,
                        st.duration_hours,
                        st.color_hex,
                        st.icon_emoji,
                        st.description,
                        bsc.priority_order,
                        bsc.is_available
                    FROM shift_templates st
                    JOIN branch_shift_config bsc ON st.id = bsc.shift_template_id
                    WHERE bsc.branch_id = ? 
                        AND st.is_active = 1 
                        AND bsc.is_available = 1
                    ORDER BY bsc.priority_order ASC, st.display_name ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$branchId]);
            
            $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Cache the result
            $this->cacheManager->set($cacheKey, $shifts, self::CACHE_DURATION);
            
            $this->logger->info('Loaded branch shifts from database', [
                'branch_id' => $branchId, 
                'count' => count($shifts)
            ]);
            
            return $shifts;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get branch shifts', [
                'branch_id' => $branchId, 
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to load shift configuration: ' . $e->getMessage());
        }
    }
    
    /**
     * Get all shift templates (master data)
     * 
     * @return array
     * @throws Exception
     */
    public function getAllShiftTemplates() {
        try {
            $cached = $this->cacheManager->get(self::CACHE_KEY_ALL_TEMPLATES);
            if ($cached !== null) {
                return $cached;
            }
            
            $sql = "SELECT * FROM shift_templates 
                    WHERE is_active = 1 
                    ORDER BY sort_order ASC, display_name ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->cacheManager->set(self::CACHE_KEY_ALL_TEMPLATES, $templates, self::CACHE_DURATION);
            
            return $templates;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get shift templates', ['error' => $e->getMessage()]);
            throw new Exception('Failed to load shift templates: ' . $e->getMessage());
        }
    }
    
    /**
     * Create new shift template (no code changes needed!)
     * 
     * @param array $data
     * @return int new template ID
     * @throws Exception
     */
    public function createShiftTemplate($data) {
        $validator = new ShiftTemplateValidator();
        $data = $validator->validateCreate($data);
        
        try {
            $this->pdo->beginTransaction();
            
            // Insert new template
            $sql = "INSERT INTO shift_templates 
                    (name, display_name, start_time, end_time, color_hex, icon_emoji, description, sort_order) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['display_name'],
                $data['start_time'],
                $data['end_time'],
                $data['color_hex'],
                $data['icon_emoji'] ?? null,
                $data['description'] ?? null,
                $data['sort_order'] ?? 999
            ]);
            
            $templateId = (int) $this->pdo->lastInsertId();
            
            // Clear cache
            $this->clearCache();
            
            $this->pdo->commit();
            
            $this->logger->info('Created new shift template', [
                'template_id' => $templateId,
                'name' => $data['name']
            ]);
            
            return $templateId;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to create shift template', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw new Exception('Failed to create shift template: ' . $e->getMessage());
        }
    }
    
    /**
     * Enable shift for branch (no code changes needed!)
     * 
     * @param int $branchId
     * @param int $shiftTemplateId
     * @param int $priorityOrder
     * @return bool
     * @throws Exception
     */
    public function enableShiftForBranch($branchId, $shiftTemplateId, $priorityOrder = 1) {
        if (!$branchId || !$shiftTemplateId) {
            throw new InvalidArgumentException('Branch ID and Shift Template ID are required');
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Check if branch and template exist
            $this->validateBranchAndTemplate($branchId, $shiftTemplateId);
            
            // Insert or update configuration
            $sql = "INSERT INTO branch_shift_config 
                    (branch_id, shift_template_id, priority_order, is_available) 
                    VALUES (?, ?, ?, 1) 
                    ON DUPLICATE KEY UPDATE 
                    is_available = 1, 
                    priority_order = VALUES(priority_order),
                    updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$branchId, $shiftTemplateId, $priorityOrder]);
            
            // Clear relevant cache
            $this->cacheManager->delete(sprintf(self::CACHE_KEY_BRANCH_SHIFTS, $branchId));
            
            $this->pdo->commit();
            
            $this->logger->info('Enabled shift for branch', [
                'branch_id' => $branchId,
                'shift_template_id' => $shiftTemplateId,
                'priority_order' => $priorityOrder
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to enable shift for branch', [
                'branch_id' => $branchId,
                'shift_template_id' => $shiftTemplateId,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to enable shift for branch: ' . $e->getMessage());
        }
    }
    
    /**
     * Disable shift for branch
     * 
     * @param int $branchId
     * @param int $shiftTemplateId
     * @return bool
     * @throws Exception
     */
    public function disableShiftForBranch($branchId, $shiftTemplateId) {
        try {
            $sql = "UPDATE branch_shift_config 
                    SET is_available = 0, updated_at = CURRENT_TIMESTAMP 
                    WHERE branch_id = ? AND shift_template_id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$branchId, $shiftTemplateId]);
            
            // Clear cache
            $this->cacheManager->delete(sprintf(self::CACHE_KEY_BRANCH_SHIFTS, $branchId));
            
            $this->logger->info('Disabled shift for branch', [
                'branch_id' => $branchId,
                'shift_template_id' => $shiftTemplateId
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to disable shift for branch', [
                'branch_id' => $branchId,
                'shift_template_id' => $shiftTemplateId,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to disable shift for branch: ' . $e->getMessage());
        }
    }
    
    /**
     * Get shift statistics for reporting
     * 
     * @param int $branchId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getShiftStatistics($branchId, $startDate, $endDate) {
        try {
            $sql = "SELECT 
                        st.display_name,
                        st.name as shift_type,
                        COUNT(sa.id) as total_assignments,
                        COUNT(CASE WHEN sa.status = 'confirmed' THEN 1 END) as confirmed_count,
                        COUNT(CASE WHEN sa.status = 'pending' THEN 1 END) as pending_count,
                        COUNT(CASE WHEN sa.status = 'declined' THEN 1 END) as declined_count,
                        SUM(CASE WHEN sa.status = 'confirmed' THEN sa.total_hours ELSE 0 END) as total_hours
                    FROM shift_templates st
                    LEFT JOIN shift_assignments_v2 sa ON st.id = sa.shift_template_id 
                        AND sa.assignment_date BETWEEN ? AND ?
                        AND sa.branch_id = ?
                    WHERE st.is_active = 1
                    GROUP BY st.id, st.display_name, st.name
                    ORDER BY st.sort_order ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$startDate, $endDate, $branchId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get shift statistics', [
                'branch_id' => $branchId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to get shift statistics: ' . $e->getMessage());
        }
    }
    
    /**
     * Validate that branch and template exist
     * 
     * @param int $branchId
     * @param int $templateId
     * @throws Exception
     */
    private function validateBranchAndTemplate($branchId, $templateId) {
        // Validate branch
        $stmt = $this->pdo->prepare("SELECT id, nama_cabang FROM cabang_outlet WHERE id = ?");
        $stmt->execute([$branchId]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$branch) {
            throw new Exception("Branch with ID {$branchId} not found");
        }
        
        // Validate template
        $stmt = $this->pdo->prepare("SELECT id, name FROM shift_templates WHERE id = ? AND is_active = 1");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$template) {
            throw new Exception("Shift template with ID {$templateId} not found or inactive");
        }
    }
    
    /**
     * Clear all relevant cache
     */
    private function clearCache() {
        $this->cacheManager->delete(self::CACHE_KEY_ALL_TEMPLATES);
        // Note: Branch-specific caches will be cleared when needed
    }
}

/**
 * Shift Template Validator
 * Validates shift template data according to business rules
 */
class ShiftTemplateValidator {
    public function validateCreate($data) {
        $required = ['name', 'display_name', 'start_time', 'end_time', 'color_hex'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException("Field '{$field}' is required");
            }
        }
        
        // Validate time format (HH:MM:SS)
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $data['start_time'])) {
            throw new InvalidArgumentException('Invalid start time format');
        }
        
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $data['end_time'])) {
            throw new InvalidArgumentException('Invalid end time format');
        }
        
        // Validate color hex
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $data['color_hex'])) {
            throw new InvalidArgumentException('Invalid color hex format');
        }
        
        // Clean and prepare data
        return [
            'name' => trim($data['name']),
            'display_name' => trim($data['display_name']),
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'color_hex' => strtoupper($data['color_hex']),
            'icon_emoji' => !empty($data['icon_emoji']) ? trim($data['icon_emoji']) : null,
            'description' => !empty($data['description']) ? trim($data['description']) : null,
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 999
        ];
    }
}

/**
 * Simple Cache Manager
 * Basic caching implementation - can be replaced with Redis/Memcached
 */
class SimpleCacheManager {
    private $cache = [];
    
    public function get($key) {
        return $this->cache[$key] ?? null;
    }
    
    public function set($key, $value, $ttl = 3600) {
        $this->cache[$key] = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
    }
    
    public function delete($key) {
        unset($this->cache[$key]);
    }
    
    public function clear() {
        $this->cache = [];
    }
}

/**
 * Null Logger
 * Placeholder logger for development/testing
 */
class NullLogger {
    public function info($message, $context = []) {}
    public function error($message, $context = []) {}
    public function warning($message, $context = []) {}
}

/**
 * Exception Classes
 */
class ConfigurationException extends Exception {}
class ValidationException extends Exception {}
?>