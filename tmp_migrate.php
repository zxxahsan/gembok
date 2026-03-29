<?php
require_once 'includes/db.php';
$pdo = getDB();

try {
    echo "Migrating sales_profile_prices...\n";
    $pdo->exec("ALTER TABLE sales_profile_prices ADD COLUMN validity VARCHAR(50) DEFAULT NULL AFTER selling_price");
    echo "Done.\n";
} catch (Exception $e) {
    echo "Error or already exists: " . $e->getMessage() . "\n";
}

try {
    echo "Migrating hotspot_sales...\n";
    $pdo->exec("ALTER TABLE hotspot_sales ADD COLUMN validity VARCHAR(50) DEFAULT NULL AFTER profile");
    echo "Done.\n";
} catch (Exception $e) {
    echo "Error or already exists: " . $e->getMessage() . "\n";
}
