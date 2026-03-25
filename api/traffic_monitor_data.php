<?php
require_once '../includes/auth.php';

if (!isAdminLoggedIn() && !isTechnicianLoggedIn() && !isSalesLoggedIn()) {
    echo json_encode(['data' => []]);
    exit;
}

header('Content-Type: application/json');
require_once '../includes/functions.php';
require_once '../includes/mikrotik_api.php';

$customers = fetchAll("SELECT id, name, pppoe_username, usage_bytes_in, usage_bytes_out, usage_last_rx, usage_last_tx, status, router_id FROM customers ORDER BY name ASC");

// Strip massive multi-router bulk arrays, query RouterOS natively matching the 100% secure Customer Portal logic!
$apiRouters = getAllRouters();
$data = [];

foreach ($customers as $c) {
    if (empty($c['pppoe_username'])) continue;
    
    $userOrig = $c['pppoe_username'];
    $user = trim($c['pppoe_username']); // Preserve exact case for Mikrotik query
    $rid = $c['router_id'];
    
    $liveRx = 0;
    $liveTx = 0;
    $isOnline = false;
    
    $dynamicInterface = mikrotikGetInterfaceBytesByUsername($user, $rid);
    
    // Explicit cross-router traversal overcoming missing local `router_id` matrices natively
    if (!$dynamicInterface) {
        foreach ($apiRouters as $r) {
            if ($r['id'] == $rid) continue;
            $dynamicInterface = mikrotikGetInterfaceBytesByUsername($user, $r['id']);
            if ($dynamicInterface) {
                $rid = $r['id']; // Update tracker
                break;
            }
        }
    }
    
    // Evaluate Usage Statistics bridging verified native interface metrics
    if ($dynamicInterface) {
        $isOnline = true;
        $liveRx = (float)($dynamicInterface['rx-byte'] ?? 0);
        $liveTx = (float)($dynamicInterface['tx-byte'] ?? 0);
    }
    
    $dbRx = (float)($c['usage_bytes_in'] ?? 0);
    $dbTx = (float)($c['usage_bytes_out'] ?? 0);
    $lastRx = (float)($c['usage_last_rx'] ?? 0);
    $lastTx = (float)($c['usage_last_tx'] ?? 0);
    
    $activeRx = max($liveRx, $lastRx);
    $activeTx = max($liveTx, $lastTx);
    
    $totalRx = $dbRx + $activeRx; // Total Download (Bytes-In from Router)
    $totalTx = $dbTx + $activeTx; // Total Upload (Bytes-Out from Router)
    $grandTotal = $totalRx + $totalTx;
    
    // Status Badge Core
    $statusHtml = $isOnline ? '<span class="status-badge" style="background: rgba(0, 255, 136, 0.1); color: var(--neon-green); border: 1px solid rgba(0, 255, 136, 0.3); padding: 4px 8px; border-radius: 4px; font-size: 0.75rem;"><i class="fas fa-circle"></i> Online</span>' : '<span class="status-badge" style="background: rgba(255, 71, 87, 0.1); color: var(--danger); border: 1px solid rgba(255, 71, 87, 0.3); padding: 4px 8px; border-radius: 4px; font-size: 0.75rem;"><i class="fas fa-times-circle"></i> Offline</span>';

    $data[] = [
        'name' => htmlspecialchars($c['name']),
        'username' => htmlspecialchars($userOrig),
        'status' => $statusHtml,
        'download' => formatBytes($totalRx),
        'upload' => formatBytes($totalTx),
        'total' => formatBytes($grandTotal),
        'raw_total' => $grandTotal
    ];
}

// Sort by highest traffic usage automatically
usort($data, function($a, $b) {
    return $b['raw_total'] <=> $a['raw_total'];
});

// Clear implicit network timeouts masking clean responses
if (ob_get_length()) ob_clean();
echo json_encode(['data' => $data]);
