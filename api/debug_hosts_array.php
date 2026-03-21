<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

require_once '../includes/auth.php';
require_once '../includes/functions.php';

$phone = $_GET['phone'] ?? $_GET['username'] ?? '';

if (empty($phone)) {
    echo "ERROR: Please provide ?phone=XXX\n";
    exit;
}

$device = genieacsGetDevice($phone);
echo "Device ID Tracker: " . ($device['_id'] ?? 'Not Found') . "\n";

$path = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.AssociatedDevice';
$keys = explode('.', $path);
$current = $device;

echo "\n--- Traversal Trace ---\n";
foreach ($keys as $key) {
    echo "Attempting to access key: '$key'\n";
    if (!isset($current[$key])) {
        echo "  [FAIL] Key '$key' is NOT SET in the current array!\n";
        echo "  Available keys at this level are: " . implode(", ", array_keys($current)) . "\n";
        exit;
    }
    echo "  [SUCCESS] Key '$key' accessed.\n";
    $current = $current[$key];
}

echo "\nTraversal successful! Array structure is:\n";
print_r(array_keys($current));
