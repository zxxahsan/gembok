<?php
/**
 * API: Fetch Live PPPoE Traffic for Customer Dashboard
 */
require_once '../includes/auth.php';
requireCustomerLogin();
require_once '../includes/mikrotik_api.php';

header('Content-Type: application/json');

$customerSession = getCurrentCustomer();
$customer = fetchOne("SELECT pppoe_username FROM customers WHERE id = ?", [$customerSession['id']]);
$pppoeUsername = $customer['pppoe_username'] ?? '';

if (empty($pppoeUsername)) {
    echo json_encode(['success' => false, 'message' => 'PPPoE Username tidak diatur', 'rx' => 0, 'tx' => 0]);
    exit;
}

try {
    $mk = getMikrotikConnection();
    if (!$mk) {
        echo json_encode(['success' => false, 'message' => 'Koneksi ke Router Gagal', 'rx' => 0, 'tx' => 0]);
        exit;
    }

    $interfaceName = "<pppoe-" . $pppoeUsername . ">";
    
    mikrotikWrite($mk, '/interface/monitor-traffic');
    mikrotikWrite($mk, '=interface=' . $interfaceName);
    mikrotikWrite($mk, '=once=');
    $traffic = mikrotikRead($mk);
    
    if (!empty($traffic) && !isset($traffic['!trap'])) {
        // RouterOS maps TX and RX from the router's perspective. 
        // For the customer, Router TX -> Download, Router RX -> Upload.
        $downloadBits = (int)($traffic[0]['tx-bits-per-second'] ?? 0);
        $uploadBits = (int)($traffic[0]['rx-bits-per-second'] ?? 0);
        
        echo json_encode([
            'success' => true, 
            'download_bps' => $downloadBits, 
            'upload_bps' => $uploadBits
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'PPPoE sedang terputus (Offline)', 'rx' => 0, 'tx' => 0]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'rx' => 0, 'tx' => 0]);
}
