<?php
/**
 * Homepage
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$stats = [
    'totalCustomers' => (int)(fetchOne("SELECT COUNT(*) as total FROM customers")['total'] ?? 0),
    'totalPackages' => (int)(fetchOne("SELECT COUNT(*) as total FROM packages")['total'] ?? 0),
    'totalInvoices' => (int)(fetchOne("SELECT COUNT(*) as total FROM invoices")['total'] ?? 0),
    'totalOnu' => (int)(fetchOne("SELECT COUNT(*) as total FROM onu_locations")['total'] ?? 0),
];

$packages = fetchAll("SELECT * FROM packages ORDER BY price ASC");

$adminContact = fetchOne("SELECT email, name FROM admin_users ORDER BY id ASC LIMIT 1");
$adminEmail = '';
if ($adminContact && !empty($adminContact['email'])) {
    $adminEmail = $adminContact['email'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - ISP Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Dark Theme Colors */
            --bg-primary: #0a0a12;
            --bg-secondary: #0f0f1a;
            --bg-card: #1a1a2e;
            --bg-input: #161628;
            --text-primary: #ffffff;
            --text-secondary: #b0b0c0;
            --text-muted: #666680;
            --border-color: #2a2a40;
            --accent-purple: #bf00ff;
            --accent-cyan: #00f5ff;
            --accent-pink: #ff00c8;
            --accent-blue: #00aaff;
            --neon-green: #00ff88;
            --neon-yellow: #ffeb3b;
            --neon-orange: #ff9800;
            --neon-red: #ff4444;
            --neon-cyan: #00f5ff;
            --neon-purple: #bf00ff;
            --shadow-card: 0 8px 32px rgba(0, 0, 0, 0.3);
            --shadow-glow: 0 0 20px rgba(191, 0, 255, 0.3);
            --gradient-primary: linear-gradient(135deg, #00f5ff 0%, #bf00ff 100%);
            --gradient-secondary: linear-gradient(135deg, #ff00c8 0%, #00aaff 100%);
            --gradient-accent: linear-gradient(135deg, #00ff88 0%, #ffeb3b 100%);
        }

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: radial-gradient(circle at center, #1a1a2e 0%, #0a0a12 100%);
        }

        .container {
            background: var(--bg-secondary);
            border-radius: 20px;
            box-shadow: var(--shadow-card);
            max-width: 1000px;
            width: 100%;
            overflow: hidden;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        /* Animated background effect */
        .container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(
                from 0deg,
                transparent,
                var(--accent-purple),
                var(--accent-cyan),
                var(--accent-pink),
                transparent
            );
            animation: rotate 10s linear infinite;
            z-index: -1;
        }

        .container::after {
            content: '';
            position: absolute;
            inset: 1px;
            border-radius: 19px;
            background: var(--bg-secondary);
            z-index: -1;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .header {
            background: var(--bg-card);
            color: var(--text-primary);
            padding: 40px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            position: relative;
            overflow: visible;
            z-index: 1;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--gradient-primary);
            opacity: 0.1;
            z-index: 0;
        }

        .header h1 {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 10px;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
            z-index: 1;
        }

        .header p {
            opacity: 0.8;
            font-size: 1.2rem;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .header .subtitle {
            font-size: 1rem;
            color: var(--text-secondary);
            position: relative;
            z-index: 1;
            margin-bottom: 25px;
        }

        .header .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            position: relative;
            z-index: 3;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid transparent;
            position: relative;
            z-index: 2;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 4px 20px rgba(191, 0, 255, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(191, 0, 255, 0.4), 0 0 20px rgba(191, 0, 255, 0.2);
        }

        .btn-secondary {
            background: var(--bg-input);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-card);
            border-color: var(--accent-cyan);
            box-shadow: 0 0 15px rgba(0, 245, 255, 0.2);
        }

        .content {
            padding: 40px;
            position: relative;
            z-index: 1;
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-align: center;
        }

        .section-subtitle {
            font-size: 0.95rem;
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 25px;
        }

        .pricing-section {
            margin-bottom: 40px;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }

        .pricing-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .pricing-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: var(--gradient-secondary);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }

        .pricing-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-glow);
            border-color: var(--accent-cyan);
        }

        .pricing-card:hover::before {
            opacity: 0.08;
        }

        .pricing-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .pricing-desc {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 16px;
        }

        .pricing-price {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 8px;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .pricing-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .pricing-chip {
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(0, 245, 255, 0.08);
            border: 1px solid rgba(0, 245, 255, 0.4);
            color: var(--neon-cyan);
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .feature {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .feature::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--gradient-primary);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }

        .feature:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-glow), 0 20px 40px rgba(0, 0, 0, 0.4);
            border-color: var(--accent-purple);
        }

        .feature:hover::before {
            opacity: 0.1;
        }

        .feature i {
            font-size: 3rem;
            margin-bottom: 20px;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: inline-block;
        }

        .feature h3 {
            font-size: 1.3rem;
            margin-bottom: 12px;
            color: var(--text-primary);
            font-weight: 600;
        }

        .feature p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-glow);
            border-color: var(--accent-cyan);
        }
        
        @media (max-width: 480px) {
            .stat-card {
                padding: 15px;
            }
            
            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }
            
            .stat-info h3 {
                font-size: 1.5rem;
            }
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-glow);
            border-color: var(--accent-cyan);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.5rem;
        }

        .cyan { background: rgba(0, 245, 255, 0.1); color: var(--neon-cyan); }
        .green { background: rgba(0, 255, 136, 0.1); color: var(--neon-green); }
        .purple { background: rgba(191, 0, 255, 0.1); color: var(--neon-purple); }
        .pink { background: rgba(255, 0, 200, 0.1); color: var(--neon-pink); }

        .stat-info h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-info p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .actions {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 16px 32px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: 1px solid transparent;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 4px 20px rgba(191, 0, 255, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(191, 0, 255, 0.4), 0 0 20px rgba(191, 0, 255, 0.2);
        }

        .btn-secondary {
            background: var(--bg-input);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-card);
            border-color: var(--accent-cyan);
            box-shadow: 0 0 15px rgba(0, 245, 255, 0.2);
        }

        .btn-tertiary {
            background: transparent;
            color: var(--neon-cyan);
            border: 1px solid var(--accent-cyan);
        }

        .btn-tertiary:hover {
            background: rgba(0, 245, 255, 0.1);
            box-shadow: 0 0 15px rgba(0, 245, 255, 0.2);
        }

        .footer {
            background: var(--bg-card);
            padding: 30px;
            text-align: center;
            border-top: 1px solid var(--border-color);
        }

        .footer p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .footer a {
            color: var(--neon-cyan);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer a:hover {
            color: var(--accent-purple);
            text-decoration: underline;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .footer-links a {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .highlight {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .header p {
                font-size: 1rem;
            }
            
            .header .actions {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
            
            .content {
                padding: 30px 20px;
            }
            
            .actions {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .header h1 {
                font-size: 1.8rem;
            }
            
            .header p {
                font-size: 0.9rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .features {
                grid-template-columns: 1fr;
            }
            
            .feature {
                padding: 20px;
            }
            
            .content {
                padding: 20px;
            }
            
            .header .actions {
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container" id="home">
        <div class="header">
            <h1>🚀 <?php echo htmlspecialchars(APP_NAME); ?></h1>
            <p><?php echo htmlspecialchars(getSetting('homepage_title', 'Internet cepat dan stabil untuk rumah dan bisnis Anda')); ?></p>
            <div class="subtitle"><?php echo htmlspecialchars(getSetting('homepage_subtitle', 'Kelola koneksi pelanggan dan billing dengan sistem yang sederhana')); ?></div>
            
            <div class="actions">
                <a href="./admin/login.php" class="btn btn-primary">
                    <i class="fas fa-user-shield"></i> Dashboard Admin
                </a>
                <a href="./portal/login.php" class="btn btn-secondary">
                    <i class="fas fa-user"></i> Area Pelanggan
                </a>
            </div>
        </div>
        
        <div class="content">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon cyan">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['totalCustomers']); ?></h3>
                        <p>Pelanggan</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['totalPackages']); ?></h3>
                        <p>Paket</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['totalInvoices']); ?></h3>
                        <p>Invoice</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon pink">
                        <i class="fas fa-satellite-dish"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['totalOnu']); ?></h3>
                        <p>ONU Devices</p>
                    </div>
                </div>
            </div>

            <?php if (!empty($packages)): ?>
                <div class="pricing-section" id="packages">
                    <h2 class="section-title">Paket Internet</h2>
                    <p class="section-subtitle">Pilih paket yang sesuai dengan kebutuhan internet Anda</p>
                    <div class="pricing-grid">
                        <?php foreach ($packages as $package): ?>
                            <div class="pricing-card">
                                <div class="pricing-name">
                                    <?php echo htmlspecialchars($package['name']); ?>
                                </div>
                                <?php if (!empty($package['description'])): ?>
                                    <div class="pricing-desc">
                                        <?php echo nl2br(htmlspecialchars($package['description'])); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="pricing-price">
                                    <?php echo formatCurrency($package['price']); ?>/bulan
                                </div>
                                <div class="pricing-meta">
                                    <span class="pricing-chip">
                                        Profil: <?php echo htmlspecialchars($package['profile_normal']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="features" id="features">
                <div class="feature">
                    <i class="fas fa-tachometer-alt"></i>
                    <h3>Dashboard Modern</h3>
                    <p>Statistik real-time dan monitoring lengkap dengan tampilan yang elegan dan informatif</p>
                </div>
                <div class="feature">
                    <i class="fas fa-users"></i>
                    <h3>Manajemen Pelanggan</h3>
                    <p>Kelola pelanggan, paket internet, dan status koneksi secara efisien dan cepat</p>
                </div>
                <div class="feature">
                    <i class="fas fa-file-invoice"></i>
                    <h3>Billing Otomatis</h3>
                    <p>Sistem invoice otomatis, pembayaran terintegrasi, dan pengingat jatuh tempo</p>
                </div>
                <div class="feature">
                    <i class="fas fa-network-wired"></i>
                    <h3>Integrasi MikroTik</h3>
                    <p>Manajemen PPPoE dan Hotspot langsung dari sistem dengan kontrol penuh</p>
                </div>
                <div class="feature">
                    <i class="fas fa-satellite-dish"></i>
                    <h3>Monitoring GenieACS</h3>
                    <p>Monitoring ONU/ONT devices secara real-time dengan informasi status lengkap</p>
                </div>
                <div class="feature">
                    <i class="fas fa-map-marked-alt"></i>
                    <h3>Visualisasi Peta</h3>
                    <p>Lokasi pelanggan dan perangkat dalam peta interaktif untuk manajemen area</p>
                </div>
                <div class="feature">
                    <i class="fas fa-ticket-alt"></i>
                    <h3>Trouble Ticket</h3>
                    <p>Sistem pelaporan gangguan lengkap dengan notifikasi dan penanganan otomatis</p>
                </div>
                <div class="feature">
                    <i class="fas fa-ticket-alt"></i>
                    <h3>Voucher Generator</h3>
                    <p>Generate voucher hotspot dengan mudah dan integrasi langsung ke MikroTik</p>
                </div>
            </div>
        </div>
        
        <div class="footer" id="contact">
            <p>
                <span class="highlight"><?php echo htmlspecialchars(APP_NAME); ?></span> - <?php echo htmlspecialchars(getSetting('homepage_tagline', APP_NAME . ' - ISP Management System')); ?><br>
            </p>
            <div class="footer-links">
                <?php if (!empty($adminEmail)): ?>
                    <a href="mailto:<?php echo htmlspecialchars($adminEmail); ?>" target="_blank">
                        <i class="fas fa-envelope"></i> Kontak Admin
                    </a>
                <?php endif; ?>
                <a href="#" onclick="alert('Versi: <?php echo APP_VERSION; ?>')">
                    <i class="fas fa-code-branch"></i> v<?php echo APP_VERSION; ?>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
