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

$allActiveSessions = [];
$allOnlineUsers = [];
$routers = getAllRouters();

foreach ($routers as $r) {
    if ($mk = getMikrotikConnection($r['id'])) {
        // Find accurately connected users!
        mikrotikWrite($mk, '/ppp/active/print');
        mikrotikWrite($mk, '=.proplist=name');
        $pppActive = mikrotikRead($mk);
        if (!empty($pppActive) && !isset($pppActive['!trap'])) {
            foreach ($pppActive as $session) {
                if (isset($session['name'])) {
                    $u = strtolower(trim($session['name']));
                    $allOnlineUsers[$r['id']][$u] = true;
                }
            }
        }
        
        // Find byte traffic for those connected dynamically!
        mikrotikWrite($mk, '/interface/print');
        mikrotikWrite($mk, '=.proplist=name,rx-byte,tx-byte');
        $interfaces = mikrotikRead($mk);
        
        $activeSessions = [];
        if (!empty($interfaces) && !isset($interfaces['!trap'])) {
            foreach ($interfaces as $intf) {
                if (isset($intf['name'])) {
                    $name = strtolower(trim($intf['name']));
                    if (strpos($name, '<pppoe-') === 0) {
                        $username = substr($name, 7, -1);
                        $activeSessions[$username] = [
                            'rx' => (float)($intf['rx-byte'] ?? 0),
                            'tx' => (float)($intf['tx-byte'] ?? 0)
                        ];
                    }
                }
            }
        }
        $allActiveSessions[$r['id']] = $activeSessions;
    }
}

$data = [];
foreach ($customers as $c) {
    if (empty($c['pppoe_username'])) continue;
    
    $userOrig = $c['pppoe_username'];
    $user = strtolower(trim($c['pppoe_username']));
    $rid = $c['router_id'];
    
    $liveRx = 0;
    $liveTx = 0;
    $isOnline = false;
    
    // Check accurately using PPP Active tracking bridging offline issues
    if ($rid && isset($allOnlineUsers[$rid]) && isset($allOnlineUsers[$rid][$user])) {
        $isOnline = true;
    } else {
        foreach ($allOnlineUsers as $routerId => $users) {
            if (isset($users[$user])) {
                $isOnline = true;
                $rid = $routerId; // Update tracking matrix overriding mismatched arrays securely
                break;
            }
        }
    }
    
    // Map usage statistics mirroring verified active interfaces
    if ($isOnline && isset($allActiveSessions[$rid]) && isset($allActiveSessions[$rid][$user])) {
        $liveRx = $allActiveSessions[$rid][$user]['rx'];
        $liveTx = $allActiveSessions[$rid][$user]['tx'];
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
