<?php
/**
 * Create Face Recognition Tables
 * Script to create face_models and face_recognition_logs tables
 */

include 'connect.php';

echo "Creating face recognition tables...\n\n";

// Create face_models table
$sql1 = "CREATE TABLE IF NOT EXISTS `face_models` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `face_data` longblob NOT NULL,
  `face_embedding` text DEFAULT NULL,
  `quality_score` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_face` (`user_id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_face_user` FOREIGN KEY (`user_id`) REFERENCES `register` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if ($conn->query($sql1) === TRUE) {
    echo "✓ Table 'face_models' created successfully\n";
} else {
    echo "✗ Error creating table 'face_models': " . $conn->error . "\n";
}

// Create face_recognition_logs table
$sql2 = "CREATE TABLE IF NOT EXISTS `face_recognition_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `attempted_user_id` int(11) DEFAULT NULL,
  `similarity_score` decimal(5,2) DEFAULT NULL,
  `confidence_level` enum('high','medium','low') DEFAULT NULL,
  `recognition_result` enum('success','failed','no_match') NOT NULL DEFAULT 'failed',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `attempted_user_id` (`attempted_user_id`),
  KEY `recognition_result` (`recognition_result`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_face_log_user` FOREIGN KEY (`user_id`) REFERENCES `register` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_face_log_attempted` FOREIGN KEY (`attempted_user_id`) REFERENCES `register` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if ($conn->query($sql2) === TRUE) {
    echo "✓ Table 'face_recognition_logs' created successfully\n";
} else {
    echo "✗ Error creating table 'face_recognition_logs': " . $conn->error . "\n";
}

echo "\nFace recognition tables creation completed!\n";

$conn->close();
?>