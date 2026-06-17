<?php
/**
 * Migration: Add post-session summary columns to `calendario`
 * Run ONCE by visiting this file in the browser, then delete it.
 */
include 'conn.php';

$columns = [
    "sfm_how_did_you_meet"       => "VARCHAR(10)  DEFAULT NULL",
    "sfm_how_did_you_meet_label" => "VARCHAR(100) DEFAULT NULL",
    "sfm_hear_about_us"          => "VARCHAR(10)  DEFAULT NULL",
    "sfm_hear_about_us_label"    => "VARCHAR(150) DEFAULT NULL",
    "sfm_paquete_id"             => "VARCHAR(20)  DEFAULT NULL",
    "sfm_paquete_nombre"         => "VARCHAR(200) DEFAULT NULL",
    "sfm_engagement"             => "TINYINT(1)   DEFAULT NULL",
    "sfm_engagement_label"       => "VARCHAR(50)  DEFAULT NULL",
];

$results = [];
foreach ($columns as $col => $def) {
    // Check if column already exists
    $check = $conn->query("SHOW COLUMNS FROM `calendario` LIKE '$col'");
    if ($check && $check->num_rows === 0) {
        $sql = "ALTER TABLE `calendario` ADD COLUMN `$col` $def";
        if ($conn->query($sql)) {
            $results[] = "✅ Added: $col";
        } else {
            $results[] = "❌ Error adding $col: " . $conn->error;
        }
    } else {
        $results[] = "⏭ Already exists: $col";
    }
}

$conn->close();
echo "<pre>" . implode("\n", $results) . "\n\nDone. Delete this file.</pre>";
?>
