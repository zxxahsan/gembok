<?php
header('Content-Type: text/plain');

require_once '../includes/auth.php';
require_once '../includes/functions.php';

$phone = $_GET['phone'] ?? $_GET['username'] ?? '';

if (empty($phone)) {
    echo "ERROR: Please provide ?phone=XXX\n";
    exit;
}

$device = genieacsGetDevice($phone);

echo "Dump of HostsData:\n";
$hostsRaw = genieacsGetValue($device, 'InternetGatewayDevice.LANDevice.1.Hosts.Host');
if ($hostsRaw) {
    echo "FOUND Hosts.Host! Keys inside it:\n";
    foreach($hostsRaw as $key => $hostData) {
        if (!is_numeric($key)) continue;
        echo "  Index [$key]:\n";
        foreach($hostData as $k => $v) {
            echo "    $k => " . (is_array($v) ? ($v['_value'] ?? 'array') : $v) . "\n";
        }
        echo "\n";
    }
} else {
    echo "Hosts.Host NOT FOUND.\n";
}

echo "\n--------------------------------------------------------------\n";

$assocRaw = genieacsGetValue($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.AssociatedDevice');
if ($assocRaw) {
    echo "FOUND AssociatedDevice! Keys inside it:\n";
    foreach($assocRaw as $key => $hostData) {
        if (!is_numeric($key)) continue;
        echo "  Index [$key]:\n";
        foreach($hostData as $k => $v) {
            echo "    $k => " . (is_array($v) ? ($v['_value'] ?? 'array') : $v) . "\n";
        }
        echo "\n";
    }
} else {
    echo "AssociatedDevice NOT FOUND.\n";
}
