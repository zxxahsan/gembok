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
        $liveRx = (float)($dynamicInterface['rx-byte'] ?? 0);
        $liveTx = (float)($dynamicInterface['tx-byte'] ?? 0);
        
        // Single Source of Truth architecture: We do NOT write to Database here anymore.
        // We defer all accumulation writing exclusively to cron/scheduler.php every 1 Minute,
        // preventing dual-racing overlapping bugs when the dashboard rests open.
        
        echo json_encode([
            'success' => true, 
            'rx_bytes' => $liveRx,
            'tx_bytes' => $liveTx,
            'timestamp_ms' => microtime(true)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Sesi PPPoE tidak ditemukan (Offline)', 'rx' => 0, 'tx' => 0]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'rx' => 0, 'tx' => 0]);
}
