<?php
session_start();

if (!isset($_SESSION['user_id']) || !isAdminOrSuperadmin($_SESSION['role'])) {
    header('Location: index.php?error=unauthorized');
    exit;
}

echo "<h1>🔧 Data Structure Debug</h1>";

if (isset($_SESSION['csv_headers'])) {
    echo "<h3>📁 CSV Headers:</h3>";
    echo "<pre>" . print_r($_SESSION['csv_headers'], true) . "</pre>";
}

if (isset($_SESSION['preview_data'])) {
    echo "<h3>📊 Preview Data (Raw):</h3>";
    echo "<pre>" . print_r($_SESSION['preview_data'], true) . "</pre>";
}

if (isset($_SESSION['preview_mapped_data'])) {
    echo "<h3>🗺️ Mapped Data:</h3>";
    echo "<pre>" . print_r($_SESSION['preview_mapped_data'], true) . "</pre>";
}

if (isset($_SESSION['import_stats'])) {
    echo "<h3>📈 Import Statistics:</h3>";
    echo "<pre>" . print_r($_SESSION['import_stats'], true) . "</pre>";
}

echo '<br><a href="import_whitelist.php">← Kembali ke Import Whitelist</a>';
?>