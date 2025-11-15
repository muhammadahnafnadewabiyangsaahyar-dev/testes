<?php
/**
 * ADVANCED ROLE-BASED ACCESS CONTROL (RBAC) SYSTEM
 *
 * Features:
 * - Hierarchical role system (user < admin < superadmin)
 * - Permission-based access control
 * - Dynamic role assignment from positions
 * - Session-based authentication
 * - Audit logging for security
 * - API endpoint protection
 * - Database-driven permissions
 */

/**
 * Role Hierarchy Levels:
 * 0 - Guest (no access)
 * 1 - User (basic employee access)
 * 2 - Admin (management access)
 * 3 - Superadmin (full system access)
 */

/**
 * CORE RBAC FUNCTIONS
 */

/**
 * Get role by position from database (primary method)
 */
function getRoleByPosisiFromDB($pdo, $posisi) {
    if (empty($posisi)) {
        return 'user'; // default role for empty position
    }

    try {
        // Lookup role from posisi_jabatan table
        $stmt = $pdo->prepare("SELECT role_posisi FROM posisi_jabatan WHERE nama_posisi = ? LIMIT 1");
        $stmt->execute([trim($posisi)]);
        $result = $stmt->fetchColumn();

        if ($result) {
            return strtolower($result);
        } else {
            // Position not found, use fallback logic
            return getFallbackRole($posisi);
        }
    } catch (PDOException $e) {
        error_log("Error getting role from DB: " . $e->getMessage());
        return getFallbackRole($posisi);
    }
}

/**
 * Advanced role validation with caching
 */
function validateUserRole($pdo, $user_id, $required_role = null) {
    static $role_cache = [];

    if (isset($role_cache[$user_id])) {
        $user_role = $role_cache[$user_id];
    } else {
        try {
            $stmt = $pdo->prepare("SELECT role, posisi FROM register WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return ['valid' => false, 'role' => null, 'level' => 0, 'message' => 'User not found'];
            }

            // Sync role with position if needed
            $expected_role = getRoleByPosisiFromDB($pdo, $user['posisi']);

            if (strtolower($user['role']) !== $expected_role) {
                // Auto-correct role mismatch
                $stmt = $pdo->prepare("UPDATE register SET role = ? WHERE id = ?");
                $stmt->execute([$expected_role, $user_id]);

                logUserActivity($pdo, $user_id, 'role_auto_correct', "Role corrected from {$user['role']} to $expected_role");

                $user['role'] = $expected_role;
            }

            $user_role = strtolower($user['role']);
            $role_cache[$user_id] = $user_role;

        } catch (PDOException $e) {
            error_log("Error validating user role: " . $e->getMessage());
            return ['valid' => false, 'role' => null, 'level' => 0, 'message' => 'Database error'];
        }
    }

    $role_level = getRoleLevel($user_role);

    if ($required_role === null) {
        return ['valid' => true, 'role' => $user_role, 'level' => $role_level, 'message' => 'Role validated'];
    }

    $required_level = getRoleLevel($required_role);
    $has_access = $role_level >= $required_level;

    return [
        'valid' => $has_access,
        'role' => $user_role,
        'level' => $role_level,
        'required_level' => $required_level,
        'has_access' => $has_access,
        'message' => $has_access ? 'Access granted' : 'Insufficient permissions'
    ];
}

function getFallbackRole($posisi) {
    // Fallback jika database lookup gagal
    // Hanya digunakan sebagai backup
    $posisi_lower = strtolower(trim($posisi));
    $superadmin_positions = ['superadmin', 'owner'];
    $admin_positions = ['hr', 'finance', 'marketing', 'scm', 'akuntan'];

    if (in_array($posisi_lower, $superadmin_positions)) {
        return 'superadmin';
    } elseif (in_array($posisi_lower, $admin_positions)) {
        return 'admin';
    } else {
        return 'user';
    }
}

/**
 * PERMISSION CHECKING FUNCTIONS
 */

/**
 * Check if user has admin or superadmin role
 */
function isAdminOrSuperadmin($role) {
    return in_array(strtolower($role), ['admin', 'superadmin']);
}

/**
 * Check if user has superadmin role only
 */
function isSuperadmin($role) {
    return strtolower($role) === 'superadmin';
}

/**
 * Get role hierarchy level (for permission checks)
 */
function getRoleLevel($role) {
    $role_lower = strtolower($role);
    switch ($role_lower) {
        case 'superadmin':
            return 3;
        case 'admin':
            return 2;
        case 'user':
            return 1;
        default:
            return 0;
    }
}

/**
 * Advanced permission checking with context
 */
function checkPermission($pdo, $user_id, $permission, $resource = null, $context = []) {
    $role_validation = validateUserRole($pdo, $user_id);

    if (!$role_validation['valid']) {
        return [
            'granted' => false,
            'reason' => 'Invalid user or role',
            'user_level' => 0,
            'required_level' => getPermissionRequiredLevel($permission)
        ];
    }

    $user_level = $role_validation['level'];
    $required_level = getPermissionRequiredLevel($permission);

    // Check basic role hierarchy
    if ($user_level < $required_level) {
        return [
            'granted' => false,
            'reason' => 'Insufficient role level',
            'user_level' => $user_level,
            'required_level' => $required_level
        ];
    }

    // Check resource-specific permissions
    if ($resource) {
        $resource_check = checkResourcePermission($pdo, $user_id, $permission, $resource, $context);
        if (!$resource_check['granted']) {
            return $resource_check;
        }
    }

    return [
        'granted' => true,
        'reason' => 'Permission granted',
        'user_level' => $user_level,
        'required_level' => $required_level
    ];
}

/**
 * Get required permission level for specific actions
 */
function getPermissionRequiredLevel($permission) {
    $permission_levels = [
        // User permissions (level 1)
        'view_own_profile' => 1,
        'update_own_profile' => 1,
        'view_own_attendance' => 1,
        'submit_leave_request' => 1,
        'view_own_salary' => 1,
        'confirm_shift' => 1,

        // Admin permissions (level 2)
        'view_all_attendance' => 2,
        'manage_shifts' => 2,
        'approve_leave_requests' => 2,
        'view_employee_list' => 2,
        'generate_reports' => 2,
        'send_notifications' => 2,
        'manage_whitelist' => 2,

        // Superadmin permissions (level 3)
        'manage_positions' => 3,
        'manage_roles' => 3,
        'system_configuration' => 3,
        'view_system_logs' => 3,
        'manage_admins' => 3,
        'bulk_operations' => 3,
        'system_backup' => 3
    ];

    return $permission_levels[$permission] ?? 3; // Default to superadmin level
}

/**
 * Check resource-specific permissions (e.g., can user manage shifts for their branch only)
 */
function checkResourcePermission($pdo, $user_id, $permission, $resource, $context = []) {
    try {
        // Get user details
        $stmt = $pdo->prepare("SELECT role, outlet FROM register WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['granted' => false, 'reason' => 'User not found'];
        }

        // Branch-specific permissions for admins
        if ($user['role'] === 'admin' && isset($context['branch'])) {
            if ($context['branch'] !== $user['outlet']) {
                return [
                    'granted' => false,
                    'reason' => 'Admin can only manage their own branch',
                    'user_branch' => $user['outlet'],
                    'target_branch' => $context['branch']
                ];
            }
        }

        // Employee-specific permissions
        if (isset($context['employee_id'])) {
            if ($context['employee_id'] == $user_id) {
                // Users can always manage their own data
                return ['granted' => true, 'reason' => 'Self-access granted'];
            }

            // Check if admin can manage this employee
            if ($user['role'] === 'admin') {
                $stmt = $pdo->prepare("SELECT outlet FROM register WHERE id = ?");
                $stmt->execute([$context['employee_id']]);
                $employee_branch = $stmt->fetchColumn();

                if ($employee_branch !== $user['outlet']) {
                    return [
                        'granted' => false,
                        'reason' => 'Admin can only manage employees in their branch',
                        'user_branch' => $user['outlet'],
                        'employee_branch' => $employee_branch
                    ];
                }
            }
        }

        return ['granted' => true, 'reason' => 'Resource access granted'];

    } catch (PDOException $e) {
        error_log("Error checking resource permission: " . $e->getMessage());
        return ['granted' => false, 'reason' => 'Database error'];
    }
}

/**
 * SESSION & AUTHENTICATION FUNCTIONS
 */

/**
 * Validate and sync role with position
 * Ensures role is always consistent with position from database
 */
function syncRoleWithPosisi($pdo, $posisi) {
    return getRoleByPosisiFromDB($pdo, $posisi);
}

/**
 * Enhanced session validation with role checking
 */
function validateSession($pdo, $redirect = true) {
    if (!isset($_SESSION['user_id'])) {
        if ($redirect) {
            header('Location: index.php?error=notloggedin');
            exit;
        }
        return ['valid' => false, 'reason' => 'No active session'];
    }

    $role_validation = validateUserRole($pdo, $_SESSION['user_id']);

    if (!$role_validation['valid']) {
        if ($redirect) {
            session_destroy();
            header('Location: index.php?error=invalidrole');
            exit;
        }
        return ['valid' => false, 'reason' => $role_validation['message']];
    }

    return [
        'valid' => true,
        'user_id' => $_SESSION['user_id'],
        'role' => $role_validation['role'],
        'level' => $role_validation['level']
    ];
}

/**
 * Require specific role for page access
 */
function requireRole($pdo, $required_role, $redirect_url = 'index.php?error=unauthorized') {
    $session = validateSession($pdo, false);

    if (!$session['valid']) {
        header("Location: $redirect_url");
        exit;
    }

    $role_check = validateUserRole($pdo, $session['user_id'], $required_role);

    if (!$role_check['valid']) {
        header("Location: $redirect_url");
        exit;
    }

    return $session;
}

/**
 * Require specific permission for action
 */
function requirePermission($pdo, $permission, $resource = null, $context = []) {
    $session = validateSession($pdo, false);

    if (!$session['valid']) {
        return ['granted' => false, 'reason' => 'Invalid session'];
    }

    $permission_check = checkPermission($pdo, $session['user_id'], $permission, $resource, $context);

    if (!$permission_check['granted']) {
        return $permission_check;
    }

    return array_merge($permission_check, $session);
}

/**
 * UTILITY FUNCTIONS
 */

/**
 * Get all positions with their roles from database
 * Useful for dropdowns, validation, etc.
 */
function getAllPosisiWithRoles($pdo) {
    try {
        $stmt = $pdo->query("SELECT nama_posisi, role_posisi FROM posisi_jabatan ORDER BY nama_posisi ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting all posisi: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if position exists in database
 */
function posisiExists($pdo, $posisi) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM posisi_jabatan WHERE nama_posisi = ?");
        $stmt->execute([trim($posisi)]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error checking posisi exists: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user permissions matrix
 */
function getUserPermissions($role) {
    $role_level = getRoleLevel($role);

    $all_permissions = [
        // Level 1 - User
        'view_own_profile' => 1,
        'update_own_profile' => 1,
        'view_own_attendance' => 1,
        'submit_leave_request' => 1,
        'view_own_salary' => 1,
        'confirm_shift' => 1,
        'generate_cv' => 1,

        // Level 2 - Admin
        'view_all_attendance' => 2,
        'manage_shifts' => 2,
        'approve_leave_requests' => 2,
        'view_employee_list' => 2,
        'generate_reports' => 2,
        'send_notifications' => 2,
        'manage_whitelist' => 2,
        'view_overview' => 2,

        // Level 3 - Superadmin
        'manage_positions' => 3,
        'manage_roles' => 3,
        'system_configuration' => 3,
        'view_system_logs' => 3,
        'manage_admins' => 3,
        'bulk_operations' => 3,
        'system_backup' => 3
    ];

    $user_permissions = [];
    foreach ($all_permissions as $permission => $required_level) {
        if ($role_level >= $required_level) {
            $user_permissions[] = $permission;
        }
    }

    return $user_permissions;
}

/**
 * Log user activity for audit trail
 */
function logUserActivity($pdo, $user_id, $action, $description, $metadata = []) {
    try {
        $metadata_json = !empty($metadata) ? json_encode($metadata) : null;

        $stmt = $pdo->prepare("
            INSERT INTO activity_logs
            (user_id, action, description, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $user_id,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);

        return true;
    } catch (PDOException $e) {
        error_log("Failed to log activity for user $user_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Enhanced activity logging with logger integration
 */
function logUserActivityEnhanced($pdo, $user_id, $action, $description, $metadata = []) {
    // Log to database
    logUserActivity($pdo, $user_id, $action, $description, $metadata);

    // Also log to file logger if available
    if (class_exists('Logger')) {
        $logger = Logger::getInstance();
        $logger->info("User Activity: $action", array_merge($metadata, [
            'user_id' => $user_id,
            'action' => $action,
            'description' => $description
        ]));
    }
}

/**
 * Get role display information
 */
function getRoleDisplayInfo($role) {
    $role_info = [
        'user' => [
            'name' => 'Karyawan',
            'color' => '#28a745',
            'icon' => 'fas fa-user',
            'description' => 'Akses dasar untuk karyawan'
        ],
        'admin' => [
            'name' => 'Admin',
            'color' => '#dc3545',
            'icon' => 'fas fa-user-shield',
            'description' => 'Akses manajemen untuk admin cabang'
        ],
        'superadmin' => [
            'name' => 'Super Admin',
            'color' => '#6f42c1',
            'icon' => 'fas fa-crown',
            'description' => 'Akses penuh sistem untuk owner/manajemen tertinggi'
        ]
    ];

    return $role_info[strtolower($role)] ?? [
        'name' => 'Unknown',
        'color' => '#6c757d',
        'icon' => 'fas fa-question',
        'description' => 'Role tidak dikenal'
    ];
}
?>
