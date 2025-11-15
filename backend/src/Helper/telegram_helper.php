<?php
// No logger dependency - using error_log instead

/**
 * Telegram Bot Helper untuk notifikasi sistem KAORI HR
 */

// Konfigurasi Telegram Bot
define('TELEGRAM_BOT_TOKEN', "8578910089:AAEv9A6gZpjDLbIug6sGAL2_Jv_JD9o-GUY");
define('TELEGRAM_API_URL', "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/");

// Konfigurasi Channel untuk Upload File
define('TELEGRAM_CHANNEL_ID', "1002949469046"); // Channel ID baru

/**
 * Kirim pesan ke Telegram chat
 */
function sendTelegramMessage($chat_id, $message, $parse_mode = 'HTML') {
    if (empty(TELEGRAM_BOT_TOKEN) || TELEGRAM_BOT_TOKEN === 'YOUR_BOT_TOKEN_HERE') {
        error_log("Telegram bot token not configured");
        return false;
    }

    $url = TELEGRAM_API_URL . 'sendMessage';

    $postData = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => $parse_mode,
        'disable_web_page_preview' => true
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Log response untuk debugging
    error_log("Telegram API response untuk chat {$chat_id}: " . $response . " (HTTP: {$http_code})");

    if ($error) {
        error_log("Telegram API curl error: " . $error);
        return false;
    }

    $response_data = json_decode($response, true);

    if ($response_data && isset($response_data['ok']) && $response_data['ok'] === true) {
        error_log("Telegram message berhasil dikirim ke chat {$chat_id}");
        return true;
    }

    if ($response_data && isset($response_data['description'])) {
        error_log("Telegram message gagal ke chat {$chat_id}: " . $response_data['description']);
    }

    return false;
}

/**
 * Kirim notifikasi pengajuan izin baru ke Telegram (Channel + File Upload)
 */
function sendTelegramIzinBaru($izin_data, $user_data, $pdo) {
    try {
        $success = false;
        
        // 1. Kirim notifikasi ke channel dengan pesan lengkap
        $channel_message = "🔔 <b>PENGAJUAN IZIN BARU</b>\n\n" .
                          "📄 <b>Nomor Surat:</b> {$izin_data['nomor_surat']}\n" .
                          "👤 <b>Nama:</b> {$user_data['nama_lengkap']}\n" .
                          "🏢 <b>Outlet:</b> {$user_data['outlet']}\n" .
                          "💼 <b>Posisi:</b> {$user_data['posisi']}\n" .
                          "📝 <b>Perihal:</b> {$izin_data['perihal']}\n" .
                          "📅 <b>Tanggal:</b> {$izin_data['tanggal_mulai']} - {$izin_data['tanggal_selesai']}\n" .
                          "⏱️ <b>Durasi:</b> {$izin_data['durasi_hari']} hari\n" .
                          "💬 <b>Alasan:</b> {$izin_data['alasan']}\n\n" .
                          "⚡ <i>Surat izin terlampir</i>";

        if (sendTelegramMessage(TELEGRAM_CHANNEL_ID, $channel_message)) {
            $success = true;
            error_log("Telegram: Notifikasi berhasil dikirim ke channel");
        }

        // 2. Upload file surat ke channel jika ada
        if (isset($izin_data['file_path']) && file_exists($izin_data['file_path'])) {
            if (uploadFileToTelegram(TELEGRAM_CHANNEL_ID, $izin_data['file_path'], "Surat Izin - {$izin_data['nomor_surat']}")) {
                error_log("Telegram: File berhasil diupload ke channel");
            } else {
                error_log("Telegram: Gagal upload file ke channel");
            }
        }

        return $success;

    } catch (Exception $e) {
        error_log("Telegram notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Kirim notifikasi status pengajuan izin ke Telegram (untuk user)
 */
function sendTelegramIzinStatus($izin_data, $user_data, $status, $catatan = '', $approver_data = []) {
    try {
        // Cek apakah user memiliki chat_id Telegram
        if (empty($user_data['telegram_chat_id'])) {
            return false; // User belum connect ke bot Telegram
        }

        $status_emoji = '';
        $status_text = '';

        switch (strtolower($status)) {
            case 'approved':
            case 'disetujui':
                $status_emoji = '✅';
                $status_text = 'DISETUJUI';
                break;
            case 'rejected':
            case 'ditolak':
                $status_emoji = '❌';
                $status_text = 'DITOLAK';
                break;
            case 'pending':
            case 'menunggu':
                $status_emoji = '⏳';
                $status_text = 'SEDANG DIPROSES';
                break;
            default:
                $status_emoji = '📝';
                $status_text = strtoupper($status);
        }

        $message = "{$status_emoji} <b>STATUS PENGAJUAN IZIN: {$status_text}</b>\n\n" .
                  "📄 <b>Nomor Surat:</b> {$izin_data['nomor_surat']}\n" .
                  "👤 <b>Nama:</b> {$user_data['nama_lengkap']}\n" .
                  "📝 <b>Perihal:</b> {$izin_data['perihal']}\n" .
                  "📅 <b>Tanggal:</b> {$izin_data['tanggal_mulai']} - {$izin_data['tanggal_selesai']}\n" .
                  "⏱️ <b>Durasi:</b> {$izin_data['durasi_hari']} hari\n" .
                  "💬 <b>Alasan:</b> {$izin_data['alasan']}\n";

        if (!empty($catatan)) {
            $message .= "\n📝 <b>Catatan Admin:</b> {$catatan}\n";
        }

        if (!empty($approver_data['nama_lengkap'])) {
            $message .= "\n👨‍💼 <b>Diproses oleh:</b> {$approver_data['nama_lengkap']}\n";
        }

        $message .= "\n⚡ <i>Silakan login ke sistem untuk detail lebih lanjut</i>";

        return sendTelegramMessage($user_data['telegram_chat_id'], $message);

    } catch (Exception $e) {
        error_log("Telegram status notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Ambil chat ID Telegram admin/HR dari database
 */
function getAdminTelegramChatIds($pdo) {
    try {
        $stmt = $pdo->query("SELECT telegram_chat_id FROM register WHERE role IN ('hr', 'kepala_toko', 'admin', 'superadmin') AND telegram_chat_id IS NOT NULL AND telegram_chat_id != ''");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Error getting admin Telegram chat IDs: " . $e->getMessage());
        return [];
    }
}

/**
 * Test koneksi ke Telegram Bot API
 */
function testTelegramConnection() {
    if (empty(TELEGRAM_BOT_TOKEN) || TELEGRAM_BOT_TOKEN === 'YOUR_BOT_TOKEN_HERE') {
        return ['success' => false, 'message' => 'Bot token tidak dikonfigurasi'];
    }

    $url = TELEGRAM_API_URL . 'getMe';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => 'Curl error: ' . $error];
    }

    $response_data = json_decode($response, true);

    if ($response_data && isset($response_data['ok']) && $response_data['ok'] === true) {
        $bot_info = $response_data['result'];
        return [
            'success' => true,
            'message' => 'Koneksi berhasil',
            'bot_username' => $bot_info['username'] ?? 'Unknown',
            'bot_name' => $bot_info['first_name'] ?? 'Unknown'
        ];
    }

    return [
        'success' => false,
        'message' => 'API response: ' . ($response_data['description'] ?? 'Unknown error')
    ];
}

/**
 * Send message with inline keyboard
 */
function sendTelegramMessageWithKeyboard($chat_id, $text, $keyboard = null, $parse_mode = 'HTML') {
    if (empty(TELEGRAM_BOT_TOKEN) || TELEGRAM_BOT_TOKEN === 'YOUR_BOT_TOKEN_HERE') {
        error_log("Telegram bot token not configured");
        return false;
    }

    $url = TELEGRAM_API_URL . 'sendMessage';

    $postData = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse_mode,
        'disable_web_page_preview' => true
    ];

    if ($keyboard) {
        $postData['reply_markup'] = json_encode($keyboard);
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Log response untuk debugging
    error_log("Telegram keyboard message response untuk chat {$chat_id}: " . $response . " (HTTP: {$http_code})");

    if ($error) {
        error_log("Telegram API curl error: " . $error);
        return false;
    }

    $response_data = json_decode($response, true);

    if ($response_data && isset($response_data['ok']) && $response_data['ok'] === true) {
        error_log("Telegram keyboard message berhasil dikirim ke chat {$chat_id}");
        return true;
    }

    if ($response_data && isset($response_data['description'])) {
        error_log("Telegram keyboard message gagal ke chat {$chat_id}: " . $response_data['description']);
    }

    return false;
}

/**
 * Update user's Telegram username when they start the bot
 */
function updateUserTelegramInfo($pdo, $user_id, $chat_id, $username) {
    try {
        $stmt = $pdo->prepare("UPDATE register SET telegram_chat_id = ?, telegram_username = ? WHERE id = ?");
        $stmt->execute([$chat_id, $username, $user_id]);
        return true;
    } catch (Exception $e) {
        error_log("Error updating user Telegram info: " . $e->getMessage());
        return false;
    }
}

/**
 * Upload file ke Telegram Channel
 */
function uploadFileToTelegram($chat_id, $file_path, $caption = '') {
    if (!file_exists($file_path)) {
        error_log("File tidak ditemukan: " . $file_path);
        return false;
    }

    $url = TELEGRAM_API_URL . 'sendDocument';

    $postData = [
        'chat_id' => $chat_id,
        'document' => new CURLFile($file_path),
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_TIMEOUT => 60, // Timeout lebih lama untuk file upload
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    error_log("Telegram file upload response: " . $response . " (HTTP: {$http_code})");

    if ($error) {
        error_log("Telegram file upload curl error: " . $error);
        return false;
    }

    $response_data = json_decode($response, true);

    if ($response_data && isset($response_data['ok']) && $response_data['ok'] === true) {
        error_log("Telegram file berhasil diupload ke chat {$chat_id}");
        return true;
    }

    if ($response_data && isset($response_data['description'])) {
        error_log("Telegram file upload gagal ke chat {$chat_id}: " . $response_data['description']);
    }

    return false;
}

/**
 * Ambil daftar nama karyawan dari whitelist untuk validasi Telegram
 */
function getKaryawanWhitelist($pdo) {
    try {
        // Cek apakah kolom 'aktif' ada di tabel
        $stmt = $pdo->prepare("SHOW COLUMNS FROM register LIKE 'aktif'");
        $stmt->execute();
        $aktif_column_exists = $stmt->fetch() !== false;
        
        if ($aktif_column_exists) {
            $stmt = $pdo->query("SELECT nama_lengkap FROM register WHERE role IN ('hr', 'kepala_toko', 'admin', 'superadmin', 'karyawan') AND aktif = 1");
        } else {
            // Jika kolom 'aktif' tidak ada, ambil semua user
            $stmt = $pdo->query("SELECT nama_lengkap FROM register WHERE role IN ('hr', 'kepala_toko', 'admin', 'superadmin', 'karyawan')");
        }
        
        $whitelist = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Debug: Log semua nama yang ditemukan
        error_log("Telegram Whitelist Debug: Found " . count($whitelist) . " users");
        foreach ($whitelist as $nama) {
            error_log("Telegram Whitelist: " . trim($nama));
        }
        
        return $whitelist;
    } catch (Exception $e) {
        error_log("Error getting karyawan whitelist: " . $e->getMessage());
        
        // Fallback: coba query tanpa kondisi role
        try {
            $stmt = $pdo->query("SELECT nama_lengkap FROM register");
            $whitelist = $stmt->fetchAll(PDO::FETCH_COLUMN);
            error_log("Telegram Whitelist (Fallback): Found " . count($whitelist) . " users");
            return $whitelist;
        } catch (Exception $e2) {
            error_log("Fallback query also failed: " . $e2->getMessage());
            return [];
        }
    }
}

/**
 * Proses validasi nama karyawan di Telegram Bot
 */
function processTelegramUserValidation($nama, $pdo) {
    $whitelist = getKaryawanWhitelist($pdo);
    $nama_input = trim($nama);
    $nama_input_lower = strtolower($nama_input);
    
    // Debug: Log input nama
    error_log("Telegram Validation: Looking for '{$nama_input}' (lower: '{$nama_input_lower}')");
    error_log("Telegram Validation: Whitelist count: " . count($whitelist));
    
    // Exact match first
    foreach ($whitelist as $nama_db) {
        $nama_db_trim = trim($nama_db);
        if ($nama_db_trim === $nama_input) {
            error_log("Telegram Validation: Exact match found for '{$nama_input}'");
            return [
                'valid' => true,
                'message' => "✅ Nama ditemukan! Silakan pergi ke bot @UserInfoToBot dan klik /start lalu copy ID ke sini."
            ];
        }
    }
    
    // Case-insensitive exact match
    foreach ($whitelist as $nama_db) {
        $nama_db_trim = trim($nama_db);
        $nama_db_lower = strtolower($nama_db_trim);
        if ($nama_db_lower === $nama_input_lower) {
            error_log("Telegram Validation: Case-insensitive match found for '{$nama_input}'");
            return [
                'valid' => true,
                'message' => "✅ Nama ditemukan! Silakan pergi ke bot @UserInfoToBot dan klik /start lalu copy ID ke sini."
            ];
        }
    }
    
    // Partial match (input name contained in database name)
    foreach ($whitelist as $nama_db) {
        $nama_db_trim = trim($nama_db);
        $nama_db_lower = strtolower($nama_db_trim);
        if (strpos($nama_db_lower, $nama_input_lower) !== false) {
            error_log("Telegram Validation: Partial match found '{$nama_input}' in '{$nama_db_trim}'");
            return [
                'valid' => true,
                'message' => "✅ Nama ditemukan! Silakan pergi ke bot @UserInfoToBot dan klik /start lalu copy ID ke sini."
            ];
        }
    }
    
    // Database name contained in input
    foreach ($whitelist as $nama_db) {
        $nama_db_trim = trim($nama_db);
        $nama_db_lower = strtolower($nama_db_trim);
        if (strpos($nama_input_lower, $nama_db_lower) !== false) {
            error_log("Telegram Validation: Database name contained in input '{$nama_db_trim}' in '{$nama_input}'");
            return [
                'valid' => true,
                'message' => "✅ Nama ditemukan! Silakan pergi ke bot @UserInfoToBot dan klik /start lalu copy ID ke sini."
            ];
        }
    }
    
    // Remove spaces and special characters for comparison
    $nama_input_clean = preg_replace('/[^a-zA-Z0-9]/', '', $nama_input_lower);
    foreach ($whitelist as $nama_db) {
        $nama_db_trim = trim($nama_db);
        $nama_db_clean = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($nama_db_trim));
        if (!empty($nama_input_clean) && !empty($nama_db_clean) && $nama_input_clean === $nama_db_clean) {
            error_log("Telegram Validation: Clean match found '{$nama_input_clean}' in '{$nama_db_clean}'");
            return [
                'valid' => true,
                'message' => "✅ Nama ditemukan! Silakan pergi ke bot @UserInfoToBot dan klik /start lalu copy ID ke sini."
            ];
        }
    }
    
    // Debug: Log all names that were checked
    error_log("Telegram Validation: No match found for '{$nama_input}'. Available names:");
    foreach ($whitelist as $nama_db) {
        error_log("  - '" . trim($nama_db) . "'");
    }
    
    return [
        'valid' => false,
        'message' => "❌ Hush hush hush... kamu bukan Teman Kaori."
    ];
}
?>