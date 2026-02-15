<?php
/**
 * Webhook Handler - Telegram Bot
 */

require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

try {
    // Get raw POST data
    $json = file_get_contents('php://input');
    
    logActivity('TELEGRAM_WEBHOOK', "Received webhook");
    
    // Validate token if configured
    if (!empty(TELEGRAM_BOT_TOKEN)) {
        // Telegram doesn't use signature validation
        // Just log the webhook
    }
    
    // Parse JSON data
    $data = json_decode($json, true);
    
    if (!$data) {
        logError('Telegram webhook: Invalid JSON');
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }
    
    // Log webhook
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO webhook_logs (source, payload, status_code, response, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute(['telegram', $json, 200, 'Received']);
    
    $message = $data['message'] ?? null;
    $callbackQuery = $data['callback_query'] ?? null;
    
    if ($callbackQuery) {
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        $callbackDataString = $callbackQuery['data'] ?? '';
        $callbackData = [];
        
        if ($callbackDataString !== '') {
            parse_str($callbackDataString, $callbackData);
        }
        
        $action = $callbackData['action'] ?? '';
        
        switch ($action) {
            case 'pay_invoice':
                handlePayInvoice($chatId, $callbackData);
                break;
                
            case 'check_status':
                handleCheckStatus($chatId, $callbackData);
                break;
                
            case 'help':
                handleHelp($chatId);
                break;
                
            case 'billing_menu':
                handleBillingMenu($chatId);
                break;
                
            case 'billing_help_cek':
                handleBillingHelpCek($chatId);
                break;
                
            case 'billing_help_isolir':
                handleBillingHelpIsolir($chatId);
                break;
                
            case 'billing_help_bukaisolir':
                handleBillingHelpBukaIsolir($chatId);
                break;
            
            case 'billing_help_invoice':
                handleBillingHelpInvoice($chatId);
                break;
            
            case 'billing_help_lunas':
                handleBillingHelpLunas($chatId);
                break;
            
            case 'billing_mark_paid':
                handleBillingMarkPaidCallback($chatId, $callbackData, $callbackQuery);
                break;
            
            case 'mt_pppoe_kick':
                handlePppoeKickCallback($chatId, $callbackData);
                break;
            
            case 'mt_pppoe_disable':
                handlePppoeDisableCallback($chatId, $callbackData);
                break;
            
            case 'mt_pppoe_enable':
                handlePppoeEnableCallback($chatId, $callbackData);
                break;
            
            case 'mt_pppoe_del':
                handlePppoeDelCallback($chatId, $callbackData, $callbackQuery);
                break;
            
            case 'mt_hotspot_del':
                handleHotspotDelCallback($chatId, $callbackData, $callbackQuery);
                break;
            
            case 'mikrotik_menu':
                handleMikrotikMenu($chatId);
                break;
            
            case 'mt_resource':
                handleMikrotikResource($chatId);
                break;
            
            case 'mt_online':
                handleMikrotikOnline($chatId);
                break;
            
            case 'mt_ping_help':
                handleMikrotikPingHelp($chatId);
                break;
            
            case 'mt_pppoe_help':
                handleMikrotikPppoeHelp($chatId);
                break;
            
            case 'mt_hotspot_help':
                handleMikrotikHotspotHelp($chatId);
                break;
                
            default:
                handleHelp($chatId);
        }
    } elseif ($message) {
        $chatId = $message['chat']['id'] ?? null;
        $text = trim($message['text'] ?? '');
        
        if ($text !== '' && $text[0] === '/') {
            handleCommand($chatId, $text);
        } else {
            handleRegularMessage($chatId, $text);
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Webhook processed']);
    
} catch (Exception $e) {
    logError("Telegram webhook error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

function handlePayInvoice($chatId, $data) {
    $invoiceId = $data['invoice_id'] ?? '';
    
    // Get invoice details
    $invoice = fetchOne("SELECT i.*, c.name as customer_name FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id WHERE i.id = ?", [$invoiceId]);
    
    if (!$invoice) {
        sendMessage($chatId, "❌ Invoice tidak ditemukan.");
        return;
    }
    
    // Send payment link
    $paymentLink = generatePaymentLink($invoice);
    
    $message = "💳 *Invoice #{$invoice['invoice_number']}*\n\n";
    $message .= "Pelanggan: {$invoice['customer_name']}\n";
    $message .= "Jumlah: " . formatCurrency($invoice['amount']) . "\n";
    $message .= "Jatuh Tempo: " . formatDate($invoice['due_date']) . "\n\n";
    $message .= "Silakan bayar melalui link berikut:\n";
    $message .= $paymentLink;
    
    sendMessage($chatId, $message);
}

function handleCheckStatus($chatId, $data) {
    $phone = $data['phone'] ?? '';
    
    // Get customer by phone
    $customer = fetchOne("SELECT * FROM customers WHERE phone = ?", [$phone]);
    
    if (!$customer) {
        sendMessage($chatId, "❌ Pelanggan tidak ditemukan dengan nomor HP tersebut.");
        return;
    }
    
    // Get customer status
    $status = $customer['status'] === 'active' ? 'Aktif' : 'Isolir';
    
    $message = "📊 *Status Pelanggan*\n\n";
    $message .= "Nama: {$customer['name']}\n";
    $message .= "No HP: {$customer['phone']}\n";
    $message .= "PPPoE Username: {$customer['pppoe_username']}\n";
    $message .= "Status: {$status}\n";
    
    if ($customer['status'] === 'isolated') {
        $message .= "\n⚠️ Koneksi sedang diisolir karena belum bayar.";
    }
    
    sendMessage($chatId, $message);
}

function handleHelp($chatId) {
    $message = "🤖 GEMBOK Bot Commands\n\n";
    $message .= "Untuk pelanggan:\n";
    $message .= "/pay_invoice &lt;invoice_id&gt; - Cek dan bayar invoice\n";
    $message .= "/check_status &lt;no_hp&gt; - Cek status pelanggan\n";
    $message .= "/help - Tampilkan bantuan ini\n\n";
    
    if (isAdminChat($chatId)) {
        $message .= "Untuk admin:\n";
        $message .= "/menu - Tampilkan menu utama\n";
        $message .= "/billing_cek &lt;pppoe_username&gt; - Cek tagihan pelanggan\n";
        $message .= "/billing_invoice &lt;pppoe_username&gt; - Daftar invoice pelanggan\n";
        $message .= "/billing_isolir &lt;pppoe_username&gt; - Isolir pelanggan\n";
        $message .= "/billing_bukaisolir &lt;pppoe_username&gt; - Buka isolir pelanggan\n";
        $message .= "/billing_lunas &lt;no_invoice&gt; - Tandai invoice lunas\n";
        $message .= "/mt_setprofile &lt;pppoe_username&gt; &lt;profile&gt; - Ganti profile PPPoE\n";
        $message .= "/mt_resource - Cek resource MikroTik\n";
        $message .= "/mt_online - Cek user PPPoE online\n";
        $message .= "/mt_ping &lt;ip/host&gt; - Ping dari MikroTik\n";
        $message .= "/pppoe_list - Daftar user PPPoE\n";
        $message .= "/pppoe_add &lt;user&gt; &lt;pass&gt; &lt;profile&gt; - Tambah PPPoE\n";
        $message .= "/pppoe_del &lt;user&gt; - Hapus PPPoE\n";
        $message .= "/pppoe_disable &lt;user&gt; - Nonaktifkan PPPoE\n";
        $message .= "/pppoe_enable &lt;user&gt; - Aktifkan PPPoE\n";
        $message .= "/hs_list - Daftar user Hotspot\n";
        $message .= "/hs_add &lt;user&gt; &lt;pass&gt; &lt;profile&gt; - Tambah Hotspot\n";
        $message .= "/hs_del &lt;user&gt; - Hapus Hotspot\n";
    }
    
    sendMessage($chatId, $message);
}

function handleRegularMessage($chatId, $message) {
    $message = "Terima kasih atas pesan Anda.\n\n";
    $message .= "Untuk menggunakan bot ini, silakan gunakan command yang tersedia.\n";
    $message .= "Ketik /help untuk melihat daftar command.";
    
    sendMessage($chatId, $message);
}

function sendMessage($chatId, $text, $options = []) {
    if (empty(TELEGRAM_BOT_TOKEN)) {
        logError('Telegram bot token not configured');
        return false;
    }
    
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if (!empty($options)) {
        $data = array_merge($data, $options);
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    logActivity('TELEGRAM_SEND', "To: {$chatId}, Status code: {$httpCode}");
    
    return $httpCode === 200;
}

function editMessageText($chatId, $messageId, $text, $replyMarkup = null) {
    if (empty(TELEGRAM_BOT_TOKEN)) {
        logError('Telegram bot token not configured');
        return false;
    }
    
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/editMessageText";
    
    $data = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($replyMarkup !== null) {
        $data['reply_markup'] = $replyMarkup;
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    logActivity('TELEGRAM_EDIT', "To: {$chatId}, Msg: {$messageId}, Status code: {$httpCode}");
    
    return $httpCode === 200;
}

function generatePaymentLink($invoice) {
    // Generate Tripay payment link
    if (empty(TRIPAY_API_KEY) || empty(TRIPAY_MERCHANT_CODE)) {
        return 'Payment gateway not configured';
    }
    
    // This is a placeholder - implement actual Tripay payment link generation
    $amount = $invoice['amount'];
    $merchantRef = $invoice['invoice_number'];
    
    $paymentLink = "https://tripay.co.id/checkout?merchant_code=" . TRIPAY_MERCHANT_CODE . "&amount={$amount}&merchant_ref={$merchantRef}";
    
    return $paymentLink;
}

function isAdminChat($chatId) {
    $adminChatId = getSetting('TELEGRAM_ADMIN_CHAT_ID', '');
    if ($adminChatId === '') {
        return false;
    }
    return (string)$chatId === (string)$adminChatId;
}

function handleCommand($chatId, $text) {
    $parts = explode(' ', trim($text), 2);
    $command = strtolower($parts[0]);
    $args = $parts[1] ?? '';
    
    switch ($command) {
        case '/start':
        case '/menu':
            handleMenu($chatId);
            break;
            
        case '/help':
            handleHelp($chatId);
            break;
            
        case '/pay_invoice':
            $invoiceId = trim($args);
            if ($invoiceId === '') {
                sendMessage($chatId, "Format: /pay_invoice &lt;invoice_id&gt;");
                break;
            }
            handlePayInvoice($chatId, ['invoice_id' => $invoiceId]);
            break;
            
        case '/check_status':
            $phone = trim($args);
            if ($phone === '') {
                sendMessage($chatId, "Format: /check_status &lt;no_hp&gt;");
                break;
            }
            handleCheckStatus($chatId, ['phone' => $phone]);
            break;
            
        case '/billing_cek':
            handleBillingCheck($chatId, $args);
            break;
            
        case '/billing_invoice':
            handleBillingInvoice($chatId, $args);
            break;
            
        case '/billing_isolir':
            handleBillingIsolir($chatId, $args);
            break;
            
        case '/billing_bukaisolir':
            handleBillingBukaIsolir($chatId, $args);
            break;
        
        case '/billing_lunas':
            handleBillingLunas($chatId, $args);
            break;
            
        case '/mt_resource':
            handleMikrotikResource($chatId);
            break;
            
        case '/mt_online':
            handleMikrotikOnline($chatId);
            break;
            
        case '/mt_ping':
            handleMikrotikPing($chatId, $args);
            break;
        
        case '/mt_setprofile':
            handleMikrotikSetProfile($chatId, $args);
            break;
        
        case '/pppoe_list':
            handlePppoeList($chatId);
            break;
        
        case '/pppoe_add':
            handlePppoeAdd($chatId, $args);
            break;
        
        case '/pppoe_del':
            handlePppoeDel($chatId, $args);
            break;
        
        case '/pppoe_disable':
            handlePppoeDisable($chatId, $args);
            break;
        
        case '/pppoe_enable':
            handlePppoeEnable($chatId, $args);
            break;
        
        case '/hs_list':
            handleHotspotList($chatId);
            break;
        
        case '/hs_add':
            handleHotspotAdd($chatId, $args);
            break;
        
        case '/hs_del':
            handleHotspotDel($chatId, $args);
            break;
            
        default:
            handleRegularMessage($chatId, $text);
    }
}

function handleMenu($chatId) {
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📄 Billing', 'callback_data' => 'action=billing_menu'],
                ['text' => '📡 MikroTik', 'callback_data' => 'action=mikrotik_menu']
            ],
            [
                ['text' => '❓ Help', 'callback_data' => 'action=help']
            ]
        ]
    ];
    
    sendMessage($chatId, "Pilih menu:", ['reply_markup' => $keyboard]);
}

function handleBillingMenu($chatId) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah billing hanya untuk admin.");
        return;
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📄 Cek Tagihan', 'callback_data' => 'action=billing_help_cek'],
                ['text' => '📜 Daftar Invoice', 'callback_data' => 'action=billing_help_invoice']
            ],
            [
                ['text' => '🔒 Isolir Pelanggan', 'callback_data' => 'action=billing_help_isolir'],
                ['text' => '🔓 Buka Isolir', 'callback_data' => 'action=billing_help_bukaisolir']
            ],
            [
                ['text' => '✅ Tandai Lunas', 'callback_data' => 'action=billing_help_lunas']
            ]
        ]
    ];
    
    sendMessage($chatId, "Menu Billing Admin:", ['reply_markup' => $keyboard]);
}

function handleBillingHelpCek($chatId) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah billing hanya untuk admin.");
        return;
    }
    
    $message = "📄 Cek Tagihan Pelanggan\n\n";
    $message .= "Gunakan perintah:\n";
    $message .= "/billing_cek &lt;pppoe_username&gt;\n\n";
    $message .= "Contoh:\n";
    $message .= "/billing_cek pelanggan001";
    
    sendMessage($chatId, $message);
}

function handleBillingHelpIsolir($chatId) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah billing hanya untuk admin.");
        return;
    }
    
    $message = "🔒 Isolir Pelanggan\n\n";
    $message .= "Gunakan perintah:\n";
    $message .= "/billing_isolir &lt;pppoe_username&gt;\n\n";
    $message .= "Contoh:\n";
    $message .= "/billing_isolir pelanggan001";
    
    sendMessage($chatId, $message);
}

function handleBillingHelpBukaIsolir($chatId) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah billing hanya untuk admin.");
        return;
    }
    
    $message = "🔓 Buka Isolir Pelanggan\n\n";
    $message .= "Gunakan perintah:\n";
    $message .= "/billing_bukaisolir &lt;pppoe_username&gt;\n\n";
    $message .= "Contoh:\n";
    $message .= "/billing_bukaisolir pelanggan001";
    
    sendMessage($chatId, $message);
}

function handleBillingHelpInvoice($chatId) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah billing hanya untuk admin.");
        return;
    }
    
    $message = "📜 Daftar Invoice Pelanggan\n\n";
    $message .= "Gunakan perintah:\n";
    $message .= "/billing_invoice &lt;pppoe_username&gt;\n\n";
    $message .= "Contoh:\n";
    $message .= "/billing_invoice pelanggan001";
    
    sendMessage($chatId, $message);
}

function handleBillingHelpLunas($chatId) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah billing hanya untuk admin.");
        return;
    }
    
    $message = "✅ Tandai Invoice Lunas\n\n";
    $message .= "Gunakan perintah:\n";
    $message .= "/billing_lunas &lt;no_invoice&gt;\n\n";
    $message .= "Contoh:\n";
    $message .= "/billing_lunas INV-2026-0001";
    
    sendMessage($chatId, $message);
}

function handleBillingCheck($chatId, $args) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah billing hanya untuk admin.");
        return;
    }
    
    $username = trim($args);
    if ($username === '') {
        sendMessage($chatId, "Format: /billing_cek &lt;pppoe_username&gt;");
        return;
    }
    
    $customer = fetchOne("SELECT c.*, p.name AS package_name, p.price AS package_price FROM customers c LEFT JOIN packages p ON c.package_id = p.id WHERE c.pppoe_username = ?", [$username]);
    
    if (!$customer) {
        sendMessage($chatId, "Pelanggan dengan PPPoE username {$username} tidak ditemukan.");
        return;
    }
    
    $invoice = fetchOne("SELECT * FROM invoices WHERE customer_id = ? ORDER BY due_date DESC LIMIT 1", [$customer['id']]);
    
    $message = "📄 Tagihan Pelanggan\n\n";
    $message .= "Nama: {$customer['name']}\n";
    $message .= "PPPoE: {$customer['pppoe_username']}\n";
    $message .= "Paket: " . ($customer['package_name'] ?? '-') . "\n";
    
    if ($invoice) {
        $status = $invoice['status'] === 'paid' ? 'Lunas' : 'Belum Lunas';
        $message .= "Invoice: {$invoice['invoice_number']}\n";
        $message .= "Jumlah: " . formatCurrency($invoice['amount']) . "\n";
        $message .= "Jatuh tempo: " . formatDate($invoice['due_date']) . "\n";
        $message .= "Status: {$status}\n";
    } else {
        $message .= "Belum ada invoice untuk pelanggan ini.\n";
    }
    
    $options = [];
    if ($invoice) {
        $buttons = [];
        $buttons[] = [
            [
                'text' => '📜 Daftar Invoice',
                'callback_data' => 'action=billing_help_invoice'
            ]
        ];
        if ($invoice['status'] !== 'paid') {
            $buttons[] = [
                [
                    'text' => '✅ Tandai Lunas',
                    'callback_data' => 'action=billing_mark_paid&inv=' . urlencode($invoice['invoice_number'])
                ]
            ];
        }
        $options['reply_markup'] = [
            'inline_keyboard' => $buttons
        ];
    }
    
    sendMessage($chatId, $message, $options);
}

function handleBillingInvoice($chatId, $args) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah billing hanya untuk admin.");
        return;
    }
    
    $username = trim($args);
    if ($username === '') {
        sendMessage($chatId, "Format: /billing_invoice &lt;pppoe_username&gt;");
        return;
    }
    
    $customer = fetchOne("SELECT * FROM customers WHERE pppoe_username = ?", [$username]);
    
    if (!$customer) {
        sendMessage($chatId, "Pelanggan dengan PPPoE username {$username} tidak ditemukan.");
        return;
    }
    
    $invoices = fetchAll("SELECT * FROM invoices WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5", [$customer['id']]);
    
    if (empty($invoices)) {
        sendMessage($chatId, "Belum ada invoice untuk pelanggan {$customer['name']}.");
        return;
    }
    
    $message = "📜 Daftar Invoice {$customer['name']}\n\n";
    $keyboard = [
        'inline_keyboard' => []
    ];
    
    foreach ($invoices as $inv) {
        $status = $inv['status'] === 'paid' ? 'Lunas' : 'Belum Lunas';
        $message .= "#{$inv['invoice_number']} - " . formatCurrency($inv['amount']) . " - {$status}\n";
        $message .= "Jatuh tempo: " . formatDate($inv['due_date']) . "\n\n";
        
        if ($inv['status'] !== 'paid') {
            $keyboard['inline_keyboard'][] = [
                [
                    'text' => "✅ {$inv['invoice_number']}",
                    'callback_data' => 'action=billing_mark_paid&inv=' . urlencode($inv['invoice_number'])
                ]
            ];
        }
    }
    
    if (empty($keyboard['inline_keyboard'])) {
        sendMessage($chatId, $message);
    } else {
        sendMessage($chatId, $message, ['reply_markup' => $keyboard]);
    }
}

function handleBillingIsolir($chatId, $args) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah billing hanya untuk admin.");
        return;
    }
    
    $username = trim($args);
    if ($username === '') {
        sendMessage($chatId, "Format: /billing_isolir &lt;pppoe_username&gt;");
        return;
    }
    
    $customer = fetchOne("SELECT * FROM customers WHERE pppoe_username = ?", [$username]);
    
    if (!$customer) {
        sendMessage($chatId, "Pelanggan dengan PPPoE username {$username} tidak ditemukan.");
        return;
    }
    
    if (isCustomerIsolated($customer['id'])) {
        sendMessage($chatId, "Pelanggan ini sudah dalam status isolir.");
        return;
    }
    
    if (isolateCustomer($customer['id'])) {
        sendMessage($chatId, "Pelanggan {$customer['name']} berhasil diisolir.");
    } else {
        sendMessage($chatId, "Gagal mengisolir pelanggan {$customer['name']}.");
    }
}

function handleBillingBukaIsolir($chatId, $args) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah billing hanya untuk admin.");
        return;
    }
    
    $username = trim($args);
    if ($username === '') {
        sendMessage($chatId, "Format: /billing_bukaisolir &lt;pppoe_username&gt;");
        return;
    }
    
    $customer = fetchOne("SELECT * FROM customers WHERE pppoe_username = ?", [$username]);
    
    if (!$customer) {
        sendMessage($chatId, "Pelanggan dengan PPPoE username {$username} tidak ditemukan.");
        return;
    }
    
    if (!isCustomerIsolated($customer['id'])) {
        sendMessage($chatId, "Pelanggan ini tidak dalam status isolir.");
        return;
    }
    
    if (unisolateCustomer($customer['id'])) {
        sendMessage($chatId, "Pelanggan {$customer['name']} berhasil dibuka isolirnya.");
    } else {
        sendMessage($chatId, "Gagal membuka isolir pelanggan {$customer['name']}.");
    }
}

function handleBillingLunas($chatId, $args, $silent = false) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah billing hanya untuk admin.");
        return;
    }
    
    $invoiceNumber = trim($args);
    if ($invoiceNumber === '') {
        sendMessage($chatId, "Format: /billing_lunas &lt;no_invoice&gt;");
        return;
    }
    
    $invoice = fetchOne("SELECT * FROM invoices WHERE invoice_number = ?", [$invoiceNumber]);
    
    if (!$invoice) {
        sendMessage($chatId, "Invoice {$invoiceNumber} tidak ditemukan.");
        return;
    }
    
    if ($invoice['status'] === 'paid') {
        sendMessage($chatId, "Invoice {$invoiceNumber} sudah berstatus lunas.");
        return;
    }
    
    $updateData = [
        'status' => 'paid',
        'updated_at' => date('Y-m-d H:i:s'),
        'paid_at' => date('Y-m-d H:i:s'),
        'payment_method' => 'Telegram Bot'
    ];
    
    update('invoices', $updateData, 'id = ?', [$invoice['id']]);
    
    if (isCustomerIsolated($invoice['customer_id'])) {
        unisolateCustomer($invoice['customer_id']);
    }
    
    logActivity('BOT_INVOICE_PAID', "Invoice: {$invoice['invoice_number']}");
    
    if (!$silent) {
        sendMessage($chatId, "Invoice {$invoiceNumber} berhasil ditandai lunas dan isolir pelanggan (jika ada) dibuka.");
    }
}

function handleBillingMarkPaidCallback($chatId, $data, $callbackQuery) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah billing hanya untuk admin.");
        return;
    }
    
    $invoiceNumber = $data['inv'] ?? '';
    if ($invoiceNumber === '') {
        sendMessage($chatId, "Data invoice tidak valid.");
        return;
    }
    
    handleBillingLunas($chatId, $invoiceNumber, true);
    
    $messageId = $callbackQuery['message']['message_id'] ?? null;
    if ($messageId === null) {
        sendMessage($chatId, "Invoice {$invoiceNumber} berhasil ditandai lunas.");
        return;
    }
    
    $invoice = fetchOne("SELECT * FROM invoices WHERE invoice_number = ?", [$invoiceNumber]);
    if (!$invoice) {
        sendMessage($chatId, "Invoice {$invoiceNumber} berhasil ditandai lunas.");
        return;
    }
    
    $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$invoice['customer_id']]);
    if (!$customer) {
        sendMessage($chatId, "Invoice {$invoiceNumber} berhasil ditandai lunas.");
        return;
    }
    
    $invoices = fetchAll("SELECT * FROM invoices WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5", [$customer['id']]);
    
    if (empty($invoices)) {
        editMessageText($chatId, $messageId, "Tidak ada invoice untuk pelanggan {$customer['name']}.");
        return;
    }
    
    $message = "📜 Daftar Invoice {$customer['name']}\n\n";
    $keyboard = [
        'inline_keyboard' => []
    ];
    
    foreach ($invoices as $inv) {
        $status = $inv['status'] === 'paid' ? 'Lunas' : 'Belum Lunas';
        $message .= "#{$inv['invoice_number']} - " . formatCurrency($inv['amount']) . " - {$status}\n";
        $message .= "Jatuh tempo: " . formatDate($inv['due_date']) . "\n\n";
        
        if ($inv['status'] !== 'paid') {
            $keyboard['inline_keyboard'][] = [
                [
                    'text' => "✅ {$inv['invoice_number']}",
                    'callback_data' => 'action=billing_mark_paid&inv=' . urlencode($inv['invoice_number'])
                ]
            ];
        }
    }
    
    if (empty($keyboard['inline_keyboard'])) {
        editMessageText($chatId, $messageId, $message);
    } else {
        editMessageText($chatId, $messageId, $message, $keyboard);
    }
}

function handleMikrotikMenu($chatId) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah MikroTik hanya untuk admin.");
        return;
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📊 Resource', 'callback_data' => 'action=mt_resource'],
                ['text' => '📡 Online PPPoE', 'callback_data' => 'action=mt_online']
            ],
            [
                ['text' => '📶 Ping IP/Host', 'callback_data' => 'action=mt_ping_help']
            ],
            [
                ['text' => '👤 PPPoE Commands', 'callback_data' => 'action=mt_pppoe_help'],
                ['text' => '🌐 Hotspot Commands', 'callback_data' => 'action=mt_hotspot_help']
            ]
        ]
    ];
    
    sendMessage($chatId, "Menu MikroTik Admin:", ['reply_markup' => $keyboard]);
}

function handleMikrotikResource($chatId) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah MikroTik hanya untuk admin.");
        return;
    }
    
    $res = mikrotikGetResource();
    if (!$res) {
        sendMessage($chatId, "Tidak dapat mengambil resource MikroTik. Cek konfigurasi di Settings.");
        return;
    }
    
    $cpu = $res['cpu-load'] ?? '-';
    $memTotal = $res['total-memory'] ?? '-';
    $memFree = $res['free-memory'] ?? '-';
    $hddTotal = $res['total-hdd-space'] ?? '-';
    $hddFree = $res['free-hdd-space'] ?? '-';
    $uptime = $res['uptime'] ?? '-';
    
    $message = "📊 Resource MikroTik\n\n";
    $message .= "CPU Load: {$cpu}%\n";
    $message .= "Memory: {$memFree} / {$memTotal}\n";
    $message .= "HDD: {$hddFree} / {$hddTotal}\n";
    $message .= "Uptime: {$uptime}\n";
    
    sendMessage($chatId, $message);
}

function handleMikrotikOnline($chatId) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah MikroTik hanya untuk admin.");
        return;
    }
    
    $sessions = mikrotikGetActiveSessions();
    if (!is_array($sessions)) {
        sendMessage($chatId, "Tidak dapat mengambil data PPPoE aktif.");
        return;
    }
    
    $total = count($sessions);
    if ($total === 0) {
        sendMessage($chatId, "Tidak ada PPPoE yang sedang online.");
        return;
    }
    
    $message = "📡 PPPoE Online: {$total}\n\n";
    
    $maxList = 20;
    $inlineMax = 10;
    $count = 0;
    $inlineCount = 0;
    
    $keyboard = [
        'inline_keyboard' => []
    ];
    
    foreach ($sessions as $s) {
        $name = $s['name'] ?? '-';
        $addr = $s['address'] ?? '-';
        $uptime = $s['uptime'] ?? '-';
        $message .= "- {$name} ({$addr}) up {$uptime}\n";
        $count++;
        
        if ($inlineCount < $inlineMax && $name !== '-') {
            $keyboard['inline_keyboard'][] = [
                [
                    'text' => "❌ {$name}",
                    'callback_data' => 'action=mt_pppoe_kick&name=' . urlencode($name)
                ]
            ];
            $inlineCount++;
        }
        
        if ($count >= $maxList) {
            break;
        }
    }
    
    if ($total > $maxList) {
        $message .= "\n...dan " . ($total - $maxList) . " user lain.";
    }
    
    $keyboard['inline_keyboard'][] = [
        [
            'text' => '🔄 Refresh',
            'callback_data' => 'action=mt_online'
        ]
    ];
    
    sendMessage($chatId, $message, ['reply_markup' => $keyboard]);
}

function handleMikrotikPing($chatId, $args) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah MikroTik hanya untuk admin.");
        return;
    }
    
    $target = trim($args);
    if ($target === '') {
        handleMikrotikPingHelp($chatId);
        return;
    }
    
    $result = mikrotikPing($target);
    if (!$result) {
        sendMessage($chatId, "Gagal melakukan ping dari MikroTik ke {$target}.");
        return;
    }
    
    $sent = $result['sent'];
    $recv = $result['received'];
    $loss = $result['loss'];
    $avg = $result['avg'] !== null ? round($result['avg'], 2) . " ms" : '-';
    
    $message = "📶 Ping dari MikroTik\n\n";
    $message .= "Target: {$target}\n";
    $message .= "Terkirim: {$sent}\n";
    $message .= "Diterima: {$recv}\n";
    $message .= "Loss: {$loss}%\n";
    $message .= "Rata-rata: {$avg}\n";
    
    sendMessage($chatId, $message);
}

function handleMikrotikPingHelp($chatId) {
    $message = "📶 Ping IP/Host dari MikroTik\n\n";
    $message .= "Gunakan perintah:\n";
    $message .= "/mt_ping &lt;ip/host&gt;\n\n";
    $message .= "Contoh:\n";
    $message .= "/mt_ping 8.8.8.8\n";
    $message .= "/mt_ping google.com";
    
    sendMessage($chatId, $message);
}

function handleMikrotikSetProfile($chatId, $args) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah MikroTik hanya untuk admin.");
        return;
    }
    
    $parts = preg_split('/\s+/', trim($args));
    if (count($parts) < 2) {
        $msg = "Ganti profile PPPoE\n\n";
        $msg .= "Format:\n";
        $msg .= "/mt_setprofile &lt;pppoe_username&gt; &lt;profile&gt;\n\n";
        $msg .= "Contoh:\n";
        $msg .= "/mt_setprofile pelanggan001 paket-10mbps";
        sendMessage($chatId, $msg);
        return;
    }
    
    $username = $parts[0];
    $profile = $parts[1];
    
    $ok = mikrotikSetProfile($username, $profile);
    if (!$ok) {
        sendMessage($chatId, "Gagal mengubah profile PPPoE {$username} ke {$profile}.");
        return;
    }
    
    mikrotikRemoveActiveSessionByName($username);
    
    sendMessage($chatId, "Profile PPPoE {$username} berhasil diubah ke {$profile} dan session aktifnya dihapus (user akan reconnect dengan profile baru).");
}

function handleMikrotikPppoeHelp($chatId) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah MikroTik hanya untuk admin.");
        return;
    }
    
    $message = "👤 Perintah PPPoE MikroTik\n\n";
    $message .= "/pppoe_list - Daftar user PPPoE\n";
    $message .= "/pppoe_add &lt;user&gt; &lt;pass&gt; &lt;profile&gt;\n";
    $message .= "/pppoe_del &lt;user&gt;\n";
    $message .= "/pppoe_disable &lt;user&gt;\n";
    $message .= "/pppoe_enable &lt;user&gt;\n";
    
    sendMessage($chatId, $message);
}

function handleMikrotikHotspotHelp($chatId) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah MikroTik hanya untuk admin.");
        return;
    }
    
    $message = "🌐 Perintah Hotspot MikroTik\n\n";
    $message .= "/hs_list - Daftar user Hotspot\n";
    $message .= "/hs_add &lt;user&gt; &lt;pass&gt; &lt;profile&gt;\n";
    $message .= "/hs_del &lt;user&gt;\n";
    
    sendMessage($chatId, $message);
}

function handlePppoeList($chatId) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah MikroTik hanya untuk admin.");
        return;
    }
    
    $users = mikrotikGetPppoeUsers();
    if (empty($users)) {
        sendMessage($chatId, "Tidak ada user PPPoE atau gagal mengambil data.");
        return;
    }
    
    $message = "👤 Daftar User PPPoE\n\n";
    $max = 30;
    $inlineMax = 10;
    $count = 0;
    $inlineCount = 0;
    
    $keyboard = [
        'inline_keyboard' => []
    ];
    
    foreach ($users as $u) {
        $name = $u['name'] ?? '-';
        $profile = $u['profile'] ?? '-';
        $disabled = $u['disabled'] ?? 'false';
        $status = $disabled === 'true' ? 'Nonaktif' : 'Aktif';
        $message .= "- {$name} ({$profile}) {$status}\n";
        $count++;
        
        if ($inlineCount < $inlineMax && $name !== '-') {
            $row = [];
            if ($disabled === 'true') {
                $row[] = [
                    'text' => "✅ {$name}",
                    'callback_data' => 'action=mt_pppoe_enable&name=' . urlencode($name)
                ];
            } else {
                $row[] = [
                    'text' => "🚫 {$name}",
                    'callback_data' => 'action=mt_pppoe_disable&name=' . urlencode($name)
                ];
            }
            $row[] = [
                'text' => "� {$name}",
                'callback_data' => 'action=mt_pppoe_del&name=' . urlencode($name)
            ];
            $keyboard['inline_keyboard'][] = $row;
            $inlineCount++;
        }
        
        if ($count >= $max) {
            break;
        }
    }
    
    if (count($users) > $max) {
        $message .= "\n...dan " . (count($users) - $max) . " user lain.";
    }
    
    if (empty($keyboard['inline_keyboard'])) {
        sendMessage($chatId, $message);
    } else {
        sendMessage($chatId, $message, ['reply_markup' => $keyboard]);
    }
}

function handlePppoeAdd($chatId, $args) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah MikroTik hanya untuk admin.");
        return;
    }
    
    $parts = preg_split('/\s+/', trim($args));
    if (count($parts) < 3) {
        $msg = "Tambah user PPPoE\n\n";
        $msg .= "Format:\n";
        $msg .= "/pppoe_add &lt;user&gt; &lt;pass&gt; &lt;profile&gt;\n\n";
        $msg .= "Contoh:\n";
        $msg .= "/pppoe_add pelanggan001 rahasia paket-10mbps";
        sendMessage($chatId, $msg);
        return;
    }
    
    $user = $parts[0];
    $pass = $parts[1];
    $profile = $parts[2];
    
    $result = mikrotikAddSecret($user, $pass, $profile, 'pppoe');
    if ($result['success']) {
        sendMessage($chatId, "User PPPoE {$user} berhasil ditambahkan dengan profile {$profile}.");
    } else {
        sendMessage($chatId, "Gagal menambah user PPPoE {$user}: {$result['message']}");
    }
}

function handlePppoeDel($chatId, $args, $silent = false) {
    if (!isAdminChat($chatId)) {
        if (!$silent) {
            sendMessage($chatId, "Perintah MikroTik hanya untuk admin.");
        }
        return;
    }
    
    $user = trim($args);
    if ($user === '') {
        if (!$silent) {
            sendMessage($chatId, "Format: /pppoe_del &lt;user&gt;");
        }
        return;
    }
    
    $secret = mikrotikGetSecretByName($user);
    if (!$secret || empty($secret['.id'])) {
        if (!$silent) {
            sendMessage($chatId, "User PPPoE {$user} tidak ditemukan.");
        }
        return;
    }
    
    $result = mikrotikDeleteSecret($secret['.id']);
    if ($result['success']) {
        if (!$silent) {
            sendMessage($chatId, "User PPPoE {$user} berhasil dihapus.");
        }
    } else {
        if (!$silent) {
            sendMessage($chatId, "Gagal menghapus user PPPoE {$user}: {$result['message']}");
        }
    }
}

function handlePppoeDisable($chatId, $args) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah MikroTik hanya untuk admin.");
        return;
    }
    
    $user = trim($args);
    if ($user === '') {
        sendMessage($chatId, "Format: /pppoe_disable &lt;user&gt;");
        return;
    }
    
    $secret = mikrotikGetSecretByName($user);
    if (!$secret || empty($secret['.id'])) {
        sendMessage($chatId, "User PPPoE {$user} tidak ditemukan.");
        return;
    }
    
    $result = mikrotikUpdateSecret($secret['.id'], ['disabled' => 'true']);
    if ($result['success']) {
        mikrotikRemoveActiveSessionByName($user);
        sendMessage($chatId, "User PPPoE {$user} berhasil dinonaktifkan dan jika online akan diputus.");
    } else {
        sendMessage($chatId, "Gagal menonaktifkan user PPPoE {$user}: {$result['message']}");
    }
}

function handlePppoeEnable($chatId, $args) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah MikroTik hanya untuk admin.");
        return;
    }
    
    $user = trim($args);
    if ($user === '') {
        sendMessage($chatId, "Format: /pppoe_enable &lt;user&gt;");
        return;
    }
    
    $secret = mikrotikGetSecretByName($user);
    if (!$secret || empty($secret['.id'])) {
        sendMessage($chatId, "User PPPoE {$user} tidak ditemukan.");
        return;
    }
    
    $result = mikrotikUpdateSecret($secret['.id'], ['disabled' => 'false']);
    if ($result['success']) {
        sendMessage($chatId, "User PPPoE {$user} berhasil diaktifkan.");
    } else {
        sendMessage($chatId, "Gagal mengaktifkan user PPPoE {$user}: {$result['message']}");
    }
}

function handlePppoeKickCallback($chatId, $data) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah MikroTik hanya untuk admin.");
        return;
    }
    
    $user = $data['name'] ?? '';
    if ($user === '') {
        sendMessage($chatId, "Data user PPPoE tidak valid.");
        return;
    }
    
    if (mikrotikRemoveActiveSessionByName($user)) {
        sendMessage($chatId, "Session PPPoE {$user} berhasil diputus.");
    } else {
        sendMessage($chatId, "Gagal memutus session PPPoE {$user}.");
    }
}

function handlePppoeDisableCallback($chatId, $data) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah MikroTik hanya untuk admin.");
        return;
    }
    
    $user = $data['name'] ?? '';
    if ($user === '') {
        sendMessage($chatId, "Data user PPPoE tidak valid.");
        return;
    }
    
    handlePppoeDisable($chatId, $user);
}

function handlePppoeEnableCallback($chatId, $data) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah MikroTik hanya untuk admin.");
        return;
    }
    
    $user = $data['name'] ?? '';
    if ($user === '') {
        sendMessage($chatId, "Data user PPPoE tidak valid.");
        return;
    }
    
    handlePppoeEnable($chatId, $user);
}

function handlePppoeDelCallback($chatId, $data, $callbackQuery) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah MikroTik hanya untuk admin.");
        return;
    }
    
    $user = $data['name'] ?? '';
    if ($user === '') {
        sendMessage($chatId, "Data user PPPoE tidak valid.");
        return;
    }
    
    handlePppoeDel($chatId, $user, true);
    
    $messageId = $callbackQuery['message']['message_id'] ?? null;
    if ($messageId === null) {
        sendMessage($chatId, "User PPPoE {$user} berhasil dihapus.");
        return;
    }
    
    $users = mikrotikGetPppoeUsers();
    if (empty($users)) {
        editMessageText($chatId, $messageId, "Tidak ada user PPPoE atau gagal mengambil data.");
        return;
    }
    
    $message = "👤 Daftar User PPPoE\n\n";
    $max = 30;
    $inlineMax = 10;
    $count = 0;
    $inlineCount = 0;
    
    $keyboard = [
        'inline_keyboard' => []
    ];
    
    foreach ($users as $u) {
        $name = $u['name'] ?? '-';
        $profile = $u['profile'] ?? '-';
        $disabled = $u['disabled'] ?? 'false';
        $status = $disabled === 'true' ? 'Nonaktif' : 'Aktif';
        $message .= "- {$name} ({$profile}) {$status}\n";
        $count++;
        
        if ($inlineCount < $inlineMax && $name !== '-') {
            $row = [];
            if ($disabled === 'true') {
                $row[] = [
                    'text' => "✅ {$name}",
                    'callback_data' => 'action=mt_pppoe_enable&name=' . urlencode($name)
                ];
            } else {
                $row[] = [
                    'text' => "🚫 {$name}",
                    'callback_data' => 'action=mt_pppoe_disable&name=' . urlencode($name)
                ];
            }
            $row[] = [
                'text' => "🗑 {$name}",
                'callback_data' => 'action=mt_pppoe_del&name=' . urlencode($name)
            ];
            $keyboard['inline_keyboard'][] = $row;
            $inlineCount++;
        }
        
        if ($count >= $max) {
            break;
        }
    }
    
    if (count($users) > $max) {
        $message .= "\n...dan " . (count($users) - $max) . " user lain.";
    }
    
    if (empty($keyboard['inline_keyboard'])) {
        editMessageText($chatId, $messageId, $message);
    } else {
        editMessageText($chatId, $messageId, $message, $keyboard);
    }
}

function handleHotspotList($chatId) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah MikroTik hanya untuk admin.");
        return;
    }
    
    $users = mikrotikGetHotspotUsers();
    if (empty($users)) {
        sendMessage($chatId, "Tidak ada user Hotspot atau gagal mengambil data.");
        return;
    }
    
    $message = "🌐 Daftar User Hotspot\n\n";
    $max = 30;
    $inlineMax = 10;
    $count = 0;
    $inlineCount = 0;
    
    $keyboard = [
        'inline_keyboard' => []
    ];
    
    foreach ($users as $u) {
        $name = $u['name'] ?? '-';
        $profile = $u['profile'] ?? '-';
        $message .= "- {$name} ({$profile})\n";
        $count++;
        
        if ($inlineCount < $inlineMax && $name !== '-') {
            $keyboard['inline_keyboard'][] = [
                [
                    'text' => "🗑 {$name}",
                    'callback_data' => 'action=mt_hotspot_del&name=' . urlencode($name)
                ]
            ];
            $inlineCount++;
        }
        
        if ($count >= $max) {
            break;
        }
    }
    
    if (count($users) > $max) {
        $message .= "\n...dan " . (count($users) - $max) . " user lain.";
    }
    
    if (empty($keyboard['inline_keyboard'])) {
        sendMessage($chatId, $message);
    } else {
        sendMessage($chatId, $message, ['reply_markup' => $keyboard]);
    }
}

function handleHotspotAdd($chatId, $args) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah MikroTik hanya untuk admin.");
        return;
    }
    
    $parts = preg_split('/\s+/', trim($args));
    if (count($parts) < 3) {
        $msg = "Tambah user Hotspot\n\n";
        $msg .= "Format:\n";
        $msg .= "/hs_add &lt;user&gt; &lt;pass&gt; &lt;profile&gt;\n\n";
        $msg .= "Contoh:\n";
        $msg .= "/hs_add user1 rahasia hotspot-3mbps";
        sendMessage($chatId, $msg);
        return;
    }
    
    $user = $parts[0];
    $pass = $parts[1];
    $profile = $parts[2];
    
    $ok = mikrotikAddHotspotUser($user, $pass, $profile);
    if ($ok) {
        sendMessage($chatId, "User Hotspot {$user} berhasil ditambahkan dengan profile {$profile}.");
    } else {
        sendMessage($chatId, "Gagal menambah user Hotspot {$user}.");
    }
}

function handleHotspotDel($chatId, $args, $silent = false) {
    if (!isAdminChat($chatId)) {
        if (!$silent) {
            sendMessage($chatId, "Perintah MikroTik hanya untuk admin.");
        }
        return;
    }
    
    $user = trim($args);
    if ($user === '') {
        if (!$silent) {
            sendMessage($chatId, "Format: /hs_del &lt;user&gt;");
        }
        return;
    }
    
    $ok = mikrotikDeleteHotspotUser($user);
    if ($ok) {
        if (!$silent) {
            sendMessage($chatId, "User Hotspot {$user} berhasil dihapus.");
        }
    } else {
        if (!$silent) {
            sendMessage($chatId, "Gagal menghapus user Hotspot {$user}.");
        }
    }
}

function handleHotspotDelCallback($chatId, $data, $callbackQuery) {
    if (!isAdminChat($chatId)) {
        sendMessage($chatId, "Perintah MikroTik hanya untuk admin.");
        return;
    }
    
    $user = $data['name'] ?? '';
    if ($user === '') {
        sendMessage($chatId, "Data user Hotspot tidak valid.");
        return;
    }
    
    handleHotspotDel($chatId, $user, true);
    
    $messageId = $callbackQuery['message']['message_id'] ?? null;
    if ($messageId === null) {
        sendMessage($chatId, "User Hotspot {$user} berhasil dihapus.");
        return;
    }
    
    $users = mikrotikGetHotspotUsers();
    if (empty($users)) {
        editMessageText($chatId, $messageId, "Tidak ada user Hotspot atau gagal mengambil data.");
        return;
    }
    
    $message = "🌐 Daftar User Hotspot\n\n";
    $max = 30;
    $inlineMax = 10;
    $count = 0;
    $inlineCount = 0;
    
    $keyboard = [
        'inline_keyboard' => []
    ];
    
    foreach ($users as $u) {
        $name = $u['name'] ?? '-';
        $profile = $u['profile'] ?? '-';
        $message .= "- {$name} ({$profile})\n";
        $count++;
        
        if ($inlineCount < $inlineMax && $name !== '-') {
            $keyboard['inline_keyboard'][] = [
                [
                    'text' => "🗑 {$name}",
                    'callback_data' => 'action=mt_hotspot_del&name=' . urlencode($name)
                ]
            ];
            $inlineCount++;
        }
        
        if ($count >= $max) {
            break;
        }
    }
    
    if (count($users) > $max) {
        $message .= "\n...dan " . (count($users) - $max) . " user lain.";
    }
    
    if (empty($keyboard['inline_keyboard'])) {
        editMessageText($chatId, $messageId, $message);
    } else {
        editMessageText($chatId, $messageId, $message, $keyboard);
    }
}
