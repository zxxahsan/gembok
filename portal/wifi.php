<?php
/**
 * WiFi & Router Settings Page - Customer Portal
 */

require_once '../includes/auth.php';
requireCustomerLogin();

$customerSession = getCurrentCustomer();

// Fetch fresh customer data
if ($customerSession && isset($customerSession['id'])) {
    $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$customerSession['id']]);
} else {
    $customer = $customerSession;
}

$pageTitle = 'WiFi & Router';

// Get ONU data from GenieACS
$onuData = null;
$onuOnline = false;
$onuSignal = 'N/A';
$onuDevices = '-';

$customerDevice = genieacsFindDeviceByPppoe($customer['pppoe_username']);

if ($customerDevice) {
    $deviceId = $customerDevice['_id'] ?? $customerDevice['_deviceId']['_SerialNumber'] ?? $customer['pppoe_username'];
    $onuData = genieacsGetDeviceInfo($deviceId);
    
    if ($onuData && isset($onuData['status'])) {
        $onuOnline = ($onuData['status'] === 'online');
    }
    
    $rxPowerFromDevice = genieacsGetValue($customerDevice, 'VirtualParameters.RXPower');
    if ($rxPowerFromDevice !== null) {
        $onuSignal = $rxPowerFromDevice;
    } elseif ($onuData && isset($onuData['rx_power'])) {
        $onuSignal = is_array($onuData['rx_power']) ? ($onuData['rx_power']['_value'] ?? 'N/A') : $onuData['rx_power'];
    }
    if (is_array($onuSignal)) {
        $onuSignal = $onuSignal['_value'] ?? $onuSignal['value'] ?? 'N/A';
    }
    
    $rawDevices = genieacsGetValue($customerDevice, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.TotalAssociations') ?? ($onuData['total_associations'] ?? '-');
    if (is_array($rawDevices)) {
        $rawDevices = $rawDevices['_value'] ?? $rawDevices['value'] ?? '-';
    }
    $onuDevices = is_numeric($rawDevices) ? (int)$rawDevices : '-';
}

// Handle POST actions for WiFi & Reboot
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlash('error', 'Sesi tidak valid.');
        redirect('wifi.php');
    }
    
    $action = $_POST['action'] ?? '';
    
    if (!$customerDevice || !$onuOnline) {
        setFlash('error', 'Perangkat sedang offline. Tidak dapat melakukan pengaturan.');
        redirect('wifi.php');
    }

    $deviceId = $customerDevice['_id'];

    if ($action === 'change_ssid') {
        $newSsid = trim($_POST['new_ssid'] ?? '');
        if (empty($newSsid)) {
            setFlash('error', 'Nama WiFi tidak boleh kosong');
        } else {
            if (genieacsSetParameter($deviceId, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID', $newSsid)) {
                setFlash('success', 'Nama WiFi berhasil diubah. Router mungkin perlu restart.');
                logActivity('CUSTOMER_CHANGE_SSID', "Customer {$customer['name']} changed SSID");
            } else {
                setFlash('error', 'Gagal mengubah nama WiFi');
            }
        }
        redirect('wifi.php');
        
    } elseif ($action === 'change_wifi_pass') {
        $newPass = trim($_POST['new_wifi_pass'] ?? '');
        if (strlen($newPass) < 8) {
            setFlash('error', 'Password WiFi minimal 8 karakter');
        } else {
            if (genieacsSetParameter($deviceId, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey', $newPass)) {
                setFlash('success', 'Password WiFi berhasil diubah. Router mungkin perlu restart.');
                logActivity('CUSTOMER_CHANGE_WIFI_PASS', "Customer {$customer['name']} changed WiFi password");
            } else {
                setFlash('error', 'Gagal mengubah password WiFi');
            }
        }
        redirect('wifi.php');
        
    } elseif ($action === 'reboot_router') {
        if (genieacsReboot($deviceId)) {
            setFlash('success', 'Perintah mulai ulang (reboot) berhasil dikirim. Perangkat akan merestart dalam 1-2 menit.');
            logActivity('CUSTOMER_REBOOT_ROUTER', "Customer {$customer['name']} initiated router reboot");
        } else {
            setFlash('error', 'Gagal mengirim perintah restart ke router.');
        }
        redirect('wifi.php');
    }
}

ob_start();
?>

<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">

    <!-- ONU Info -->
    <div class="card">
        <h3 style="margin-bottom: 15px; color: var(--neon-cyan);">
            <i class="fas fa-satellite-dish"></i> Informasi Router (ONU)
        </h3>
        
        <?php if ($onuData): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div>
                    <p style="color: var(--text-secondary); margin-bottom: 5px;">Username PPPoE</p>
                    <p><code><?php echo htmlspecialchars($customer['pppoe_username'] ?? '-'); ?></code></p>
                </div>
                <div>
                    <p style="color: var(--text-secondary); margin-bottom: 5px;">Perangkat Terhubung</p>
                    <p><?php echo htmlspecialchars($onuDevices); ?> Device</p>
                </div>
                <div>
                    <p style="color: var(--text-secondary); margin-bottom: 5px;">Status</p>
                    <p>
                        <?php if ($onuOnline): ?>
                            <span class="badge badge-success">Online</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Offline</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <p style="color: var(--text-secondary); margin-bottom: 5px;">Signal</p>
                    <p><?php echo $onuSignal; ?> dBm</p>
                </div>
            </div>
        <?php else: ?>
            <p style="color: var(--text-muted);">
                <i class="fas fa-info-circle"></i> Data ONU tidak tersedia. Pastikan Router terhubung.
            </p>
        <?php endif; ?>
    </div>

    <!-- WiFi Settings -->
    <?php 
    $isCustomerDeviceOnline = $customerDevice && $onuOnline;
    ?>
    
    <?php if ($isCustomerDeviceOnline && $customerDevice): ?>
    <div class="card" id="wifi-settings">
        <h3 style="margin-bottom: 15px; color: var(--neon-cyan);">
            <i class="fas fa-wifi"></i> Pengaturan WiFi
        </h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            <div style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div class="form-group">
                        <label class="form-label">SSID WiFi Saat Ini</label>
                        <p style="font-size: 1.2rem; font-weight: 600; padding: 10px; background: rgba(0, 245, 255, 0.05); border-radius: 8px; border: 1px solid rgba(0, 245, 255, 0.2);">
                            <i class="fas fa-signal" style="color: var(--neon-cyan); margin-right: 10px;"></i>
                            <?php 
                                $currentSsid = genieacsGetValue($customerDevice, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID');
                                echo htmlspecialchars(is_array($currentSsid) ? ($currentSsid['_value'] ?? 'Unknown') : ($currentSsid ?? 'Unknown')); 
                            ?>
                        </p>
                    </div>
                </div>
                <button type="button" class="btn btn-primary" onclick="openModal('modalChangeSsid')">
                    <i class="fas fa-edit"></i> Ubah Nama WiFi
                </button>
            </div>
            
            <div style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div class="form-group">
                        <label class="form-label">Password WiFi Saat Ini</label>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px; background: rgba(0, 245, 255, 0.05); border-radius: 8px; border: 1px solid rgba(0, 245, 255, 0.2);">
                            <div style="display: flex; align-items: center; width: 100%;">
                                <i class="fas fa-key" style="color: var(--neon-cyan); margin-right: 10px;"></i>
                                <?php 
                                    $currentPass = genieacsGetValue($customerDevice, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey');
                                    $passVal = htmlspecialchars(is_array($currentPass) ? ($currentPass['_value'] ?? '') : ($currentPass ?? '')); 
                                ?>
                                <input type="password" id="currentWifiPass" value="<?php echo $passVal; ?>" readonly style="background: transparent; border: none; color: var(--text-primary); font-size: 1.2rem; font-weight: 600; width: 100%; outline: none;">
                            </div>
                            <button type="button" onclick="togglePasswordVisibility('currentWifiPass', this)" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; margin-left: 10px;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary" onclick="openModal('modalChangeWifiPass')">
                    <i class="fas fa-lock"></i> Ubah Password WiFi
                </button>
            </div>
        </div>
    </div>
    
    <!-- Reboot Router -->
    <div class="card">
        <h3 style="margin-bottom: 15px; color: var(--neon-orange);">
            <i class="fas fa-power-off"></i> Mulai Ulang Router
        </h3>
        <p style="color: var(--text-secondary); margin-bottom: 15px;">
            Jika koneksi internet terasa lambat atau bermasalah, Anda dapat mencoba me-restart router WiFi dari sini tanpa perlu mematikan saklar listrik. Proses restart memakan waktu sekitar 1-2 menit.
        </p>
        <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin me-restart router WiFi? Internet akan terputus selama proses restart.');">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="action" value="reboot_router">
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-sync-alt"></i> Restart Router Sekarang
            </button>
        </form>
    </div>
    
    <?php else: ?>
    <!-- Device Offline Message -->
    <?php if ($customerDevice): ?>
    <div class="card" style="border-color: rgba(255, 71, 87, 0.3);">
        <h3 style="margin-bottom: 15px; color: var(--neon-red);">
            <i class="fas fa-exclamation-triangle"></i> Perangkat Offline
        </h3>
        <p style="color: var(--text-secondary);">
            Router Anda saat ini tidak terhubung ke sistem kami. Pengaturan WiFi dan fitur Restart Router tidak dapat digunakan saat ini. Pastikan router Anda dalam keadaan menyala.
        </p>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>

<!-- Modals -->
<div id="modalChangeSsid" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: var(--bg-card); width: 400px; max-width: 90%; padding: 25px; border-radius: 12px; border: 1px solid var(--border-color);">
        <h3 style="color: var(--neon-cyan); margin-bottom: 20px;">Ubah Nama WiFi</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="action" value="change_ssid">
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: var(--text-secondary);">Nama WiFi Baru (SSID)</label>
                <input type="text" name="new_ssid" class="form-control" style="width: 100%; padding: 10px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); color: white; border-radius: 6px;" required>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalChangeSsid')" style="padding: 8px 15px; background: transparent; border: 1px solid var(--border-color); color: white; border-radius: 6px; cursor: pointer;">Batal</button>
                <button type="submit" class="btn btn-primary" style="padding: 8px 15px; background: var(--gradient-primary); border: none; color: white; border-radius: 6px; cursor: pointer;">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div id="modalChangeWifiPass" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: var(--bg-card); width: 400px; max-width: 90%; padding: 25px; border-radius: 12px; border: 1px solid var(--border-color);">
        <h3 style="color: var(--neon-cyan); margin-bottom: 20px;">Ubah Password WiFi</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="action" value="change_wifi_pass">
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: var(--text-secondary);">Password WiFi Baru (Minimal 8 karakter)</label>
                <input type="password" name="new_wifi_pass" class="form-control" minlength="8" style="width: 100%; padding: 10px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); color: white; border-radius: 6px;" required>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalChangeWifiPass')" style="padding: 8px 15px; background: transparent; border: 1px solid var(--border-color); color: white; border-radius: 6px; cursor: pointer;">Batal</button>
                <button type="submit" class="btn btn-primary" style="padding: 8px 15px; background: var(--gradient-primary); border: none; color: white; border-radius: 6px; cursor: pointer;">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
function togglePasswordVisibility(inputId, btnElement) {
    const input = document.getElementById(inputId);
    const icon = btnElement.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<?php
$content = ob_get_clean();
require_once '../includes/customer_layout.php';
