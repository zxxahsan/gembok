<?php
require_once 'includes/db.php';
$pdo = getDB();

try {
    echo "Updating existing customer portal passwords to plain-text '1234'...\n";
    $pdo->exec("UPDATE customers SET portal_password = '1234'");
    echo "Done.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
