<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireAdminLogin();

$pdo = getDB();
$pageTitle = 'WhatsApp & Template Messages';

// Handle Form Submission for Device Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    try {
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$_POST['wa_bot_url'], 'wa_bot_url']);
        setFlash('success', 'Pengaturan WhatsApp Bot URL berhasil disimpan.');
        header("Location: whatsapp.php");
        exit;
    } catch (PDOException $e) {
        setFlash('error', 'Gagal menyimpan pengaturan: ' . $e->getMessage());
    }
}

// Handle Template Generation / Restitution if table is brand new or dropped
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'migrate_table') {
    $createTable = "CREATE TABLE IF NOT EXISTS whatsapp_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(50) NOT NULL UNIQUE,
        message TEXT NOT NULL,
        variables_hint TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($createTable);
    
    $waTemplates = [
        ['new_customer', "Halo *{customer_name}*,\n\nSelamat datang di Layanan Internet *{app_name}*!\nBerikut detail layanan Anda:\n- Paket: {package_name}\n- Harga: Rp {package_price}/bulan\n- Jatuh Tempo: Tanggal {due_date} tiap bulan\n- Username PPPoE: {pppoe_username}\n- Password: {pppoe_password}\n\nGunakan Portal Pelanggan kami:\n{portal_url}", '{customer_name}, {app_name}, {package_name}, {package_price}, {due_date}, {pppoe_username}, {pppoe_password}, {portal_url}'],
        ['invoice_created', "Halo *{customer_name}*,\n\nTagihan internet periode *{period}* telah terbit.\n\n- Nomor: {invoice_number}\n- Total: Rp {amount}\n- Jatuh Tempo: {due_date}\n\nBayar sekarang via Portal:\n{payment_url}", '{customer_name}, {period}, {invoice_number}, {amount}, {due_date}, {payment_url}, {app_name}'],
        ['invoice_reminder', "⚠️ *PENGINGAT TAGIHAN* ⚠️\n\nHalo *{customer_name}*,\nTagihan internet sebesar *Rp {amount}* akan jatuh tempo pada *{due_date}*.\n\nLakukan pembayaran online di:\n{payment_url}\n\nTerima kasih.", '{customer_name}, {amount}, {due_date}, {payment_url}'],
        ['isolation_warning', "🔴 *KONEKSI TERPUTUS* 🔴\n\nMaaf *{customer_name}*, layanan internet telah diisolir karena tagihan *Rp {amount}* melewati batas ({due_date}).\n\nAktifkan kembali dalam 1 menit dengan pembayaran di:\n{payment_url}", '{customer_name}, {amount}, {due_date}, {payment_url}']
    ];
    foreach ($waTemplates as $watmp) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO whatsapp_templates (type, message, variables_hint) VALUES (?, ?, ?)");
        $stmt->execute($watmp);
    }
    setFlash('success', 'Tabel Template berhasil ditenagai!');
    header("Location: whatsapp.php");
    exit;
}

// Handle Template Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_template') {
    try {
        $stmt = $pdo->prepare("UPDATE whatsapp_templates SET message = ? WHERE type = ?");
        $stmt->execute([$_POST['message'], $_POST['type']]);
        setFlash('success', 'Template ' . $_POST['type'] . ' berhasil diubah.');
        header("Location: whatsapp.php");
        exit;
    } catch (PDOException $e) {
        setFlash('error', 'Gagal merubah template: ' . $e->getMessage());
    }
}

// Fetch Settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('wa_bot_url')");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Fetch Templates (Graceful Fallback if Array doesnt exist yet!)
$templates = [];
try {
    $stmt = $pdo->query("SELECT * FROM whatsapp_templates");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Expected on first load without DB Migration!
    $templates = null;
}

// Map Types to Friendly Names
$typeNames = [
    'new_customer' => 'Pelanggan Baru',
    'invoice_created' => 'Informasi Penagihan Baru',
    'invoice_reminder' => 'Reminder Tagihan (H-3 / H-1)',
    'isolation_warning' => 'Peringatan Isolir (PPPoE Terputus)'
];
?>

<?php require_once '../includes/layout.php'; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-robot"></i> Koneksi API WhatsApp (Node.js)</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save_settings">
            <div class="form-group">
                <label class="form-label">WhatsApp Integrator URL</label>
                <input type="text" name="wa_bot_url" class="form-control" value="<?php echo htmlspecialchars($settings['wa_bot_url'] ?? ''); ?>" placeholder="http://127.0.0.1:3000">
                <small style="color: var(--text-muted); display: block; margin-top: 5px;">Contoh: http://192.168.1.10:3000. Sistem gateway yang mem-parsing cURL PHP ke WA Web JS.</small>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan Pengaturan Koneksi
            </button>
        </form>
    </div>
</div>

<?php if ($templates === null): ?>
<div class="card" style="margin-top: 20px; border-color: var(--neon-orange);">
    <div class="card-header">
        <h3 class="card-title" style="color: var(--neon-orange);"><i class="fas fa-exclamation-triangle"></i> Instalasi Template Engine Diperlukan!</h3>
    </div>
    <div class="card-body">
        <p style="margin-bottom: 15px; color: var(--text-secondary);">Sistem mendeteksi bahwa tabel <code>whatsapp_templates</code> belum tertanam di dalam database Anda. Silakan tekan tombol di bawah ini agar sistem melakukan injeksi database secara otomatis.</p>
        <form method="POST">
            <input type="hidden" name="action" value="migrate_table">
            <button type="submit" class="btn btn-warning"><i class="fas fa-database"></i> Jalankan Migrasi Database WhatsApp Sekarang</button>
        </form>
    </div>
</div>
<?php else: ?>

<div style="margin-top: 30px;">
    <h2><i class="fas fa-comment-dots"></i> Master Template Pesan</h2>
    <p style="color: var(--text-secondary); margin-bottom: 20px;">Sesuaikan format kalimat otomatis yang akan disalurkan oleh sistem Gembok ke nomor WhatsApp Pelanggan.</p>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
        <?php foreach ($templates as $tmpl): ?>
        <div class="card" style="border-top: 3px solid var(--neon-cyan);">
            <div class="card-header" style="justify-content: space-between;">
                <h3 class="card-title"><?php echo htmlspecialchars($typeNames[$tmpl['type']] ?? $tmpl['type']); ?></h3>
                <span class="badge badge-info" style="font-size: 0.7rem;"><?php echo htmlspecialchars($tmpl['type']); ?></span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="save_template">
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($tmpl['type']); ?>">
                    
                    <div class="form-group">
                        <textarea name="message" class="form-control" rows="8" style="font-family: monospace; font-size: 0.9rem; line-height: 1.5;" required><?php echo htmlspecialchars($tmpl['message']); ?></textarea>
                    </div>
                    
                    <div style="margin-bottom: 15px; padding: 10px; background: rgba(0, 245, 255, 0.05); border: 1px solid rgba(0, 245, 255, 0.2); border-radius: 8px;">
                        <span style="display: block; font-size: 0.8rem; font-weight: bold; margin-bottom: 5px; color: var(--neon-cyan);">Variabel yang diizinkan:</span>
                        <code style="font-size: 0.8rem; color: var(--text-secondary); word-wrap: break-word;"><?php echo htmlspecialchars($tmpl['variables_hint']); ?></code>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-save"></i> Perbarui Template
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
