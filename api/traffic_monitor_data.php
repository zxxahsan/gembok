<?php
require_once '../includes/auth.php';

if (!isAdminLoggedIn() && !isTechnicianLoggedIn() && !isSalesLoggedIn()) {
    echo json_encode(['data' => []]);
    exit;
}

header('Content-Type: application/json');
require_once '../includes/functions.php';
require_once '../includes/mikrotik_api.php';

$customers = fetchAll("SELECT id, name, pppoe_username, usage_bytes_in, usage_bytes_out, usage_last_rx, usage_last_tx, status FROM customers ORDER BY name ASC");

$activeSessions = [];
$routers = getAllRouters();

foreach ($routers as $r) {
    if ($mk = getMikrotikConnection($r['id'])) {
        mikrotikWrite($mk, '/interface/print');
        mikrotikWrite($mk, '=.proplist=name,rx-byte,tx-byte');
        $interfaces = mikrotikRead($mk);
        
        if (!empty($interfaces) && !isset($interfaces['!trap'])) {
            foreach ($interfaces as $intf) {
                if (isset($intf['name']) && strpos($intf['name'], '<pppoe-') === 0) {
                    $username = trim(substr($intf['name'], 7, -1));
                    $activeSessions[$username] = [
                        'rx' => (float)($intf['rx-byte'] ?? 0),
                        'tx' => (float)($intf['tx-byte'] ?? 0)
                    ];
                }
            }
        }
    }
}

$data = [];
foreach ($customers as $c) {
    if (empty($c['pppoe_username'])) continue;
    
    $user = $c['pppoe_username'];
    $liveRx = 0;
    $liveTx = 0;
    $isOnline = false;
    
    if (isset($activeSessions[$user])) {
        $isOnline = true;
        $liveRx = $activeSessions[$user]['rx'];
        $liveTx = $activeSessions[$user]['tx'];
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
        'username' => htmlspecialchars($user),
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
