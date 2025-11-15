<?php
/**
 * Authentication Controller
 * 
 * Controller untuk menangani semua request terkait autentikasi
 * Menghubungkan frontend dengan AuthenticationService
 * 
 * @author Tim Pengembang Kaori HR
 * @version 1.0.0
 */

namespace KaoriHR\Controllers;

use KaoriHR\Services\AuthenticationService;
use Monolog\Logger;

class AuthController
{
    private AuthenticationService $authService;
    private Logger $logger;

    public function __construct(AuthenticationService $authService, Logger $logger)
    {
        $this->authService = $authService;
        $this->logger = $logger;
    }

    /**
     * Menangani request login
     */
    public function login(): void
    {
        try {
            // Validate input
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $this->redirectWithError('emptyfields');
                return;
            }

            // Validate login
            $result = $this->authService->validateLogin($username, $password);
            
            if ($result['success']) {
                // Create session
                $this->authService->createSession($result['user']);
                
                // Redirect based on role
                $role = $result['user']['role'];
                if ($role === 'admin' || $role === 'superadmin') {
                    header('Location: mainpage.php');
                } else {
                    header('Location: mainpage.php');
                }
                exit;
            } else {
                $this->redirectWithError($result['error']);
            }

        } catch (\Exception $e) {
            $this->logger->error("Error dalam proses login", [
                'username' => $_POST['username'] ?? '',
                'error' => $e->getMessage()
            ]);
            
            $this->redirectWithError('dberror');
        }
    }

    /**
     * Menangani request logout
     */
    public function logout(): void
    {
        $this->authService->destroySession();
        header('Location: index.php');
        exit;
    }

    /**
     * Verifikasi whitelist via AJAX
     */
    public function verifyWhitelist(): void
    {
        try {
            header('Content-Type: application/json');
            
            $nama = trim($_GET['nama'] ?? '');
            
            if (empty($nama)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Nama tidak boleh kosong.'
                ]);
                return;
            }

            $result = $this->authService->verifyWhitelist($nama);
            
            if ($result['found']) {
                echo json_encode([
                    'success' => true,
                    'found' => true,
                    'nama_lengkap' => $result['data']['nama_lengkap'],
                    'posisi' => $result['data']['posisi'],
                    'role' => $result['data']['role'],
                    'status' => $result['data']['status_registrasi']
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'found' => false,
                    'message' => $result['message']
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error("Error verifikasi whitelist", [
                'nama' => $_GET['nama'] ?? '',
                'error' => $e->getMessage()
            ]);
            
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan server. Silakan coba lagi.'
            ]);
        }
    }

    /**
     * Menangani request registrasi
     */
    public function register(): void
    {
        try {
            $registrationAttempted = true;
            
            // Collect form data
            $userData = [
                'nama_panjang' => trim($_POST['nama_panjang'] ?? ''),
                'posisi' => trim($_POST['posisi'] ?? ''),
                'outlet' => trim($_POST['outlet'] ?? ''),
                'no_wa' => trim($_POST['no_wa'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'username' => trim($_POST['username'] ?? ''),
                'password' => $_POST['password'] ?? '',
                'confirm_password' => $_POST['confirm_password'] ?? ''
            ];

            // Process registration
            $result = $this->authService->registerUser($userData);
            
            if ($result['success']) {
                header('Location: index.php?status=register_success');
                exit;
            } else {
                // Return with errors for form display
                $_SESSION['registration_errors'] = $result['errors'];
                $_SESSION['form_data'] = $userData;
                
                header('Location: index.php?status=register_error');
                exit;
            }

        } catch (\Exception $e) {
            $this->logger->error("Error dalam proses registrasi", [
                'nama' => $_POST['nama_panjang'] ?? '',
                'error' => $e->getMessage()
            ]);
            
            $_SESSION['registration_errors'] = ['general' => 'Sistem sedang mengalami gangguan. Silakan coba lagi nanti.'];
            header('Location: index.php?status=register_error');
            exit;
        }
    }

    /**
     * Ambil data dropdown untuk form
     */
    public function getDropdownData(): array
    {
        return $this->authService->getDropdownData();
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn(): bool
    {
        return $this->authService->validateSession();
    }

    /**
     * Get current user data
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? '',
            'nama_lengkap' => $_SESSION['nama_lengkap'] ?? '',
            'role' => $_SESSION['role'] ?? '',
            'posisi' => $_SESSION['posisi'] ?? '',
            'outlet' => $_SESSION['outlet'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'no_telegram' => $_SESSION['no_telegram'] ?? ''
        ];
    }

    /**
     * Redirect dengan error message
     */
    private function redirectWithError(string $error): void
    {
        $errorMessages = [
            'invalidpassword' => 'Password yang Anda masukkan salah.',
            'usernotfound' => 'Username tidak ditemukan.',
            'emptyfields' => 'Username dan Password harus diisi.',
            'dberror' => 'Terjadi masalah pada database. Hubungi admin.',
            'toomanyattempts' => 'Terlalu banyak percobaan login. Silakan tunggu 15 menit dan coba lagi.',
            'sessionexpired' => 'Sesi Anda telah berakhir. Silakan login kembali.',
            'notloggedin' => 'Anda harus login terlebih dahulu.'
        ];

        $message = $errorMessages[$error] ?? 'Terjadi kesalahan yang tidak diketahui.';
        header("Location: index.php?error={$error}");
        exit;
    }

    /**
     * Generate CSRF token
     */
    public function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token
     */
    public function validateCsrfToken(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}