<?php
/**
 * GenieACS Device Management
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'GenieACS';

// Get devices from GenieACS
$devices = genieacsGetDevices();
$totalDevices = count($devices);

// Calculate stats
$onlineCount = 0;
$offlineCount = 0;
$weakSignalCount = 0;

foreach ($devices as $device) {
    $lastInform = $device['_lastInform'] ?? null;
    if ($lastInform && (time() - strtotime($lastInform)) < 300) {
        $onlineCount++;
    } else {
        $offlineCount++;
    }
    
    $rxPowerValue = genieacsGetValue($device, 'VirtualParameters.RXPower');
    $rxPower = is_numeric($rxPowerValue) ? (float)$rxPowerValue : 0;
    if ($rxPower < -25 && $rxPower !== 0.0) {
        $weakSignalCount++;
    }
}

ob_start();
?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 30px;">
    <div class="stat-card">
        <div class="stat-icon cyan">
            <i class="fas fa-satellite-dish"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $totalDevices; ?></h3>
            <p>Total Device</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $onlineCount; ?></h3>
            <p>Online</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $offlineCount; ?></h3>
            <p>Offline</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $weakSignalCount; ?></h3>
            <p>Signal Lemah</p>
        </div>
    </div>
</div>

<!-- Connection Status -->
<?php $genieacsSettings = getGenieacsSettings(); ?>
<?php if (!empty($genieacsSettings['url'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        GenieACS Connected: <?php echo htmlspecialchars($genieacsSettings['url']); ?>
    </div>
<?php else: ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        GenieACS tidak terkonfigurasi. Silakan setup di Settings.
    </div>
<?php endif; ?>

<!-- Devices Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-server"></i> Daftar Device ONU</h3>
        <div style="display: flex; gap: 10px;">
            <input type="text" id="searchDevice" name="search_device" class="form-control" placeholder="Cari device..." style="width: 250px;" autocomplete="off">
            <button class="btn btn-primary btn-sm" onclick="loadDevices()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>PPPoE Username</th>
                <th>Status</th>
                <th>Signal (dBm)</th>
                <th>SSID</th>
                <th>Last Inform</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($devices)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                        <i class="fas fa-server" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                        Tidak ada device ditemukan atau GenieACS tidak terkoneksi
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($devices as $device): ?>
                <tr>
                    <?php
                    $pppoeUsername = genieacsGetValue($device, 'VirtualParameters.pppoeUsername')
                        ?? genieacsGetValue($device, 'VirtualParameters.PPPoEUsername')
                        ?? genieacsGetValue($device, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username')
                        ?? '-';
                    $serial = genieacsGetValue($device, 'DeviceID.SerialNumber')
                        ?? genieacsGetValue($device, 'InternetGatewayDevice.DeviceInfo.SerialNumber')
                        ?? ($device['_deviceId']['_SerialNumber'] ?? '-');
                    $ssid = genieacsGetValue($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID')
                        ?? genieacsGetValue($device, 'InternetGatewayDevice.LANDevice.1.WiFi.Radio.1.SSID')
                        ?? genieacsGetValue($device, 'Device.WiFi.SSID.1.SSID')
                        ?? '-';
                    $rxPowerDisplay = genieacsGetValue($device, 'VirtualParameters.RXPower') ?? 'N/A';
                    ?>
                    <td>
                        <code style="color: var(--neon-cyan);">
                            <?php echo htmlspecialchars((string)$pppoeUsername); ?>
                        </code>
                    </td>
                    <td>
                        <?php 
                        $lastInform = $device['_lastInform'] ?? null;
                        if ($lastInform && (time() - strtotime($lastInform)) < 300): ?>
                            <span class="badge badge-success">Online</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Offline</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars((string)$rxPowerDisplay); ?></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span><?php echo htmlspecialchars((string)$ssid); ?></span>
                            <button class="btn btn-secondary btn-sm" onclick='openWifiModal(<?php echo json_encode((string)$serial); ?>, <?php echo json_encode((string)$ssid); ?>)'>
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                        </div>
                    </td>
                    <td><?php echo $lastInform ? formatDate($lastInform, 'd M Y H:i') : 'Never'; ?></td>
                    <td>
                        <button class="btn btn-secondary btn-sm" onclick='rebootDevice(<?php echo json_encode((string)$serial); ?>)'>
                            <i class="fas fa-redo"></i> Reboot
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="wifiModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
    <div class="card" style="width: 480px; max-width: 90%; margin: 2rem;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-wifi"></i> Edit WiFi</h3>
            <button onclick="closeWifiModal()" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.25rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">SSID Baru</label>
                <input type="text" id="wifiSsidInput" class="form-control" placeholder="SSID baru">
            </div>
            <button class="btn btn-primary btn-sm" onclick="saveSsidFromModal()">
                <i class="fas fa-save"></i> Simpan SSID
            </button>
            <div class="form-group" style="margin-top: 16px;">
                <label class="form-label">Password Baru</label>
                <input type="password" id="wifiPasswordInput" class="form-control" placeholder="Password baru">
            </div>
            <button class="btn btn-primary btn-sm" onclick="savePasswordFromModal()">
                <i class="fas fa-save"></i> Simpan Password
            </button>
        </div>
    </div>
</div>

<script>
function loadDevices() {
    location.reload();
}

let currentSerial = '';

function rebootDevice(serial) {
    if (!confirm('Reboot device ' + serial + '?')) {
        return;
    }
    
    fetch('<?php echo APP_URL; ?>/api/genieacs.php?action=reboot', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ serial: serial })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Reboot berhasil dijalankan untuk device ' + serial);
        } else {
            alert('Gagal reboot: ' + data.message);
        }
    })
    .catch(error => {
        alert('Terjadi kesalahan: ' + error.message);
    });
}

function openWifiModal(serial, ssid) {
    currentSerial = serial;
    const ssidInput = document.getElementById('wifiSsidInput');
    const passInput = document.getElementById('wifiPasswordInput');
    if (ssidInput) {
        ssidInput.value = ssid || '';
    }
    if (passInput) {
        passInput.value = '';
    }
    const modal = document.getElementById('wifiModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeWifiModal() {
    const modal = document.getElementById('wifiModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function saveSsidFromModal() {
    const input = document.getElementById('wifiSsidInput');
    const ssid = input ? input.value.trim() : '';
    if (!ssid) {
        alert('SSID tidak boleh kosong');
        return;
    }
    
    fetch('<?php echo APP_URL; ?>/api/onu_wifi.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ serial: currentSerial, ssid: ssid })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('SSID berhasil disimpan untuk device ' + currentSerial);
        } else {
            alert('Gagal simpan SSID: ' + data.message);
        }
    })
    .catch(error => {
        alert('Terjadi kesalahan: ' + error.message);
    });
}

function savePasswordFromModal() {
    const input = document.getElementById('wifiPasswordInput');
    const password = input ? input.value.trim() : '';
    if (!password) {
        alert('Password tidak boleh kosong');
        return;
    }
    
    fetch('<?php echo APP_URL; ?>/api/onu_wifi.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ serial: currentSerial, password: password })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Password berhasil disimpan untuk device ' + currentSerial);
            if (input) {
                input.value = '';
            }
        } else {
            alert('Gagal simpan password: ' + data.message);
        }
    })
    .catch(error => {
        alert('Terjadi kesalahan: ' + error.message);
    });
}

document.getElementById('searchDevice').addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('.data-table tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
