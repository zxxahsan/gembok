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
$assocObj = genieacsGetValue($device, $path);

if (is_array($assocObj) && isset($assocObj['1'])) {
    echo "\nFound AssociatedDevice! Here are the keys inside Client #1:\n";
    $client = $assocObj['1'];
    
    foreach ($client as $k => $v) {
        $valStr = is_array($v) ? ($v['_value'] ?? 'array') : $v;
        echo "  - $k => " . $valStr . "\n";
    }
} else {
    echo "Could not find Client #1 inside AssociatedDevice!\n";
}

$hostsPath = 'InternetGatewayDevice.LANDevice.1.Hosts.Host';
$hostsObj = genieacsGetValue($device, $hostsPath);

if (is_array($hostsObj) && isset($hostsObj['1'])) {
    echo "\nFound Hosts.Host! Here are the keys inside Ethernet Client #1:\n";
    $hclient = $hostsObj['1'];
    
    foreach ($hclient as $k => $v) {
        $valStr = is_array($v) ? ($v['_value'] ?? 'array') : $v;
        echo "  - $k => " . $valStr . "\n";
    }
}
