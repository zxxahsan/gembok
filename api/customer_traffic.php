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
    $dynamicInterface = mikrotikGetInterfaceBytesByUsername($pppoeUsername);
    
    if ($dynamicInterface) {
        echo json_encode([
            'success' => true, 
            'rx_bytes' => (float)($dynamicInterface['rx-byte'] ?? 0),
            'tx_bytes' => (float)($dynamicInterface['tx-byte'] ?? 0),
            'timestamp_ms' => microtime(true)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Sesi PPPoE tidak ditemukan (Offline)', 'rx' => 0, 'tx' => 0]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'rx' => 0, 'tx' => 0]);
}
