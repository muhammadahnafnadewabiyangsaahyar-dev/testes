<?php
/**
 * Authentication Service - Layanan Autentikasi
 * 
 * Mengelola seluruh proses autentikasi pengguna termasuk:
 * - Login validation
 * - Session management
 * - Password hashing
 * - Whitelist verification
 * 
 * @author Tim Pengembang Kaori HR
 * @version 1.0.0
 */

namespace KaoriHR\Services;

use Monolog\Logger;
use PDOException;

class AuthenticationService
{
    private DatabaseService $databaseService;
    private Logger $logger;

    public function __construct(DatabaseService $databaseService, Logger $logger)
    {
        $this->databaseService = $databaseService;
        $this->logger = $logger;
    }

    /**
     * Validasi login credentials
     */
    public function validateLogin(string $username, string $password): array
    {
        try {
            $sql = "SELECT id, nama_lengkap, username, email, password, role, posisi, outlet, 
                           no_telegram, status, time_created
                    FROM register 
                    WHERE username = ? AND status = 'active'";
            
            $user = $this->databaseService->executeQuerySingle($sql, [$username]);
            
            if (!$user) {
                $this->logger->warning("Login attempt dengan username tidak ditemukan", ['username' => $username]);
                return [
                    'success' => false,
                    'error' => 'usernotfound',
                    'message' => 'Username tidak ditemukan.'
                ];
            }

            if (!password_verify($password, $user['password'])) {
                $this->logger->warning("Login attempt dengan password salah", ['username' => $username]);
                return [
                    'success' => false,
                    'error' => 'invalidpassword',
                    'message' => 'Password yang Anda masukkan salah.'
                ];
            }

            $this->logger->info("Login berhasil", [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ]);

            // Remove password from return data
            unset($user['password']);

            return [
                'success' => true,
                'user' => $user
            ];

        } catch (\Exception $e) {
            $this->logger->error("Error validasi login", [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'dberror',
                'message' => 'Terjadi masalah pada database. Hubungi admin.'
            ];
        }
    }

    /**
     * Membuat session untuk user
     */
    public function createSession(array $user): void
    {
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['posisi'] = $user['posisi'];
        $_SESSION['outlet'] = $user['outlet'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['no_telegram'] = $user['no_telegram'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        $this->logger->info("Session berhasil dibuat", [
            'user_id' => $user['id'],
            'username' => $user['username']
        ]);
    }

    /**
     * Validasi session yang sedang aktif
     */
    public function validateSession(): bool
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
            return false;
        }

        // Check session timeout (30 minutes)
        $timeout = 30 * 60; // 30 minutes in seconds
        if (time() - $_SESSION['last_activity'] > $timeout) {
            $this->logger->info("Session timeout", ['user_id' => $_SESSION['user_id']]);
            return false;
        }

        // Update last activity
        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Destroy session
     */
    public function destroySession(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        
        session_unset();
        session_destroy();
        session_start(); // Start fresh session

        $this->logger->info("Session berhasil dihancurkan", ['user_id' => $userId]);
    }

    /**
     * Verifikasi whitelist untuk registrasi
     */
    public function verifyWhitelist(string $namaLengkap): array
    {
        try {
            $sql = "SELECT nama_lengkap, posisi, role, status_registrasi 
                    FROM pegawai_whitelist 
                    WHERE nama_lengkap = ? AND status_registrasi = 'pending'";
            
            $whitelistData = $this->databaseService->executeQuerySingle($sql, [$namaLengkap]);
            
            if (!$whitelistData) {
                // Check if already registered
                $checkRegisteredSql = "SELECT id FROM register WHERE nama_lengkap = ?";
                $isRegistered = $this->databaseService->executeQuerySingle($checkRegisteredSql, [$namaLengkap]);
                
                if ($isRegistered) {
                    return [
                        'found' => false,
                        'error' => 'already_registered',
                        'message' => 'Nama ini sudah terdaftar. Silakan login.'
                    ];
                }

                return [
                    'found' => false,
                    'error' => 'not_in_whitelist',
                    'message' => 'Nama Anda belum terdaftar di whitelist. Silakan hubungi HR/Admin untuk ditambahkan.'
                ];
            }

            $this->logger->info("Whitelist verified", [
                'nama' => $namaLengkap,
                'posisi' => $whitelistData['posisi'],
                'role' => $whitelistData['role']
            ]);

            return [
                'found' => true,
                'data' => $whitelistData
            ];

        } catch (\Exception $e) {
            $this->logger->error("Error verifikasi whitelist", [
                'nama' => $namaLengkap,
                'error' => $e->getMessage()
            ]);
            
            return [
                'found' => false,
                'error' => 'database_error',
                'message' => 'Terjadi kesalahan saat memverifikasi. Silakan coba lagi.'
            ];
        }
    }

    /**
     * Proses registrasi user baru
     */
    public function registerUser(array $userData): array
    {
        try {
            // Validate input
            $validation = $this->validateRegistrationData($userData);
            if (!$validation['valid']) {
                return $validation;
            }

            // Verify whitelist first
            $whitelistCheck = $this->verifyWhitelist($userData['nama_panjang']);
            if (!$whitelistCheck['found']) {
                return [
                    'success' => false,
                    'errors' => ['nama_panjang' => $whitelistCheck['message']]
                ];
            }

            $whitelistData = $whitelistCheck['data'];
            
            // Start transaction
            $this->databaseService->beginTransaction();
            
            try {
                // Hash password
                $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
                
                // Prepare user data
                $userRecord = [
                    'nama_lengkap' => $userData['nama_panjang'],
                    'posisi' => $whitelistData['posisi'] ?? $userData['posisi'],
                    'outlet' => $userData['outlet'],
                    'no_telegram' => $userData['no_wa'],
                    'email' => $userData['email'],
                    'password' => $hashedPassword,
                    'username' => $userData['username'],
                    'role' => $whitelistData['role'] ?? 'user'
                ];

                // Insert user
                $config = require __DIR__ . '/../../config/database.php';
                $userId = $this->databaseService->insert($config['tables']['users'], $userRecord);
                
                // Update whitelist status
                $updateWhitelistSql = "UPDATE {$config['tables']['whitelist']} 
                                     SET status_registrasi = 'terdaftar' 
                                     WHERE nama_lengkap = ?";
                $this->databaseService->executeQuery($updateWhitelistSql, [$userData['nama_panjang']]);

                // Commit transaction
                $this->databaseService->commit();
                
                $this->logger->info("Registrasi berhasil", [
                    'user_id' => $userId,
                    'nama' => $userData['nama_panjang'],
                    'email' => $userData['email']
                ]);

                return [
                    'success' => true,
                    'user_id' => $userId,
                    'message' => 'Registrasi berhasil! Silakan login.'
                ];

            } catch (\Exception $e) {
                $this->databaseService->rollback();
                throw $e;
            }

        } catch (PDOException $e) {
            // Handle specific database errors
            if ($e->getCode() == 23000) { // Duplicate entry
                $errorMsg = $e->getMessage();
                if (strpos($errorMsg, 'username') !== false) {
                    return [
                        'success' => false,
                        'errors' => ['username' => 'Username sudah digunakan.']
                    ];
                } elseif (strpos($errorMsg, 'email') !== false) {
                    return [
                        'success' => false,
                        'errors' => ['email' => 'Email sudah digunakan.']
                    ];
                } elseif (strpos($errorMsg, 'no_telegram') !== false) {
                    return [
                        'success' => false,
                        'errors' => ['no_wa' => 'Nomor Telegram sudah digunakan.']
                    ];
                }
            }

            $this->logger->error("Error registrasi user", [
                'nama' => $userData['nama_panjang'] ?? '',
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'errors' => ['general' => 'Registrasi gagal. Silakan coba lagi.']
            ];
        }
    }

    /**
     * Validasi data registrasi
     */
    private function validateRegistrationData(array $data): array
    {
        $errors = [];
        
        // Required fields
        if (empty($data['nama_panjang'])) {
            $errors['nama_panjang'] = 'Nama Lengkap harus diisi.';
        }
        
        if (empty($data['posisi'])) {
            $errors['posisi'] = 'Posisi harus dipilih.';
        }
        
        if (empty($data['outlet'])) {
            $errors['outlet'] = 'Outlet harus dipilih.';
        }
        
        // Email validation
        if (empty($data['email'])) {
            $errors['email'] = 'Email harus diisi.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format email tidak valid.';
        }
        
        // Username validation
        if (empty($data['username'])) {
            $errors['username'] = 'Username harus diisi.';
        }
        
        // Password validation
        if (empty($data['password'])) {
            $errors['password'] = 'Password harus diisi.';
        }
        
        if (empty($data['confirm_password'])) {
            $errors['confirm_password'] = 'Konfirmasi password harus diisi.';
        } elseif ($data['password'] !== $data['confirm_password']) {
            $errors['confirm_password'] = 'Password dan Konfirmasi tidak cocok.';
        }
        
        // Telegram number validation
        $noWaCleaned = trim($data['no_wa'] ?? '');
        if (empty($noWaCleaned) || $noWaCleaned === '62' || $noWaCleaned === '62 ') {
            $errors['no_wa'] = 'No. Telegram harus diisi.';
        } elseif (!preg_match('/^62\s[0-9]{8,12}$/', $noWaCleaned)) {
            $errors['no_wa'] = 'Format salah (Contoh: 62 81234567890).';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Ambil data dropdown untuk form
     */
    public function getDropdownData(): array
    {
        try {
            $config = require __DIR__ . '/../../config/database.php';
            
            // Get positions
            $sqlPosisi = "SELECT nama_posisi FROM {$config['tables']['positions']} ORDER BY nama_posisi ASC";
            $positions = $this->databaseService->executeQuery($sqlPosisi);
            
            // Get outlets (exclude KAORI HQ for registration)
            $sqlOutlets = "SELECT nama_cabang FROM {$config['tables']['outlets']} 
                          WHERE nama_cabang != 'KAORI HQ' 
                          ORDER BY nama_cabang ASC";
            $outlets = $this->databaseService->executeQuery($sqlOutlets);
            
            return [
                'positions' => array_column($positions, 'nama_posisi'),
                'outlets' => array_column($outlets, 'nama_cabang')
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Error mengambil dropdown data", ['error' => $e->getMessage()]);
            
            return [
                'positions' => [],
                'outlets' => []
            ];
        }
    }
}