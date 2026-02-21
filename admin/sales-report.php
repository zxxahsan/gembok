<?php
/**
 * Hotspot Sales Report
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Laporan Penjualan Hotspot';

// Handle filters
$date_from = sanitize($_GET['date_from'] ?? date('Y-m-d'));
$date_to = sanitize($_GET['date_to'] ?? date('Y-m-d'));
$profile_filter = sanitize($_GET['profile'] ?? 'all');

$where = "DATE(created_at) BETWEEN ? AND ?";
$params = [$date_from, $date_to];

if ($profile_filter !== 'all') {
    $where .= " AND profile = ?";
    $params[] = $profile_filter;
}

// Get Sales Data
$sales = fetchAll("SELECT * FROM hotspot_sales WHERE $where ORDER BY created_at DESC", $params);

// Get Profiles for filter
$profiles = fetchAll("SELECT DISTINCT profile FROM hotspot_sales");

// Statistics
$total_count = count($sales);
$total_income = array_sum(array_column($sales, 'price'));
$total_profit = array_sum(array_column($sales, 'selling_price'));

ob_start();
?>

<!-- Filter Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-filter"></i> Filter Laporan</h3>
    </div>
    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div class="form-group">
            <label class="form-label">Dari Tanggal</label>
            <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Sampai Tanggal</label>
            <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Profile</label>
            <select name="profile" class="form-control">
                <option value="all">Semua Profile</option>
                <?php foreach ($profiles as $p): ?>
                    <option value="<?php echo htmlspecialchars($p['profile']); ?>" <?php echo $profile_filter === $p['profile'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['profile']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="display: flex; align-items: flex-end;">
            <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-search"></i>
                Tampilkan</button>
        </div>
    </form>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-ticket-alt"></i></div>
        <div class="stat-info">
            <h3>
                <?php echo $total_count; ?>
            </h3>
            <p>Voucher Terjual</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div>
        <div class="stat-info">
            <h3>
                <?php echo formatCurrency($total_income); ?>
            </h3>
            <p>Total Penjualan</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon cyan"><i class="fas fa-chart-line"></i></div>
        <div class="stat-info">
            <h3>
                <?php echo formatCurrency($total_profit); ?>
            </h3>
            <p>Total Harga Jual</p>
        </div>
    </div>
</div>

<!-- Sales Table -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title"><i class="fas fa-list"></i> Rincian Penjualan</h3>
        <button onclick="window.print()" class="btn btn-secondary btn-sm"><i class="fas fa-print"></i> Cetak</button>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Username</th>
                    <th>Profile</th>
                    <th>Harga (MikroTik)</th>
                    <th>Harga Jual</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sales)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px;">Tidak ada data penjualan pada periode ini
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sales as $s): ?>
                        <tr>
                            <td data-label="Waktu">
                                <?php echo date('d/m/Y H:i', strtotime($s['created_at'])); ?>
                            </td>
                            <td data-label="Username"><strong>
                                    <?php echo htmlspecialchars($s['username']); ?>
                                </strong></td>
                            <td data-label="Profile"><span class="badge badge-info">
                                    <?php echo htmlspecialchars($s['profile']); ?>
                                </span></td>
                            <td data-label="Harga (MikroTik)">
                                <?php echo formatCurrency($s['price']); ?>
                            </td>
                            <td data-label="Harga Jual"><strong>
                                    <?php echo formatCurrency($s['selling_price']); ?>
                                </strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
