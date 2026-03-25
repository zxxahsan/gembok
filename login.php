<?php
/**
 * Master Unified Login Page (Satu Pintu Login)
 */
require_once 'includes/auth.php';

// Check if already logged in ANYWHERE
if (isAdminLoggedIn()) redirect('admin/dashboard.php');
if (isSalesLoggedIn()) redirect('sales/dashboard.php');
if (isTechnicianLoggedIn()) redirect('technician/dashboard.php');
if (isCustomerLoggedIn()) redirect('portal/dashboard.php');

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlash('error', 'Sesi tidak valid atau telah kadaluarsa. Silakan coba lagi.');
        redirect('login.php');
    }

    $identifier = $_POST['identifier'] ?? '';
    $password = $_POST['password'] ?? '';

    // 1. Try Admin
    if (adminLogin($identifier, $password)) {
        setFlash('success', 'Login Admin berhasil!');
        redirect('admin/dashboard.php');
    }
    
    // 2. Try Technician
    $techLogin = technicianLogin($identifier, $password);
    if ($techLogin === true) {
        setFlash('success', 'Login Teknisi berhasil!');
        redirect('technician/dashboard.php');
    } elseif ($techLogin === 'inactive') {
        setFlash('error', 'Akun Teknisi Anda dinonaktifkan.');
        redirect('login.php');
    }

    // 3. Try Sales
    $salesLogin = salesLogin($identifier, $password);
    if ($salesLogin === true) {
        setFlash('success', 'Login Sales berhasil!');
        redirect('sales/dashboard.php');
    } elseif ($salesLogin === 'inactive') {
        setFlash('error', 'Akun Sales Anda dinonaktifkan.');
        redirect('login.php');
    }

    // 4. Try Customer (Identifier falls back gracefully to logic matching Phone Number logic natively bound)
    if (customerLogin($identifier, $password)) {
        setFlash('success', 'Login Pelanggan berhasil!');
        redirect('portal/dashboard.php');
    }

    // If all login cascades fail
    setFlash('error', 'Username / Nomor HP atau Password salah!');
    redirect('login.php');
}

$appName = getSetting('app_name', 'GEMBOK');
$pageTitle = 'Satu Pintu Login';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $pageTitle; ?> - GEMBOK</title>
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#0a0a12">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="manifest.json">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 0; font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at center, #1a1a2e 0%, #0a0a12 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
        }
        .login-card {
            background: #161628; border: 1px solid #2a2a40; border-radius: 16px;
            padding: 40px; width: 100%; max-width: 450px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3); text-align: center; margin: 20px;
        }
        .login-icon {
            font-size: 3.5rem; margin-bottom: 20px;
            background: linear-gradient(135deg, #00f5ff 0%, #bf00ff 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        h1 { font-size: 1.8rem; color: #fff; margin-bottom: 5px; }
        p { color: #b0b0c0; margin-bottom: 30px; font-size: 0.95rem; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-label { display: block; color: #fff; margin-bottom: 8px; font-weight: 500; }
        .form-control {
            width: 100%; padding: 14px; background: rgba(0,0,0,0.3);
            border: 1px solid #2a2a40; border-radius: 10px; color: #fff; font-size: 1.05rem;
            transition: all 0.3s;
        }
        .form-control:focus { outline: none; border-color: #00f5ff; }
        .btn-login {
            width: 100%; padding: 14px; border: none; border-radius: 10px;
            background: linear-gradient(135deg, #00f5ff 0%, #bf00ff 100%);
            color: #fff; font-size: 1.1rem; font-weight: 600; cursor: pointer;
            transition: transform 0.2s; box-shadow: 0 4px 15px rgba(191,0,255,0.3);
        }
        .btn-login:hover { transform: translateY(-2px); }
        .alert {
            padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: left;
            display: flex; align-items: center; gap: 10px;
        }
        .alert-error {
            background: rgba(255,71,87,0.1); border: 1px solid #ff4757; color: #ff4757;
        }
        .alert-success {
            background: rgba(46,213,115,0.1); border: 1px solid #2ed573; color: #2ed573;
        }
        .footer-link {
            display: inline-block; margin-top: 25px; color: #666680; text-decoration: none;
            transition: 0.3s;
        }
        .footer-link:hover { color: #00f5ff; }
    </style>
</head>
<body>
    <div class="login-card">
        <i class="fas fa-fingerprint login-icon"></i>
        <h1>Satu Pintu Login</h1>
        <p>Gunakan Username Sistem atau Nomor HP</p>

        <?php if (hasFlash('error')): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars(getFlash('error')); ?>
            </div>
        <?php endif; ?>
        <?php if (hasFlash('success')): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars(getFlash('success')); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            
            <div class="form-group">
                <label class="form-label">Nomor HP / Username</label>
                <input type="text" name="identifier" class="form-control" placeholder="08xxxxx / username_admin" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Masuk Sekarang
            </button>
        </form>

        <a href="index.php" class="footer-link"><i class="fas fa-home"></i> Kembali ke Beranda Terpadu</a>
    </div>
</body>
</html>
