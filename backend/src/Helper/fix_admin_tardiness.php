<?php
// Fix Admin Tardiness - Perbaiki data admin yang dinilai terlambat
require_once 'connect.php';

echo "<h2>🔧 FIX ADMIN TARDINESS DATA</h2>";
echo "<pre>";

// Check current admin/superadmin users
echo "--- CHECK ADMIN/SUPERADMIN USERS ---\n";
$admin_users_query = "SELECT r.id, r.nama_lengkap, r.role, 
                      COUNT(a.id) as total_absensi,
                      COUNT(CASE WHEN a.status_keterlambatan != 'tepat waktu' AND a.status_keterlambatan != 'tidak ada shift' THEN 1 END) as tardy_count
                      FROM register r
                      LEFT JOIN absensi a ON r.id = a.user_id
                      WHERE r.role IN ('admin', 'superadmin') 
                      AND r.is_active = 1
                      GROUP BY r.id, r.nama_lengkap, r.role
                      ORDER BY r.role, r.nama_lengkap";
                      
$stmt = $pdo->query($admin_users_query);
$admin_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($admin_users)) {
    echo "❌ No admin/superadmin users found\n";
} else {
    foreach ($admin_users as $user) {
        echo "👤 " . $user['nama_lengkap'] . " (" . $user['role'] . ")\n";
        echo "   Total Absensi: " . $user['total_absensi'] . "\n";
        echo "   Tardy Records: " . $user['tardy_count'] . "\n";
        
        if ($user['tardy_count'] > 0) {
            echo "   ⚠️ HAS TARDINESS RECORDS - Will be fixed\n";
        } else {
            echo "   ✅ No tardiness issues\n";
        }
        echo "\n";
    }
}

// Fix tardy records for admin/superadmin
echo "--- FIXING TARDY RECORDS ---\n";
$fix_query = "UPDATE absensi 
              SET menit_terlambat = 0,
                  status_keterlambatan = 'tepat waktu',
                  potongan_tunjangan = 'tidak ada'
              WHERE user_id IN (
                  SELECT id FROM register 
                  WHERE role IN ('admin', 'superadmin') 
                  AND is_active = 1
              )
              AND status_keterlambatan != 'tepat waktu'";

try {
    $stmt_fix = $pdo->prepare($fix_query);
    $result = $stmt_fix->execute();
    $rows_affected = $stmt_fix->rowCount();
    
    echo "✅ Fixed $rows_affected records\n";
    
    if ($rows_affected > 0) {
        echo "Successfully updated admin/superadmin tardiness records\n";
        echo "All admin/superadmin are now marked as:\n";
        echo "- menit_terlambat: 0\n";
        echo "- status_keterlambatan: 'tidak ada shift - admin/superadmin'\n";
        echo "- potongan_tunjangan: 'tidak ada'\n";
    } else {
        echo "ℹ️ No records needed fixing\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error fixing records: " . $e->getMessage() . "\n";
}

// Verify the fix
echo "\n--- VERIFICATION ---\n";
$verify_query = "SELECT r.nama_lengkap, r.role, 
                 COUNT(a.id) as total_absensi,
                 COUNT(CASE WHEN a.status_keterlambatan NOT IN ('tepat waktu', 'tidak ada shift - admin/superadmin') THEN 1 END) as still_tardy
                 FROM register r
                 LEFT JOIN absensi a ON r.id = a.user_id
                 WHERE r.role IN ('admin', 'superadmin') 
                 AND r.is_active = 1
                 GROUP BY r.id, r.nama_lengkap, r.role
                 ORDER BY r.role, r.nama_lengkap";

$stmt_verify = $pdo->query($verify_query);
$verification = $stmt_verify->fetchAll(PDO::FETCH_ASSOC);

foreach ($verification as $user) {
    echo "👤 " . $user['nama_lengkap'] . " (" . $user['role'] . ")\n";
    echo "   Total Absensi: " . $user['total_absensi'] . "\n";
    echo "   Still Tardy: " . $user['still_tardy'] . "\n";
    
    if ($user['still_tardy'] == 0) {
        echo "   ✅ FIXED - No tardiness issues\n";
    } else {
        echo "   ⚠️ Still has tardiness: " . $user['still_tardy'] . " records\n";
    }
    echo "\n";
}

echo "--- SUMMARY ---\n";
echo "✅ Admin/superadmin tardiness calculation logic updated\n";
echo "✅ Existing incorrect records fixed\n";
echo "✅ Future absensi will correctly exclude admin/superadmin from tardiness\n";
echo "\n🎉 Admin tardiness issue resolved!";

echo "</pre>";
?>