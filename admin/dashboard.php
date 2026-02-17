<?php
/**
 * Admin Dashboard
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Dashboard';

// Get statistics
$stats = [
    'totalCustomers' => fetchOne("SELECT COUNT(*) as total FROM customers")['total'] ?? 0,
    'activeCustomers' => fetchOne("SELECT COUNT(*) as total FROM customers WHERE status = 'active'")['total'] ?? 0,
    'isolatedCustomers' => fetchOne("SELECT COUNT(*) as total FROM customers WHERE status = 'isolated'")['total'] ?? 0,
    'totalPackages' => fetchOne("SELECT COUNT(*) as total FROM packages")['total'] ?? 0,
    'totalInvoices' => fetchOne("SELECT COUNT(*) as total FROM invoices")['total'] ?? 0,
    'paidInvoices' => fetchOne("SELECT COUNT(*) as total FROM invoices WHERE status = 'paid'")['total'] ?? 0,
    'pendingInvoices' => fetchOne("SELECT COUNT(*) as total FROM invoices WHERE status = 'unpaid'")['total'] ?? 0,
    'totalRevenue' => fetchOne("SELECT SUM(amount) as total FROM invoices WHERE status = 'paid'")['total'] ?? 0,
];

// Get recent invoices
$recentInvoices = fetchAll("
    SELECT i.*, c.name as customer_name 
    FROM invoices i 
    LEFT JOIN customers c ON i.customer_id = c.id 
    ORDER BY i.created_at DESC 
    LIMIT 10
");

// Get recent customers
$recentCustomers = fetchAll("
    SELECT c.*, p.name as package_name 
    FROM customers c 
    LEFT JOIN packages p ON c.package_id = p.id 
    ORDER BY c.created_at DESC 
    LIMIT 5
");

// Get monthly revenue for chart (last 6 months)
$monthlyRevenue = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-{$i} months"));
    $monthName = date('M Y', strtotime("-{$i} months"));
    
    $revenue = fetchOne("
        SELECT SUM(amount) as total 
        FROM invoices 
        WHERE status = 'paid' 
        AND DATE_FORMAT(paid_at, '%Y-%m') = ?
    ", [$month])['total'] ?? 0;
    
    $monthlyRevenue[] = [
        'month' => $monthName,
        'revenue' => (float)$revenue
    ];
}

// Get monthly customer growth (last 6 months)
$monthlyCustomers = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-{$i} months"));
    $monthName = date('M Y', strtotime("-{$i} months"));
    
    $count = fetchOne("
        SELECT COUNT(*) as total 
        FROM customers 
        WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    ", [$month])['total'] ?? 0;
    
    $monthlyCustomers[] = [
        'month' => $monthName,
        'count' => (int)$count
    ];
}

// Get unpaid invoices count by due status
$overdueInvoices = fetchOne("
    SELECT COUNT(*) as total 
    FROM invoices 
    WHERE status = 'unpaid' 
    AND due_date < CURDATE()
")['total'] ?? 0;

$dueSoonInvoices = fetchOne("
    SELECT COUNT(*) as total 
    FROM invoices 
    WHERE status = 'unpaid' 
    AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
")['total'] ?? 0;

ob_start();
?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon cyan">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['totalCustomers']; ?></h3>
            <p>Total Pelanggan</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['activeCustomers']; ?></h3>
            <p>Pelanggan Aktif</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-ban"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['isolatedCustomers']; ?></h3>
            <p>Pelanggan Isolir</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-box"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['totalPackages']; ?></h3>
            <p>Total Paket</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon cyan">
            <i class="fas fa-file-invoice"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['totalInvoices']; ?></h3>
            <p>Total Invoice</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['paidInvoices']; ?></h3>
            <p>Invoice Lunas</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon yellow">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['pendingInvoices']; ?></h3>
            <p>Invoice Pending</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon pink">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo formatCurrency($stats['totalRevenue']); ?></h3>
            <p>Total Pendapatan</p>
        </div>
    </div>
</div>

<!-- Alert for overdue invoices -->
<?php if ($overdueInvoices > 0 || $dueSoonInvoices > 0): ?>
<div class="alert alert-warning" style="margin-bottom: 20px;">
    <i class="fas fa-exclamation-triangle"></i>
    <span>
        <?php if ($overdueInvoices > 0): ?>
            <strong><?php echo $overdueInvoices; ?></strong> invoice sudah melewati jatuh tempo.
        <?php endif; ?>
        <?php if ($dueSoonInvoices > 0): ?>
            <strong><?php echo $dueSoonInvoices; ?></strong> invoice akan jatuh tempo dalam 7 hari.
        <?php endif; ?>
        <a href="invoices.php" style="color: inherit; text-decoration: underline; margin-left: 10px;">Lihat Invoice</a>
    </span>
</div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-bolt"></i> Menu Cepat</h3>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <a href="customers.php" class="btn btn-secondary" style="justify-content: center;">
            <i class="fas fa-users"></i> Pelanggan
        </a>
        <a href="packages.php" class="btn btn-secondary" style="justify-content: center;">
            <i class="fas fa-box"></i> Paket
        </a>
        <a href="invoices.php" class="btn btn-secondary" style="justify-content: center;">
            <i class="fas fa-file-invoice"></i> Invoice
        </a>
        <a href="mikrotik.php" class="btn btn-secondary" style="justify-content: center;">
            <i class="fas fa-network-wired"></i> PPPoE
        </a>
        <a href="hotspot.php" class="btn btn-secondary" style="justify-content: center;">
            <i class="fas fa-wifi"></i> Hotspot
        </a>
        <a href="genieacs.php" class="btn btn-secondary" style="justify-content: center;">
            <i class="fas fa-satellite-dish"></i> GenieACS
        </a>
        <a href="map.php" class="btn btn-secondary" style="justify-content: center;">
            <i class="fas fa-map-marked-alt"></i> Peta
        </a>
        <a href="trouble.php" class="btn btn-secondary" style="justify-content: center;">
            <i class="fas fa-exclamation-triangle"></i> Gangguan
        </a>
        <a href="settings.php" class="btn btn-secondary" style="justify-content: center;">
            <i class="fas fa-cog"></i> Settings
        </a>
    </div>
</div>

<!-- Charts -->
<div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-top: 20px;" id="charts-container">
    <!-- Revenue Chart -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-line"></i> Pendapatan Bulanan</h3>
        </div>
        <canvas id="revenueChart" height="250"></canvas>
    </div>
    
    <!-- Customer Growth Chart -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-bar"></i> Pelanggan Baru</h3>
        </div>
        <canvas id="customerChart" height="250"></canvas>
    </div>
</div>

<!-- Recent Invoices -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history"></i> Invoice Terbaru</h3>
        <a href="invoices.php" class="btn btn-primary btn-sm">Lihat Semua</a>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>#Invoice</th>
                <th>Pelanggan</th>
                <th>Jumlah</th>
                <th>Status</th>
                <th>Tanggal</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recentInvoices)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                        Belum ada invoice
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($recentInvoices as $invoice): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($invoice['invoice_number']); ?></code></td>
                    <td><?php echo htmlspecialchars($invoice['customer_name'] ?? '-'); ?></td>
                    <td><?php echo formatCurrency($invoice['amount']); ?></td>
                    <td>
                        <?php if ($invoice['status'] === 'paid'): ?>
                            <span class="badge badge-success">Lunas</span>
                        <?php elseif ($invoice['status'] === 'unpaid'): ?>
                            <span class="badge badge-warning">Belum Bayar</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Batal</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo formatDate($invoice['created_at']); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Recent Customers -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-user-plus"></i> Pelanggan Terbaru</h3>
        <a href="customers.php" class="btn btn-primary btn-sm">Lihat Semua</a>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Nama</th>
                <th>PPPoE</th>
                <th>Paket</th>
                <th>Status</th>
                <th>Terdaftar</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recentCustomers)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                        Belum ada pelanggan
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($recentCustomers as $customer): ?>
                <tr>
                    <td><?php echo htmlspecialchars($customer['name']); ?></td>
                    <td><code><?php echo htmlspecialchars($customer['pppoe_username']); ?></code></td>
                    <td><?php echo htmlspecialchars($customer['package_name'] ?? '-'); ?></td>
                    <td>
                        <?php if ($customer['status'] === 'active'): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Isolir</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo formatDate($customer['created_at']); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Revenue Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($monthlyRevenue, 'month')); ?>,
        datasets: [{
            label: 'Pendapatan',
            data: <?php echo json_encode(array_column($monthlyRevenue, 'revenue')); ?>,
            borderColor: '#00f5ff',
            backgroundColor: 'rgba(0, 245, 255, 0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Rp ' + value.toLocaleString('id-ID');
                    },
                    color: '#9ca3af'
                },
                grid: { color: 'rgba(255,255,255,0.1)' }
            },
            x: {
                ticks: { color: '#9ca3af' },
                grid: { color: 'rgba(255,255,255,0.1)' }
            }
        }
    }
});

// Customer Chart
const customerCtx = document.getElementById('customerChart').getContext('2d');
new Chart(customerCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($monthlyCustomers, 'month')); ?>,
        datasets: [{
            label: 'Pelanggan Baru',
            data: <?php echo json_encode(array_column($monthlyCustomers, 'count')); ?>,
            backgroundColor: 'rgba(191, 0, 255, 0.5)',
            borderColor: '#bf00ff',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { color: '#9ca3af', stepSize: 1 },
                grid: { color: 'rgba(255,255,255,0.1)' }
            },
            x: {
                ticks: { color: '#9ca3af' },
                grid: { color: 'rgba(255,255,255,0.1)' }
            }
        }
    }
});
</script>

<style>
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-warning { background: rgba(255, 193, 7, 0.1); border: 1px solid var(--neon-orange); color: var(--neon-orange); }
</style>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
