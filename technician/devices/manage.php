<?php
require_once '../../includes/auth.php';
requireTechnicianLogin();

$pageTitle = 'Manajemen Perangkat';
$tech = $_SESSION['technician'];
$username = $_GET['username'] ?? '';
$serial = $_GET['serial'] ?? '';

if (empty($username) && empty($serial)) {
    redirect('search.php');
}

// 1. Try to find customer in DB
$customer = null;
if (!empty($username)) {
    $customer = fetchOne("SELECT * FROM customers WHERE pppoe_username = ?", [$username]);
} 
if (!$customer && !empty($serial)) {
    // Try both serial number and pppoe_username (because map might pass pppoe_username as serial)
    $customer = fetchOne("SELECT * FROM customers WHERE serial_number = ? OR pppoe_username = ?", [$serial, $serial]);
}

// If customer not found, create a dummy customer object for display if we have device
if (!$customer) {
    // Check if we can proceed with just device lookup
    if (empty($username) && empty($serial)) {
        setFlash('error', 'Data pelanggan tidak ditemukan.');
        redirect('search.php');
    }
    
    // Create placeholder customer
    $customer = [
        'name' => 'Unregistered Device',
        'pppoe_username' => $username ?: $serial,
        'serial_number' => $serial ?: '',
        'address' => 'Alamat tidak diketahui',
        'status' => 'unknown'
    ];
}

// Fetch Device from GenieACS
$device = null;
$error = null;

// A. Try by Serial Number if available
if (!empty($customer['serial_number'])) {
    $device = genieacsGetDevice($customer['serial_number']);
} else if (!empty($serial)) {
    $device = genieacsGetDevice($serial);
}

// B. If not found, try by PPPoE Username
if (!$device && !empty($customer['pppoe_username'])) {
    // Try finding by VirtualParameters.pppoeUsername
    $device = genieacsFindDeviceByPppoe($customer['pppoe_username']);
}

// C. If still not found and we have a username that might be a serial
if (!$device && !empty($username)) {
    $device = genieacsGetDevice($username);
}

// Helper to extract value safely
function getDeviceVal($data, $path) {
    // Use the robust genieacsGetValue from functions.php
    return genieacsGetValue($data, $path);
}

// Parse Data if device found
if ($device) {
    $lastInform = $device['_lastInform'] ?? null;
    $isOnline = $lastInform && (time() - strtotime($lastInform)) < 300;
    
    // Extract Parameters
    // Note: We use the paths that are common in GenieACS for standard ONUs
    // VirtualParameters are often created by presets to normalize data
    
    $rxPower = getDeviceVal($device, 'VirtualParameters.RXPower');
    if ($rxPower === null) {
        // Fallback to standard paths if VirtualParameter is missing
        $rxPower = getDeviceVal($device, 'InternetGatewayDevice.WANDevice.1.X_GponInterafceConfig.RxPower') ?? 
                   getDeviceVal($device, 'Device.Optical.Interface.1.RXPower');
    }

    $temp = getDeviceVal($device, 'VirtualParameters.gettemp') ?? 
            getDeviceVal($device, 'InternetGatewayDevice.DeviceInfo.TemperatureStatus.Temperature') ?? '-';
            
    $uptime = getDeviceVal($device, 'VirtualParameters.getdeviceuptime') ?? 
              getDeviceVal($device, 'InternetGatewayDevice.DeviceInfo.UpTime');
              
    if (is_numeric($uptime)) {
        $days = floor($uptime / 86400);
        $hours = floor(($uptime % 86400) / 3600);
        $minutes = floor(($uptime % 3600) / 60);
        $uptimeStr = "{$days}h {$hours}j {$minutes}m";
    } else {
        $uptimeStr = $uptime ?? '-';
    }

    $ponMode = getDeviceVal($device, 'VirtualParameters.getponmode') ?? '-';
    $sn = $device['_deviceId']['_SerialNumber'] ?? '-';
    $model = getDeviceVal($device, 'InternetGatewayDevice.DeviceInfo.ModelName') ?? '-';
    
    // WiFi
    $ssid = getDeviceVal($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID') ?? '-';
    $wifiPass = getDeviceVal($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase') ?? '***';
    $assocDevices = getDeviceVal($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.TotalAssociations') ?? '0';

    // IP
    $wanIp = getDeviceVal($device, 'VirtualParameters.IPTR069') ?? 
             getDeviceVal($device, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress') ?? '-';

} else {
    $error = "Perangkat tidak ditemukan di GenieACS. Pastikan Serial Number atau Username PPPoE sesuai.";
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage ONT - <?php echo htmlspecialchars($customer['name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #00f5ff;
            --bg-dark: #0a0a12;
            --bg-card: #161628;
            --text-primary: #ffffff;
            --text-secondary: #b0b0c0;
            --success: #00ff88;
            --danger: #ff4757;
            --warning: #ffcc00;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        
        body {
            background: var(--bg-dark);
            color: var(--text-primary);
            padding-bottom: 80px;
        }
        
        .header {
            background: var(--bg-card);
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        
        .back-btn {
            color: var(--text-primary);
            font-size: 1.2rem;
            text-decoration: none;
        }
        
        .container { padding: 20px; }
        
        .card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        .customer-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .customer-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary);
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .badge-success { background: rgba(0, 255, 136, 0.2); color: var(--success); border: 1px solid rgba(0, 255, 136, 0.3); }
        .badge-danger { background: rgba(255, 71, 87, 0.2); color: var(--danger); border: 1px solid rgba(255, 71, 87, 0.3); }
        .badge-warning { background: rgba(255, 204, 0, 0.2); color: var(--warning); border: 1px solid rgba(255, 204, 0, 0.3); }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .info-label { color: var(--text-secondary); font-size: 0.9rem; }
        .info-value { font-weight: 600; }
        
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 1rem;
            transition: 0.2s;
            text-decoration: none;
            margin-bottom: 10px;
        }
        
        .btn-primary { background: var(--primary); color: #000; }
        .btn-danger { background: rgba(255, 71, 87, 0.1); color: var(--danger); border: 1px solid rgba(255, 71, 87, 0.3); }
        .btn-secondary { background: rgba(255,255,255,0.1); color: var(--text-primary); }
        
        .rx-power { font-weight: bold; }
        .rx-good { color: var(--success); }
        .rx-warning { color: var(--warning); }
        .rx-bad { color: var(--danger); }

        .error-msg {
            background: rgba(255, 71, 87, 0.1);
            color: var(--danger);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="search.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <h2>Detail Perangkat</h2>
    </div>

    <div class="container">
        <?php if ($error): ?>
            <div class="error-msg">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
            
            <div class="card">
                <div class="customer-header">
                    <div class="customer-name"><?php echo htmlspecialchars($customer['name']); ?></div>
                    <div style="color: var(--text-secondary); margin-top: 5px;"><?php echo htmlspecialchars($customer['pppoe_username']); ?></div>
                </div>
                <div class="info-item">
                    <span class="info-label">Serial Number (DB)</span>
                    <span class="info-value"><?php echo htmlspecialchars($customer['serial_number'] ?? '-'); ?></span>
                </div>
            </div>

            <a href="search.php" class="btn btn-secondary">Kembali Cari</a>
        <?php else: ?>
            <!-- Status Card -->
            <div class="card" style="text-align: center;">
                <div class="customer-header">
                    <div class="customer-name" style="font-size: 1.5rem; margin-bottom: 10px;"><?php echo htmlspecialchars($customer['name']); ?></div>
                    <div style="color: var(--text-secondary);"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($customer['pppoe_username']); ?></div>
                </div>

                <div style="display: flex; gap: 20px; justify-content: center; margin-top: 30px; margin-bottom: 20px; flex-wrap: wrap;">
                    <!-- Status Koneksi -->
                    <div style="padding: 20px; background: rgba(0,0,0,0.3); border-radius: 12px; width: 160px; border: 1px solid rgba(255,255,255,0.05);">
                        <?php if ($isOnline): ?>
                            <i class="fas fa-globe" style="font-size: 3rem; color: var(--success); margin-bottom: 15px;"></i>
                            <div style="font-weight: bold; font-size: 1.3rem; color: var(--success);">ONLINE</div>
                        <?php else: ?>
                            <i class="fas fa-globe" style="font-size: 3rem; color: var(--danger); margin-bottom: 15px;"></i>
                            <div style="font-weight: bold; font-size: 1.3rem; color: var(--danger);">OFFLINE</div>
                        <?php endif; ?>
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 10px;">Status Jaringan</div>
                    </div>
                    
                    <!-- RX Power -->
                    <div style="padding: 20px; background: rgba(0,0,0,0.3); border-radius: 12px; width: 160px; border: 1px solid rgba(255,255,255,0.05);">
                        <i class="fas fa-broadcast-tower" style="font-size: 3rem; color: var(--neon-cyan); margin-bottom: 15px;"></i>
                        <div style="font-weight: bold; font-size: 1.3rem; color: var(--neon-cyan);">
                            <?php 
                                $rxClass = '';
                                $rxVal = floatval($rxPower);
                                if ($rxVal > -25 && $rxVal < 0) $rxClass = 'color: var(--success);';
                                elseif ($rxVal > -28 && $rxVal <= -25) $rxClass = 'color: var(--warning);';
                                else if ($rxVal <= -28) $rxClass = 'color: var(--danger);';
                            ?>
                            <span style="<?php echo $rxClass; ?>">
                                <?php echo $rxPower ? htmlspecialchars($rxPower) . ' dBm' : '-'; ?>
                            </span>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 10px;">Redaman Laser RX</div>
                    </div>
                </div>
                
                <?php if ($lastInform): ?>
                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 20px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.05);">
                    <i class="fas fa-clock"></i> Sinkronisasi Terakhir: <?php echo date('d M Y H:i', strtotime($lastInform)); ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function rebootDevice() {
            if(confirm('Apakah Anda yakin ingin me-restart perangkat ini? Koneksi pelanggan akan terputus sesaat.')) {
                // Call API to reboot
                const formData = new FormData();
                formData.append('action', 'reboot');
                // We use PPPoE username as ID, API will handle lookup
                formData.append('device_id', '<?php echo $customer['pppoe_username']; ?>');

                fetch('../../api/genieacs.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        alert('Perintah reboot berhasil dikirim!');
                    } else {
                        alert('Gagal: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Terjadi kesalahan koneksi');
                    console.error(error);
                });
            }
        }

        // WiFi Modal Functions
        function openWifiModal() {
            const modal = document.getElementById('wifiModal');
            modal.style.display = 'flex';
        }

        function closeWifiModal() {
            const modal = document.getElementById('wifiModal');
            modal.style.display = 'none';
        }

        function togglePasswordVisibility() {
            const input = document.getElementById('editPassword');
            const icon = document.getElementById('togglePass');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        function saveSsid() {
            const ssid = document.getElementById('editSsid').value;

            if (ssid.length < 3) {
                alert('SSID minimal 3 karakter');
                return;
            }

            if(!confirm('Simpan perubahan SSID? Perangkat mungkin akan reconnect.')) return;

            fetch('../../api/onu_wifi.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    pppoe_username: '<?php echo $customer['pppoe_username']; ?>',
                    ssid: ssid
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('SSID berhasil diperbarui!');
                    location.reload();
                } else {
                    alert('Gagal: ' + data.message);
                }
            })
            .catch(error => {
                alert('Terjadi kesalahan koneksi');
                console.error(error);
            });
        }

        function savePassword() {
            const password = document.getElementById('editPassword').value;

            if (password.length < 8) {
                alert('Password minimal 8 karakter');
                return;
            }

            if(!confirm('Simpan perubahan Password? Perangkat mungkin akan reconnect.')) return;

            fetch('../../api/onu_wifi.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    pppoe_username: '<?php echo $customer['pppoe_username']; ?>',
                    password: password
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('Password berhasil diperbarui!');
                    location.reload();
                } else {
                    alert('Gagal: ' + data.message);
                }
            })
            .catch(error => {
                alert('Terjadi kesalahan koneksi');
                console.error(error);
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('wifiModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
    
    <?php require_once '../includes/bottom_nav.php'; ?>
</body>
</html>
