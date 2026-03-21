<?php
/**
 * Customer Portal Dashboard
 */

require_once '../includes/auth.php';
requireCustomerLogin();

$customerSession = getCurrentCustomer();

// Fetch fresh customer data from database to ensure synchronization
if ($customerSession && isset($customerSession['id'])) {
    $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$customerSession['id']]);
    
    // Update session with fresh data
    if ($customer) {
        $customer['logged_in'] = true;
        $customer['login_time'] = $customerSession['login_time'] ?? time();
        $_SESSION['customer'] = $customer;
    } else {
        $customer = $customerSession;
    }
} else {
    $customer = $customerSession;
}

// Safely get the package
$package = null;
if (isset($customer['package_id']) && !empty($customer['package_id'])) {
    $package = fetchOne("SELECT * FROM packages WHERE id = ?", [$customer['package_id']]);
}

$pageTitle = 'Dashboard Pelanggan';

ob_start();
?>

<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">

    <!-- Welcome Header -->
    <div style="margin-bottom: 30px;">
        <h2 style="color: var(--text-primary); margin-bottom: 5px;">Selamat Datang, <?php echo htmlspecialchars($customer['name']); ?>!</h2>
        <p style="color: var(--text-secondary);">Kelola layanan internet Anda dari portal ini.</p>
    </div>

    <!-- Package Info -->
    <div class="card" style="margin-bottom: 30px;">
        <h3 style="margin-bottom: 15px; color: var(--neon-cyan);">
            <i class="fas fa-box"></i> Paket Layanan Internet
        </h3>
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="font-size: 1.8rem; font-weight: 700; margin-bottom: 8px;">
                    <?php echo htmlspecialchars($package['name'] ?? 'Tanpa Paket'); ?>
                </h2>
                <p style="color: var(--neon-green); font-size: 1.4rem; font-weight: 600;">
                    <?php echo formatCurrency($package['price'] ?? 0); ?> 
                    <span style="font-size: 0.9rem; color: var(--text-secondary); font-weight: normal;">/ bulan</span>
                </p>
            </div>
            <div style="text-align: right;">
                <div style="margin-bottom: 10px;">
                    <span style="color: var(--text-secondary); font-size: 0.9rem; display: block; margin-bottom: 5px;">Status Berlangganan:</span>
                    <?php if (isset($customer['status']) && $customer['status'] === 'active'): ?>
                        <span class="badge badge-success" style="font-size: 1.1rem; padding: 8px 16px;">Aktif</span>
                    <?php else: ?>
                        <span class="badge badge-warning" style="font-size: 1.1rem; padding: 8px 16px;">Isolir</span>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($customer['isolation_date'])): ?>
                    <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 10px;">
                        <i class="fas fa-calendar-alt"></i> Tanggal Isolir: Tanggal <?php echo $customer['isolation_date']; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Account Settings -->
    <div class="card" style="margin-bottom: 30px;">
        <h3 style="margin-bottom: 15px; color: var(--neon-cyan);">
            <i class="fas fa-user-cog"></i> Pengaturan Sandi Portal
        </h3>
        <p style="color: var(--text-secondary); margin-bottom: 15px;">
            Ubah kata sandi untuk login ke portal pelanggan.
        </p>
        
        <div class="form-group" style="max-width: 400px;">
            <label class="form-label">Password Baru</label>
            <input type="password" id="newPassword" class="form-control" placeholder="Minimal 6 karakter" style="margin-bottom: 15px;">
            <button class="btn btn-warning" onclick="changePortalPassword()">
                <i class="fas fa-key"></i> Simpan Password
            </button>
        </div>
    </div>

</div>

<!-- Alert Modal -->
<div id="alertModal" style="display: none; position: fixed; top: 20px; right: 20px; z-index: 3000;">
    <div class="alert" id="alertContent"></div>
</div>

<script>
function showAlert(message, type = 'success') {
    const modal = document.getElementById('alertModal');
    const content = document.getElementById('alertContent');
    
    content.className = 'alert alert-' + type;
    content.innerHTML = '<i class="' + (type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle') + '"></i> ' + message;
    
    modal.style.display = 'block';
    
    setTimeout(() => {
        modal.style.display = 'none';
    }, 5000);
}

function changePortalPassword() {
    const newPassword = document.getElementById('newPassword').value;
    
    if (newPassword.length < 6) {
        showAlert('Password portal minimal 6 karakter', 'error');
        return;
    }
    
    fetch('<?php echo APP_URL; ?>/api/customer_portal.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            action: 'change_password',
            new_password: newPassword 
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Password portal berhasil diperbarui. Silakan gunakan password baru pada login berikutnya.');
            document.getElementById('newPassword').value = '';
        } else {
            showAlert('Gagal memperbarui password: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showAlert('Terjadi kesalahan sistem.', 'error');
    });
}
</script>

<?php
$content = ob_get_clean();
require_once '../includes/customer_layout.php';
