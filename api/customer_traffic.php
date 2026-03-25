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
        
        // AUTO-SAVE LOGIC: Crucial fallback for Local Server environments where CRON daemon is missing!
        // We calculate Session drops intrinsically here to ensure usage_bytes gracefully aggregates over disconnects securely.
        $lastRxTracked = (float)($customer['usage_last_rx'] ?? 0);
        $lastTxTracked = (float)($customer['usage_last_tx'] ?? 0);
        
        $pdo = getDB();
        if ($liveRx < $lastRxTracked || $liveTx < $lastTxTracked) {
            // Router reconnected. Move last metrics to the History base!
            $stmt = $pdo->prepare("UPDATE customers SET usage_bytes_in = usage_bytes_in + ?, usage_bytes_out = usage_bytes_out + ?, usage_last_rx = ?, usage_last_tx = ? WHERE id = ?");
            $stmt->execute([$lastRxTracked, $lastTxTracked, $liveRx, $liveTx, $customer['id']]);
        } else {
            // Session persists linearly, override the last active tick.
            $stmt = $pdo->prepare("UPDATE customers SET usage_last_rx = ?, usage_last_tx = ? WHERE id = ?");
            $stmt->execute([$liveRx, $liveTx, $customer['id']]);
        }
        
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
