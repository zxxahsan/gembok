<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mikrotik_api.php';

$phone = $_GET['phone'] ?? $_GET['username'] ?? '';

if (empty($phone)) {
    echo "ERROR: Please provide ?phone=XXX\n";
    exit;
}

$customer = fetchOne("SELECT * FROM customers WHERE phone = ? OR pppoe_username = ?", [$phone, $phone]);

if (!$customer) {
    echo "CUSTOMER NOT FOUND in DB.\n";
    exit;
}

echo "CUSTOMER FOUND: " . $customer['name'] . "\n";
$pppoeUser = $customer['pppoe_username'] ?? '';
echo "PPPOE USERNAME: '$pppoeUser'\n\n";

if (!empty($pppoeUser)) {
    echo "Testing MikroTik Connection...\n";
    $socket = getMikrotikConnection();
    if (!$socket) {
        echo "FAILED TO CONNECT TO MIKROTIK (or login failed).\n";
    } else {
        echo "MikroTik Socket Connected.\n";
        echo "Calling mikrotikGetActiveSessionByUsername()...\n";
        
        $session = mikrotikGetActiveSessionByUsername($pppoeUser);
        if ($session) {
            echo "SESSION FOUND:\n";
            print_r($session);
        } else {
            echo "NO ACTIVE SESSION FOUND FOR '$pppoeUser'.\n";
            echo "Let's run a raw query unconditionally to see what we get...\n";
            $allSessions = mikrotikGetActiveSessions();
            echo "Total active PPPoE sessions generally: " . count($allSessions) . "\n";
            if (count($allSessions) > 0) {
                echo "Here is the first session found as an example:\n";
                print_r($allSessions[0]);
            }
        }
    }
}

echo "\n====================\nTesting Connected Devices\n";
$customerDevice = genieacsGetDevice($phone);
echo "Genie ACS Device ID: " . ($customerDevice['_id'] ?? 'Not Found') . "\n";

if ($customerDevice) {
    echo "\nRaw Total Associations (From Cache):\n";
    $rawDevices = genieacsGetValue($customerDevice, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.TotalAssociations');
    $val = is_array($rawDevices) ? ($rawDevices['_value'] ?? 'array_no_val') : $rawDevices;
    echo "TotalAssociations: " . $val . "\n";
    
    echo "\n\nChecking AssociatedDevice keys...\n";
    $possibleTrees = [
        'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.AssociatedDevice',
        'InternetGatewayDevice.LANDevice.1.Hosts.Host',
        'Device.Hosts.Host',
        'Device.WiFi.AccessPoint.1.AssociatedDevice'
    ];
    
    foreach ($possibleTrees as $treePath) {
        $treeData = genieacsGetValue($customerDevice, $treePath);
        if (is_array($treeData)) {
            echo "\n--- FOUND ARRAY AT: $treePath ---\n";
            foreach($treeData as $k => $host) {
                if (!is_numeric($k)) continue;
                echo "Host Index $k:\n";
                foreach($host as $hKey => $hVal) {
                    $valStr = is_array($hVal) ? ($hVal['_value'] ?? 'arr') : $hVal;
                    echo "  - $hKey => $valStr\n";
                }
                break; // Just dump first
            }
        }
    }
}
