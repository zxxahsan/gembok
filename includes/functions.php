<?php
/**
 * Helper Functions
 */

// Get Mikrotik settings from database (supports multi-router)
function getMikrotikSettings($routerId = null)
{
    // If routerId is provided, always fetch that specific router
    if ($routerId !== null && (int)$routerId > 0) {
        $router = fetchOne("SELECT * FROM routers WHERE id = ?", [$routerId]);
        if ($router) {
            return [
                'id' => $router['id'],
                'host' => $router['host'],
                'user' => $router['username'],
                'pass' => $router['password'],
                'port' => (int) $router['port'],
                'name' => $router['name']
            ];
        }
    }

    static $settings = null;
    if ($settings === null) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $activeRouterId = $_SESSION['active_router_id'] ?? null;

        $router = null;
        if ($activeRouterId) {
            $router = fetchOne("SELECT * FROM routers WHERE id = ?", [$activeRouterId]);
        }

        if (!$router) {
            // Try to get active router or first router
            $router = fetchOne("SELECT * FROM routers WHERE is_active = 1 LIMIT 1");
            if (!$router) {
                $router = fetchOne("SELECT * FROM routers LIMIT 1");
            }
        }

        if ($router) {
            $_SESSION['active_router_id'] = $router['id'];
            $settings = [
                'id' => $router['id'],
                'host' => $router['host'],
                'user' => $router['username'],
                'pass' => $router['password'],
                'port' => (int) $router['port'],
                'name' => $router['name']
            ];
            return $settings;
        }

        // Bridge migration/Fallback: Get from legacy settings table
        $settings = [
            'id' => 0,
            'host' => defined('MIKROTIK_HOST') ? MIKROTIK_HOST : '',
            'user' => defined('MIKROTIK_USER') ? MIKROTIK_USER : '',
            'pass' => defined('MIKROTIK_PASS') ? MIKROTIK_PASS : '',
            'port' => defined('MIKROTIK_PORT') ? MIKROTIK_PORT : 8728,
            'name' => 'Default Router'
        ];

        $dbSettings = fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('MIKROTIK_HOST', 'MIKROTIK_USER', 'MIKROTIK_PASS', 'MIKROTIK_PORT')");
        foreach ($dbSettings as $s) {
            switch ($s['setting_key']) {
                case 'MIKROTIK_HOST':
                    $settings['host'] = $s['setting_value'];
                    break;
                case 'MIKROTIK_USER':
                    $settings['user'] = $s['setting_value'];
                    break;
                case 'MIKROTIK_PASS':
                    $settings['pass'] = $s['setting_value'];
                    break;
                case 'MIKROTIK_PORT':
                    $settings['port'] = (int) $s['setting_value'];
                    break;
            }
        }
    }
    return $settings;
}

// Get all routers from database
function getAllRouters()
{
    return fetchAll("SELECT * FROM routers ORDER BY name ASC");
}

// Format currency
function formatCurrency($amount)
{
    $amount = is_numeric($amount) ? $amount : 0;
    return CURRENCY_SYMBOL . ' ' . number_format((float) $amount, 0, ',', '.');
}

// Format date
function formatDate($date, $format = 'd M Y')
{
    if (!$date)
        return '-';
    $time = strtotime($date);
    return $time ? date($format, $time) : '-';
}

// Generate invoice number
function generateInvoiceNumber()
{
    $prefix = INVOICE_PREFIX;
    $start = INVOICE_START;

    $lastInvoice = fetchOne("SELECT invoice_number FROM invoices ORDER BY id DESC LIMIT 1");

    if ($lastInvoice) {
        $lastNum = (int) str_replace($prefix, '', $lastInvoice['invoice_number']);
        $newNum = $lastNum + 1;
    } else {
        $newNum = $start;
    }

    return $prefix . str_pad($newNum, 6, '0', STR_PAD_LEFT);
}

function sendWhatsApp($phone, $message)
{
    require_once 'whatsapp.php';

    // Get default WhatsApp gateway from settings
    $defaultGateway = fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", ['DEFAULT_WHATSAPP_GATEWAY'])['setting_value'] ?? 'fonnte';

    // Format phone number (62 format)
    if (substr($phone, 0, 2) === '08') {
        $phone = '62' . substr($phone, 1);
    }

    // Send using selected gateway
    $result = sendWhatsAppMessage($phone, $message, $defaultGateway);

    return $result['success'] ?? false;
}

function getCustomerDueDate($customer, $baseDate = null)
{
    $baseTimestamp = $baseDate ? strtotime($baseDate) : time();
    $year = date('Y', $baseTimestamp);
    $month = date('m', $baseTimestamp);
    $day = isset($customer['isolation_date']) ? (int) $customer['isolation_date'] : 20;
    if ($day < 1) {
        $day = 1;
    }
    if ($day > 28) {
        $day = 28;
    }
    $lastDay = (int) date('t', strtotime($year . '-' . $month . '-01'));
    if ($day > $lastDay) {
        $day = $lastDay;
    }
    return date('Y-m-d', strtotime($year . '-' . $month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT)));
}

function logError($message)
{
    $logFile = __DIR__ . '/../logs/error.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] ERROR: {$message}\n";

    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Log activity
function logActivity($action, $details = '')
{
    $logFile = __DIR__ . '/../logs/activity.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $user = $_SESSION['admin']['username'] ?? 'guest';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $logMessage = "[{$timestamp}] [{$user}] [{$ip}] {$action} - {$details}\n";

    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Redirect
function redirect($url)
{
    header("Location: {$url}");
    exit;
}

// Flash message
function setFlash($type, $message)
{
    $_SESSION['flash'][$type] = $message;
}

function getFlash($type)
{
    $message = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);
    return $message;
}

function hasFlash($type)
{
    return isset($_SESSION['flash'][$type]);
}

// Sanitize input
function sanitize($input)
{
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Validate email
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Generate random string with charset options
function generateRandomString($length = 10, $type = 'mixed')
{
    switch ($type) {
        case 'numeric':
        case 'num':
            $x = '0123456789';
            break;
        case 'alpha':
        case 'low':
            $x = 'abcdefghijklmnopqrstuvwxyz';
            break;
        case 'up':
            $x = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            break;
        case 'alphanumeric':
        case 'mixed':
            $x = '23456789abcdefghijkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
            break; // Avoid ambiguous chars
        default:
            $x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            break;
    }
    
    $str = '';
    for ($i = 0; $i < $length; $i++) {
        $str .= $x[rand(0, strlen($x) - 1)];
    }
    return $str;
}

// Mikhmon Metadata Helpers
function formatMikhmonComment($price, $validity, $profile)
{
    // Format: vc-user-dd-mm-yy (Price: Rp 5.000, Validity: 1d)
    // Note: Mikhmon often uses specific patterns like uct-ddmmyy-price
    $date = date('d/m/y');
    return "price:{$price},validity:{$validity},profile:{$profile},date:{$date}";
}

function parseMikhmonComment($comment)
{
    $data = [
        'price' => 0,
        'validity' => '-',
        'profile' => '-',
        'date' => '-',
        'raw' => $comment
    ];

    if (empty($comment))
        return $data;

    // 1. Try existing key:value format (e.g. price:5000,validity:1d,date=...)
    // Note: Mikhmon uses both : and =
    if (strpos($comment, 'price:') !== false || strpos($comment, 'price=') !== false) {
        $parts = preg_split('/[, ]+/', $comment);
        foreach ($parts as $part) {
            $kv = preg_split('/[:=]/', $part, 2);
            if (count($kv) === 2) {
                $itemKey = trim($kv[0]);
                $itemVal = trim($kv[1]);
                if (isset($data[$itemKey])) {
                    $data[$itemKey] = $itemVal;
                }
            }
        }
        return $data;
    }

    // 2. Try Standard Mikhmon Format: Date - Code - Price - Profile - Validity
    $parts = array_map('trim', explode('-', $comment));
    if (count($parts) >= 5) {
        $data['date'] = $parts[0];
        $data['price'] = preg_replace('/[^0-9]/', '', $parts[2]);
        $data['profile'] = $parts[3];
        $data['validity'] = $parts[4];
        return $data;
    }

    // 3. Fallback search using Regex - BE STRICTER
    // Prioritize Rp or price: prefixes. If none, only accept numeric strings if they are reasonable (< 1,000,000)
    // and not too long (vouchers rarely cost billions)

    $foundPrice = 0;

    // Pattern A: Explicit Price Prefix (Rp, price:, parent:)
    if (preg_match('/(?:price[:=]|Rp\.?\s?|rp\.?\s?|parent[:=])\s?(\d{1,3}(?:\.\d{3})*|\d{3,})/i', $comment, $matches)) {
        $foundPrice = str_replace('.', '', $matches[1]);
    }
    // Pattern B: Bare number at the end or surrounded by spaces (only if Pattern A failed)
    elseif (preg_match('/(?:\s|^)(\d{3,7})(?:\s|$|,)/', $comment, $matches)) {
        $tempPrice = $matches[1];
        // Sanity check: Mikhmon voucher prices are usually under 1,000,000
        if ((int) $tempPrice < 1000000) {
            $foundPrice = $tempPrice;
        }
    }

    if ($foundPrice) {
        $data['price'] = (int) $foundPrice;
    }

    // Date - Be careful not to pick up the same big number
    if (preg_match('/(?:date[:=]|^|\s)([a-z]{3}\/\d{2}\/\d{4}\s\d{2}:\d{2}:\d{2})/i', $comment, $matches)) {
        $data['date'] = $matches[1];
    } elseif (preg_match('/(\d{2}[-\/\.]\d{2}[-\/\.]\d{2,4})/', $comment, $matches)) {
        $data['date'] = $matches[1];
    }

    return $data;
}

function parseHotspotProfileComment($comment)
{
    $price = 0;

    if (empty($comment)) {
        return 0;
    }

    // 1. Try 'parent:PRICE' format (used by this app)
    if (strpos($comment, 'parent:') !== false) {
        // Extract everything after parent:
        $parts = explode('parent:', $comment);
        if (isset($parts[1])) {
            // Take the number immediately following parent:
            $val = trim($parts[1]);
            // If comma separated like parent:5000,other:value
            $valParts = explode(',', $val);
            $price = preg_replace('/[^0-9]/', '', $valParts[0]);
            return (int) $price;
        }
    }

    // 2. Try explicit 'price:' format
    if (preg_match('/price[:=]\s?(\d+)/i', $comment, $matches)) {
        return (int) $matches[1];
    }

    // 3. Try formatted currency format (Rp 5.000)
    if (preg_match('/Rp\.?\s?(\d{1,3}(?:\.\d{3})*|\d{3,})/i', $comment, $matches)) {
        $clean = str_replace('.', '', $matches[1]);
        return (int) $clean;
    }

    // 4. Try bare numeric price (with sanity check)
    // Mikhmon sometimes just puts the price. But we must ignore timestamps (YYYYMMDD...)
    if (preg_match('/(?:\s|^)(\d{3,7})(?:\s|$|,)/', $comment, $matches)) {
        $val = (int) $matches[1];
        // Sanity check: if it looks like a date/timestamp 
        // (e.g. starts with 202, 201 or has 8+ digits), ignore it
        if ($val < 1000000 && strlen($matches[1]) <= 7) {
            return $val;
        }
    }

    return 0;
}

// Check if customer is isolated
function isCustomerIsolated($customerId)
{
    $customer = fetchOne("SELECT status FROM customers WHERE id = ?", [$customerId]);
    return $customer && $customer['status'] === 'isolated';
}

// Isolate customer
function isolateCustomer($customerId)
{
    $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
    if (!$customer) {
        return false;
    }

    // Update status
    update('customers', ['status' => 'isolated'], 'id = ?', [$customerId]);

    // Update MikroTik profile
    $package = fetchOne("SELECT * FROM packages WHERE id = ?", [$customer['package_id']]);
    if ($package && !empty($customer['pppoe_username'])) {
        // Call MikroTik API to change profile on assigned router
        $result = mikrotikSetProfile($customer['pppoe_username'], $package['profile_isolir'], $customer['router_id']);

        // Send WhatsApp notification
        $message = "Halo {$customer['name']},\n\nPembayaran internet Anda sudah melewati tanggal jatuh tempo.\n\nMohon segera lakukan pembayaran untuk mengaktifkan kembali koneksi internet Anda.\n\nTerima kasih.";
        sendWhatsApp($customer['phone'], $message);
    }

    logActivity('ISOLATE_CUSTOMER', "Customer ID: {$customerId}");

    return true;
}

// Unisolate customer
function unisolateCustomer($customerId)
{
    $customer = fetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
    if (!$customer) {
        return false;
    }

    // Update status
    update('customers', ['status' => 'active'], 'id = ?', [$customerId]);

    // Update MikroTik profile
    $package = fetchOne("SELECT * FROM packages WHERE id = ?", [$customer['package_id']]);
    if ($package && !empty($customer['pppoe_username'])) {
        // Call MikroTik API to change profile on assigned router
        $result = mikrotikSetProfile($customer['pppoe_username'], $package['profile_normal'], $customer['router_id']);
    }

    logActivity('UNISOLATE_CUSTOMER', "Customer ID: {$customerId}");

    return true;
}

// MikroTik API functions - Binary Protocol Implementation
/**
 * Get a persistent MikroTik connection for the remainder of the request
 */
function getMikrotikConnection($routerId = null)
{
    static $sockets = [];
    static $lastHosts = [];

    $mikrotik = getMikrotikSettings($routerId);
    $rId = (int)($mikrotik['id'] ?? 0);
    $currentHost = $mikrotik['host'] . ':' . $mikrotik['port'];

    // If socket is dead or doesn't exist for this router, reconnect
    if (!isset($sockets[$rId]) || !is_resource($sockets[$rId]) || feof($sockets[$rId]) || ($lastHosts[$rId] ?? '') !== $currentHost) {
        if (isset($sockets[$rId]) && is_resource($sockets[$rId])) {
            @fclose($sockets[$rId]);
        }

        $sockets[$rId] = mikrotikConnect($routerId);
        if ($sockets[$rId]) {
            if (!mikrotikLogin($sockets[$rId], $routerId)) {
                @fclose($sockets[$rId]);
                $sockets[$rId] = null;
            } else {
                $lastHosts[$rId] = $currentHost;
            }
        }
    }

    return $sockets[$rId];
}

function mikrotikConnect($routerId = null)
{
    $mikrotik = getMikrotikSettings($routerId);

    if (empty($mikrotik['host']) || empty($mikrotik['user'])) {
        logError("MikroTik config incomplete: host or user is empty");
        return false;
    }

    $socket = @fsockopen($mikrotik['host'], $mikrotik['port'], $errno, $errstr, 5);

    if (!$socket) {
        logError("MikroTik connection failed: $errstr ($errno)");
        return false;
    }

    stream_set_timeout($socket, 5);
    stream_set_blocking($socket, true);

    return $socket;
}

function mikrotikLogin($socket, $routerId = null)
{
    $mikrotik = getMikrotikSettings($routerId);
    $username = $mikrotik['user'];
    $password = $mikrotik['pass'];

    // Method 1: Plain text password (RouterOS 6.43+)
    // This is the preferred method for modern RouterOS
    mikrotikWrite($socket, '/login');
    mikrotikWrite($socket, '=name=' . $username);
    mikrotikWrite($socket, '=password=' . $password);
    mikrotikWrite($socket, ''); // End sentence

    $response = mikrotikReadSentence($socket);

    // Check if login succeeded
    foreach ($response as $word) {
        if ($word === '!done') {
            return true;
        }
    }

    // If plain text method failed, try MD5 challenge-response (older RouterOS)
    // Reconnect is needed, but we'll try a different approach

    // Method 2: MD5 Challenge-Response (RouterOS pre-6.43)
    mikrotikWrite($socket, '/login');
    mikrotikWrite($socket, ''); // End sentence

    $response = mikrotikReadSentence($socket);

    if (empty($response)) {
        return false;
    }

    // Extract challenge from response
    $challenge = null;
    foreach ($response as $word) {
        if (strpos($word, '=ret=') === 0) {
            $challenge = substr($word, 5);
            break;
        }
    }

    if (!$challenge) {
        return false;
    }

    // Calculate MD5 hash
    $hash = md5(chr(0) . $password . pack('H*', $challenge), true);

    // Send login with hash
    mikrotikWrite($socket, '/login');
    mikrotikWrite($socket, '=name=' . $username);
    mikrotikWrite($socket, '=response=' . bin2hex($hash));
    mikrotikWrite($socket, ''); // End sentence

    // Read response
    $response = mikrotikReadSentence($socket);

    // Check if login succeeded
    foreach ($response as $word) {
        if ($word === '!done') {
            return true;
        }
    }

    return false;
}

function mikrotikWrite($socket, $word)
{
    if ($word === '') {
        fwrite($socket, chr(0));
        return;
    }

    $len = strlen($word);
    $encodedLen = '';

    if ($len < 0x80) {
        $encodedLen = chr($len);
    } elseif ($len < 0x4000) {
        $encodedLen = chr(($len >> 8) | 0x80) . chr($len & 0xFF);
    } elseif ($len < 0x200000) {
        $encodedLen = chr(($len >> 16) | 0xC0) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
    } elseif ($len < 0x10000000) {
        $encodedLen = chr(($len >> 24) | 0xE0) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
    } else {
        $encodedLen = chr(0xF0) . chr(($len >> 24) & 0xFF) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
    }

    fwrite($socket, $encodedLen . $word);
}

function mikrotikWriteCommand($socket, $command)
{
    mikrotikWrite($socket, $command);
}

function mikrotikWriteWord($socket, $word)
{
    mikrotikWrite($socket, $word);
}

function mikrotikReadSentence($socket)
{
    $words = [];
    while (true) {
        $byte = fread($socket, 1);
        if ($byte === false || $byte === '')
            break;

        $byte = ord($byte);
        $len = 0;

        if (($byte & 0x80) == 0x00) {
            $len = $byte;
        } elseif (($byte & 0xC0) == 0x80) {
            $len = (($byte & 0x3F) << 8) + ord(fread($socket, 1));
        } elseif (($byte & 0xE0) == 0xC0) {
            $len = (($byte & 0x1F) << 16) + (ord(fread($socket, 1)) << 8) + ord(fread($socket, 1));
        } elseif (($byte & 0xF0) == 0xE0) {
            $len = (($byte & 0x0F) << 24) + (ord(fread($socket, 1)) << 16) + (ord(fread($socket, 1)) << 8) + ord(fread($socket, 1));
        } elseif (($byte & 0xF8) == 0xF0) {
            $len = (ord(fread($socket, 1)) << 24) + (ord(fread($socket, 1)) << 16) + (ord(fread($socket, 1)) << 8) + ord(fread($socket, 1));
        }

        if ($len == 0) {
            break;
        }

        $word = '';
        $remaining = $len;
        while ($remaining > 0) {
            $chunk = fread($socket, $remaining);
            if ($chunk === false || $chunk === '')
                break;
            $word .= $chunk;
            $remaining -= strlen($chunk);
        }

        $words[] = $word;
    }

    return $words;
}

function mikrotikRead($socket)
{
    return mikrotikReadSentence($socket);
}

function mikrotikQuery($command, $params = [])
{
    $socket = getMikrotikConnection();
    if (!$socket) {
        return false;
    }

    // Send command
    mikrotikWrite($socket, $command);
    foreach ($params as $key => $value) {
        mikrotikWrite($socket, '=' . $key . '=' . $value);
    }
    mikrotikWrite($socket, ''); // End sentence

    // Read response — mikrotikRead() returns an array of words
    $allWords = [];
    $done = false;
    $timeout = time() + 10;

    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    return mikrotikParseResponse($allWords);
}

function mikrotikParseResponse($response)
{
    // $response is an array of words from binary protocol
    $result = [];

    foreach ($response as $word) {
        if ($word === '!done' || strpos($word, '!trap') === 0) {
            break;
        }

        if (strpos($word, '=') === 0) {
            $word = substr($word, 1); // Remove leading '='
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $result[$parts[0]] = $parts[1];
            }
        }
    }

    return $result;
}

function mikrotikSetProfile($username, $profile, $routerId = null)
{
    $socket = getMikrotikConnection($routerId);
    if (!$socket) {
        return false;
    }

    // Find user and get their secret ID
    mikrotikWrite($socket, '/ppp/secret/print');
    mikrotikWrite($socket, '?name=' . $username);
    mikrotikWrite($socket, ''); // End sentence

    // Read ALL sentences until !done
    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $parsed = mikrotikParseUsers($allWords);

    if (empty($parsed)) {
        return false;
    }

    // Get the secret ID from first user
    $secretId = $parsed[0]['.id'] ?? null;
    if (!$secretId) {
        return false;
    }

    // Update profile using secret ID
    mikrotikWrite($socket, '/ppp/secret/set');
    mikrotikWrite($socket, '=.id=' . $secretId);
    mikrotikWrite($socket, '=profile=' . $profile);
    mikrotikWrite($socket, ''); // End sentence

    // Read response to confirm
    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    return true;
}

function mikrotikGetPppoeUsers()
{
    $socket = getMikrotikConnection();
    if (!$socket) {
        return [];
    }

    mikrotikWrite($socket, '/ppp/secret/print');
    mikrotikWrite($socket, ''); // End sentence

    // Read ALL sentences until !done
    $allWords = [];
    $done = false;
    $timeout = time() + 30; // 30 second timeout for large user lists

    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) {
            break;
        }

        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    return mikrotikParseUsers($allWords);
}

function mikrotikParseUsers($response)
{
    // $response is now an array of words from binary protocol
    // Format: =key=value (e.g., =name=user1)
    $users = [];
    $currentUser = [];

    foreach ($response as $word) {
        if ($word === '!done') {
            if (!empty($currentUser)) {
                $users[] = $currentUser;
                $currentUser = [];
            }
            break;
        }

        if ($word === '!re') {
            if (!empty($currentUser)) {
                $users[] = $currentUser;
                $currentUser = [];
            }
        } elseif (strpos($word, '=') === 0) {
            // Format: =key=value, so remove first '=' then split
            $word = substr($word, 1); // Remove leading '='
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $currentUser[$parts[0]] = $parts[1];
            }
        }
    }

    return $users;
}

// Add PPPoE Secret
function mikrotikAddSecret($name, $password, $profile = 'default', $service = 'pppoe')
{
    $socket = getMikrotikConnection();
    if (!$socket) {
        return ['success' => false, 'message' => 'Cannot connect to MikroTik'];
    }

    mikrotikWrite($socket, '/ppp/secret/add');
    mikrotikWrite($socket, '=name=' . $name);
    mikrotikWrite($socket, '=password=' . $password);
    mikrotikWrite($socket, '=profile=' . $profile);
    mikrotikWrite($socket, '=service=' . $service);
    mikrotikWrite($socket, ''); // End sentence

    $response = mikrotikReadSentence($socket);

    foreach ($response as $word) {
        if ($word === '!done') {
            return ['success' => true, 'message' => 'User added successfully'];
        }
        if (strpos($word, '!trap') === 0) {
            $message = 'Unknown error';
            foreach ($response as $w) {
                if (strpos($w, '=message=') === 0) {
                    $message = substr($w, 9);
                    break;
                }
            }
            return ['success' => false, 'message' => $message];
        }
    }

    return ['success' => false, 'message' => 'Unknown response'];
}

// Update PPPoE Secret
function mikrotikUpdateSecret($id, $data)
{
    $socket = getMikrotikConnection();
    if (!$socket) {
        return ['success' => false, 'message' => 'Cannot connect to MikroTik'];
    }

    mikrotikWrite($socket, '/ppp/secret/set');
    mikrotikWrite($socket, '=.id=' . $id);

    if (isset($data['name']))
        mikrotikWrite($socket, '=name=' . $data['name']);
    if (isset($data['password']))
        mikrotikWrite($socket, '=password=' . $data['password']);
    if (isset($data['profile']))
        mikrotikWrite($socket, '=profile=' . $data['profile']);
    if (isset($data['service']))
        mikrotikWrite($socket, '=service=' . $data['service']);
    if (isset($data['disabled']))
        mikrotikWrite($socket, '=disabled=' . $data['disabled']);

    mikrotikWrite($socket, ''); // End sentence

    $response = mikrotikReadSentence($socket);

    foreach ($response as $word) {
        if ($word === '!done') {
            return ['success' => true, 'message' => 'User updated successfully'];
        }
        if (strpos($word, '!trap') === 0) {
            $message = 'Unknown error';
            foreach ($response as $w) {
                if (strpos($w, '=message=') === 0) {
                    $message = substr($w, 9);
                    break;
                }
            }
            return ['success' => false, 'message' => $message];
        }
    }

    return ['success' => false, 'message' => 'Unknown response'];
}

// Delete PPPoE Secret
function mikrotikDeleteSecret($id)
{
    $socket = getMikrotikConnection();
    if (!$socket) {
        return ['success' => false, 'message' => 'Cannot connect to MikroTik'];
    }

    mikrotikWrite($socket, '/ppp/secret/remove');
    mikrotikWrite($socket, '=.id=' . $id);
    mikrotikWrite($socket, ''); // End sentence

    $response = mikrotikReadSentence($socket);

    foreach ($response as $word) {
        if ($word === '!done') {
            return ['success' => true, 'message' => 'User deleted successfully'];
        }
        if (strpos($word, '!trap') === 0) {
            $message = 'Unknown error';
            foreach ($response as $w) {
                if (strpos($w, '=message=') === 0) {
                    $message = substr($w, 9);
                    break;
                }
            }
            return ['success' => false, 'message' => $message];
        }
    }

    return ['success' => false, 'message' => 'Unknown response'];
}

// Get Active PPPoE Sessions (users currently connected)
function mikrotikGetActiveSessions()
{
    $socket = getMikrotikConnection();
    if (!$socket) {
        return [];
    }

    mikrotikWrite($socket, '/ppp/active/print');
    mikrotikWrite($socket, ''); // End sentence

    // Read ALL sentences until !done
    $allWords = [];
    $done = false;
    $timeout = time() + 30; // 30 second timeout for large user lists

    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) {
            break;
        }

        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    // Parse active sessions
    $sessions = [];
    $currentSession = [];

    foreach ($allWords as $word) {
        if ($word === '!done') {
            if (!empty($currentSession)) {
                $sessions[] = $currentSession;
            }
            break;
        }

        if ($word === '!re') {
            if (!empty($currentSession)) {
                $sessions[] = $currentSession;
                $currentSession = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $currentSession[$parts[0]] = $parts[1];
            }
        }
    }

    return $sessions;
}

function mikrotikGetProfiles()
{
    $socket = getMikrotikConnection();
    if (!$socket) {
        return [];
    }

    logActivity('MIKROTIK_API', "Fetching PPPoE profiles");

    // Send print command
    mikrotikWrite($socket, '/ppp/profile/print');

    // End sentence
    mikrotikWrite($socket, '');

    // Read ALL sentences until !done (MikroTik sends multiple sentences)
    $allWords = [];
    $done = false;
    $timeout = time() + 10; // 10 second timeout

    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) {
            break;
        }

        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $profiles = mikrotikParseProfiles($allWords);

    return $profiles;
}

function mikrotikParseProfiles($response)
{
    // $response is now an array of words from binary protocol
    // Format: =key=value (e.g., =name=default)
    $profiles = [];
    $currentProfile = [];

    foreach ($response as $word) {
        if ($word === '!done') {
            if (!empty($currentProfile)) {
                $profiles[] = $currentProfile;
                $currentProfile = [];
            }
            break;
        }

        if ($word === '!re') {
            if (!empty($currentProfile)) {
                $profiles[] = $currentProfile;
                $currentProfile = [];
            }
        } elseif (strpos($word, '=') === 0) {
            // Format: =key=value, so remove first '=' then split
            $word = substr($word, 1); // Remove leading '='
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $currentProfile[$parts[0]] = $parts[1];
            }
        }
    }

    return $profiles;
}

// Get MikroTik Hotspot Servers
function mikrotikGetHotspotServers()
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return [];

    mikrotikWrite($socket, '/ip/hotspot/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $servers = [];
    $current = [];
    foreach ($allWords as $word) {
        if ($word === '!re') {
            if (!empty($current)) {
                $servers[] = $current;
                $current = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2)
                $current[$parts[0]] = $parts[1];
        }
    }
    return $servers;
}

// Get MikroTik Hotspot User Profiles
function mikrotikGetHotspotProfiles()
{
    $socket = getMikrotikConnection();
    if (!$socket) {
        return [];
    }

    // Get hotspot user profiles
    mikrotikWrite($socket, '/ip/hotspot/user/profile/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;

    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $profiles = [];
    $currentProfile = [];

    foreach ($allWords as $word) {
        if ($word === '!re' || $word === '!done') {
            if (!empty($currentProfile)) {
                // Ensure default keys
                $currentProfile['name'] = $currentProfile['name'] ?? '';
                $currentProfile['comment'] = $currentProfile['comment'] ?? '';
                $currentProfile['shared-users'] = $currentProfile['shared-users'] ?? '1';
                $currentProfile['rate-limit'] = $currentProfile['rate-limit'] ?? '';
                $currentProfile['.id'] = $currentProfile['.id'] ?? '';

                $profiles[] = $currentProfile;
                $currentProfile = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $currentProfile[$parts[0]] = $parts[1];
            }
        }
    }

    return $profiles;
}

// Add MikroTik Hotspot User with Mikhmon Metadata support
function mikrotikAddHotspotUser($username, $password, $profile = 'default', $extraData = [])
{
    $socket = getMikrotikConnection();
    if (!$socket) {
        return false;
    }

    // Add hotspot user
    mikrotikWrite($socket, '/ip/hotspot/user/add');
    mikrotikWrite($socket, '=name=' . $username);
    mikrotikWrite($socket, '=password=' . $password);
    mikrotikWrite($socket, '=profile=' . $profile);

    // Add extra parameters if provided
    if (isset($extraData['server'])) {
        mikrotikWrite($socket, '=server=' . $extraData['server']);
    }
    if (isset($extraData['limit-uptime'])) {
        mikrotikWrite($socket, '=limit-uptime=' . $extraData['limit-uptime']);
    }
    if (isset($extraData['limit-bytes-total'])) {
        mikrotikWrite($socket, '=limit-bytes-total=' . $extraData['limit-bytes-total']);
    }

    // Mikhmon Style Comment
    $comment = $extraData['comment'] ?? "parent:{$profile}";
    mikrotikWrite($socket, '=comment=' . $comment);

    mikrotikWrite($socket, '');

    $response = mikrotikReadSentence($socket);

    // Check for success (no !trap error)
    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0) {
            return false;
        }
    }

    return true;
}

// Delete MikroTik Hotspot User
function mikrotikDeleteHotspotUser($username)
{
    $socket = getMikrotikConnection();
    if (!$socket) {
        return false;
    }

    // Find user first
    mikrotikWrite($socket, '/ip/hotspot/user/print');
    mikrotikWrite($socket, '?name=' . $username);
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;

    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    // Find the .id
    $userId = null;
    foreach ($allWords as $word) {
        if (strpos($word, '=.id=') === 0) {
            $userId = substr($word, 5);
            break;
        }
    }

    if (!$userId) {
        return false; // User not found
    }

    // Remove user
    mikrotikWrite($socket, '/ip/hotspot/user/remove');
    mikrotikWrite($socket, '=.id=' . $userId);
    mikrotikWrite($socket, '');

    $response = mikrotikReadSentence($socket);

    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0) {
            return false;
        }
    }

    return true;
}

// Toggle Hotspot User (Enable/Disable)
function mikrotikToggleHotspotUser($username, $status)
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return false;

    // Find user first
    mikrotikWrite($socket, '/ip/hotspot/user/print');
    mikrotikWrite($socket, '?name=' . $username);
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 5;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    // Find the .id
    $userId = null;
    foreach ($allWords as $word) {
        if (strpos($word, '=.id=') === 0) {
            $userId = substr($word, 5);
            break;
        }
    }

    if (!$userId) {
        return false;
    }

    // Toggle
    mikrotikWrite($socket, '/ip/hotspot/user/set');
    mikrotikWrite($socket, '=.id=' . $userId);
    mikrotikWrite($socket, '=disabled=' . ($status === 'enable' ? 'no' : 'yes'));
    mikrotikWrite($socket, '');

    $response = mikrotikReadSentence($socket);

    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0)
            return false;
    }

    return true;
}

// Get MikroTik Hotspot Users
function mikrotikGetHotspotUsers()
{
    $socket = getMikrotikConnection();
    if (!$socket) {
        return [];
    }

    // Get hotspot users
    mikrotikWrite($socket, '/ip/hotspot/user/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;

    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    // Do NOT fclose() — this is a shared persistent connection

    $users = [];
    $currentUser = [];

    foreach ($allWords as $word) {
        if ($word === '!re' || $word === '!done') {
            if (!empty($currentUser)) {
                // Ensure default keys
                $currentUser['name'] = $currentUser['name'] ?? '';
                $currentUser['profile'] = $currentUser['profile'] ?? 'default';
                $currentUser['comment'] = $currentUser['comment'] ?? '';
                $currentUser['limit-uptime'] = $currentUser['limit-uptime'] ?? '∞';
                $currentUser['limit-bytes-total'] = $currentUser['limit-bytes-total'] ?? 0;
                $currentUser['uptime'] = $currentUser['uptime'] ?? '0s';
                $currentUser['bytes-in'] = $currentUser['bytes-in'] ?? 0;
                $currentUser['bytes-out'] = $currentUser['bytes-out'] ?? 0;

                $users[] = $currentUser;
                $currentUser = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $currentUser[$parts[0]] = $parts[1];
            }
        }
    }

    return $users;
}

// Get GenieACS settings from database (override config.php)
function getGenieacsSettings()
{
    static $settings = null;
    if ($settings === null) {
        $settings = [
            'url' => defined('GENIEACS_URL') ? GENIEACS_URL : '',
            'username' => defined('GENIEACS_USERNAME') ? GENIEACS_USERNAME : '',
            'password' => defined('GENIEACS_PASSWORD') ? GENIEACS_PASSWORD : ''
        ];

        // Try to get from database
        $dbSettings = fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('GENIEACS_URL', 'GENIEACS_USERNAME', 'GENIEACS_PASSWORD')");
        foreach ($dbSettings as $s) {
            switch ($s['setting_key']) {
                case 'GENIEACS_URL':
                    $settings['url'] = $s['setting_value'];
                    break;
                case 'GENIEACS_USERNAME':
                    $settings['username'] = $s['setting_value'];
                    break;
                case 'GENIEACS_PASSWORD':
                    $settings['password'] = $s['setting_value'];
                    break;
            }
        }
    }
    return $settings;
}

// GenieACS functions
function genieacsGetDevices()
{
    $genieacs = getGenieacsSettings();
    if (empty($genieacs['url'])) {
        return [];
    }

    $projection = [
        '_id',
        '_lastInform',
        '_deviceId',
        'DeviceID',
        'VirtualParameters.pppoeUsername',
        'VirtualParameters.pppoeUsername2',
        'VirtualParameters.gettemp',
        'VirtualParameters.RXPower',
        'VirtualParameters.pppoeIP',
        'VirtualParameters.IPTR069',
        'VirtualParameters.pppoeMac',
        'VirtualParameters.getponmode',
        'VirtualParameters.PonMac',
        'VirtualParameters.getSerialNumber',
        'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID',
        'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase',
        'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.TotalAssociations',
        'VirtualParameters.activedevices',
        'VirtualParameters.getdeviceuptime'
    ];

    $query = json_encode(['_id' => ['$regex' => '']]);
    $projectionStr = implode(',', $projection);
    
    $url = rtrim($genieacs['url'], '/') . '/devices/?query=' . urlencode($query) . '&projection=' . $projectionStr;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Increased timeout for larger datasets

    // Add authentication if credentials are set
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $devices = json_decode($response, true);
        return is_array($devices) ? $devices : [];
    }

    return [];
}

function genieacsGetDevice($serial)
{
    $genieacs = getGenieacsSettings();
    if (empty($genieacs['url'])) {
        return null;
    }

    // Attempt 1: Search by Serial Number
    $query1 = json_encode(['_deviceId._SerialNumber' => $serial]);
    $url1 = rtrim($genieacs['url'], '/') . '/devices/?query=' . urlencode($query1);

    $ch1 = curl_init($url1);
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch1, CURLOPT_TIMEOUT, 10);
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch1, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }

    $response1 = curl_exec($ch1);
    $httpCode1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);

    if ($httpCode1 === 200) {
        $devices = json_decode($response1, true);
        if (is_array($devices) && count($devices) > 0) {
            return $devices[0];
        }
    }

    // Attempt 2: Search by _id (Exact match)
    // Using query parameter is safer than direct URL access for special chars
    $query2 = json_encode(['_id' => $serial]);
    $url2 = rtrim($genieacs['url'], '/') . '/devices/?query=' . urlencode($query2);

    $ch2 = curl_init($url2);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch2, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }

    $response2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);

    if ($httpCode2 === 200) {
        $devices = json_decode($response2, true);
        if (is_array($devices) && count($devices) > 0) {
            return $devices[0];
        }
    }

    // Attempt 3: Search by _id (Decoded)
    // Handles cases where ID was passed encoded (e.g. %2D instead of -)
    $decodedSerial = urldecode($serial);
    if ($decodedSerial !== $serial) {
        $query3 = json_encode(['_id' => $decodedSerial]);
        $url3 = rtrim($genieacs['url'], '/') . '/devices/?query=' . urlencode($query3);

        $ch3 = curl_init($url3);
        curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch3, CURLOPT_TIMEOUT, 10);
        if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
            curl_setopt($ch3, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
        }

        $response3 = curl_exec($ch3);
        $httpCode3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);

        if ($httpCode3 === 200) {
            $devices = json_decode($response3, true);
            if (is_array($devices) && count($devices) > 0) {
                return $devices[0];
            }
        }
    }

    return null;
}

// Helper function to extract value from GenieACS parameter structure
function genieacsGetValue($device, $path)
{
    // Navigate through nested structure
    $keys = explode('.', $path);
    $current = $device;

    foreach ($keys as $key) {
        if (!is_array($current)) {
            return null;
        }

        // Try direct key access
        if (isset($current[$key])) {
            $current = $current[$key];
        } else {
            // Try numeric index pattern (e.g., LANDevice.1 -> LANDevice["1"])
            $found = false;
            foreach ($current as $k => $v) {
                if (strpos($k, $key) === 0) {
                    $current = $v;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return null;
            }
        }
    }

    // Extract value - GenieACS stores values in different formats
    if (is_array($current)) {
        // Try common value keys
        if (isset($current['_value'])) {
            return $current['_value'];
        }
        if (isset($current['value'])) {
            return $current['value'];
        }
        if (isset($current[0]) && is_string($current[0])) {
            return $current[0];
        }
    }

    return is_string($current) ? $current : null;
}

// Get device info summary from GenieACS
function genieacsGetDeviceInfo($serial)
{
    $device = genieacsGetDevice($serial);

    if (!$device) {
        return null;
    }

    $info = [
        'id' => $device['_id'] ?? $serial,
        'serial_number' => $serial,
        'last_inform' => $device['_lastInform'] ?? null,
        'status' => 'unknown',
        'uptime' => null,
        'manufacturer' => null,
        'model' => null,
        'software_version' => null,
        'rx_power' => null,
        'tx_power' => null,
        'ssid' => null,
        'wifi_password' => null,
        'ip_address' => null,
        'mac_address' => null
    ];

    // Determine online status (last inform within 5 minutes)
    if ($info['last_inform']) {
        $lastInform = strtotime($info['last_inform']);
        $info['status'] = (time() - $lastInform) < 300 ? 'online' : 'offline';
    }

    // Extract common parameters using different possible paths
    // Device Manufacturer
    $info['manufacturer'] =
        genieacsGetValue($device, 'InternetGatewayDevice.DeviceInfo.Manufacturer') ??
        genieacsGetValue($device, 'Device.DeviceInfo.Manufacturer') ??
        genieacsGetValue($device, 'DeviceID.Manufacturer');

    // Device Model
    $info['model'] =
        genieacsGetValue($device, 'InternetGatewayDevice.DeviceInfo.ModelName') ??
        genieacsGetValue($device, 'Device.DeviceInfo.ModelName') ??
        genieacsGetValue($device, 'DeviceID.ProductClass');

    // Software Version
    $info['software_version'] =
        genieacsGetValue($device, 'InternetGatewayDevice.DeviceInfo.SoftwareVersion') ??
        genieacsGetValue($device, 'Device.DeviceInfo.SoftwareVersion');

    // Uptime
    $info['uptime'] =
        genieacsGetValue($device, 'InternetGatewayDevice.DeviceInfo.UpTime') ??
        genieacsGetValue($device, 'Device.DeviceInfo.UpTime');

    // WAN IP Address
    $info['ip_address'] =
        genieacsGetValue($device, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress') ??
        genieacsGetValue($device, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.ExternalIPAddress');

    // MAC Address
    $info['mac_address'] =
        genieacsGetValue($device, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.MACAddress') ??
        genieacsGetValue($device, 'Device.Ethernet.Interface.1.MACAddress');

    // WiFi SSID - try multiple paths
    $info['ssid'] =
        genieacsGetValue($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID') ??
        genieacsGetValue($device, 'InternetGatewayDevice.LANDevice.1.WiFi.Radio.1.SSID') ??
        genieacsGetValue($device, 'Device.WiFi.SSID.1.SSID');

    // WiFi Password
    $info['wifi_password'] =
        genieacsGetValue($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase') ??
        genieacsGetValue($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase') ??
        genieacsGetValue($device, 'Device.WiFi.AccessPoint.1.Security.KeyPassphrase');

    // PON Optical Power (for GPON/EPON ONUs)
    $info['rx_power'] =
        genieacsGetValue($device, 'InternetGatewayDevice.WANDevice.1.X_GponInterafceConfig.RxPower') ??
        genieacsGetValue($device, 'Device.Optical.Interface.1.RXPower');

    $info['tx_power'] =
        genieacsGetValue($device, 'InternetGatewayDevice.WANDevice.1.X_GponInterafceConfig.TxPower') ??
        genieacsGetValue($device, 'Device.Optical.Interface.1.TXPower');

    return $info;
}

function genieacsSetParameter($serial, $parameter, $value)
{
    $genieacs = getGenieacsSettings();
    if (empty($genieacs['url'])) {
        return ['success' => false, 'message' => 'GenieACS URL not configured'];
    }

    // Get device first to find the actual device ID
    $device = genieacsGetDevice($serial);
    if (!$device) {
        // If device lookup fails, return specific error
        return ['success' => false, 'message' => "Device lookup failed for: $serial"];
    }

    $deviceId = $device['_id'] ?? $serial;
    // Use rawurlencode and add timeout parameter (3000ms) to avoid hanging
    // This matches GACS implementation reference
    $encodedId = rawurlencode($deviceId);
    $url = rtrim($genieacs['url'], '/') . "/devices/{$encodedId}/tasks?timeout=3000&connection_request";

    $data = [
        'name' => 'setParameterValues', // Note: GACS uses setParameterValues, check if different from setParameter
        'parameterValues' => [
            [$parameter, (string)$value, 'xsd:string']
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10s > 3s GenieACS timeout

    // Add authentication if credentials are set
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    // curl_close() is deprecated in PHP 8.0+ - CurlHandle auto-destroys

    if ($httpCode === 200 || $httpCode === 201 || $httpCode === 202) {
        return ['success' => true, 'message' => 'Task created successfully'];
    }

    if ($curlError) {
        return ['success' => false, 'message' => "Curl Error: $curlError"];
    }

    return ['success' => false, 'message' => "GenieACS Error ($httpCode): " . ($response ?: 'Unknown error')];
}

// Find device by PPPoE username in GenieACS
function genieacsFindDeviceByPppoe($pppoeUsername)
{
    $genieacs = getGenieacsSettings();
    if (empty($genieacs['url'])) {
        return null;
    }

    // First, try to find device using VirtualParameters.pppoeUsername which is the most reliable approach
    $query = json_encode(['VirtualParameters.pppoeUsername' => $pppoeUsername]);
    $url = rtrim($genieacs['url'], '/') . '/devices/?query=' . urlencode($query);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    // Add authentication if credentials are set
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() is deprecated in PHP 8.0+ - CurlHandle auto-destroys

    if ($httpCode === 200) {
        $devices = json_decode($response, true);
        if (is_array($devices) && count($devices) > 0) {
            return $devices[0]; // Return first matching device
        }
    }

    // If not found via VirtualParameters, try alternative approaches
    // Try searching for devices with PPPoE username in various possible locations
    $possibleQueries = [
        // Alternative VirtualParameters that might contain the username
        ['VirtualParameters.pppoeUsername2' => $pppoeUsername],
        // Common paths where username might be stored in standard parameters
        ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.Username' => $pppoeUsername],
        ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username' => $pppoeUsername],
        ['Device.PPP.Interface.1.Credentials.Username' => $pppoeUsername],
        ['InternetGatewayDevice.PPPPEngine.PPPoE.UnicastDiscovery.Username' => $pppoeUsername],
        // If PPPoE username is stored as part of device name or description
        ['Device.DeviceInfo.Description' => $pppoeUsername],
        ['Device.DeviceInfo.FriendlyName' => $pppoeUsername]
    ];

    foreach ($possibleQueries as $query) {
        $encodedQuery = json_encode($query);
        $url = rtrim($genieacs['url'], '/') . '/devices/?query=' . urlencode($encodedQuery);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // Add authentication if credentials are set
        if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // curl_close() is deprecated in PHP 8.0+ - CurlHandle auto-destroys

        if ($httpCode === 200) {
            $devices = json_decode($response, true);
            if (is_array($devices) && count($devices) > 0) {
                return $devices[0]; // Return first matching device
            }
        }
    }

    // If no device found by searching parameters, try a more general search
    // Sometimes the PPPoE username might be stored in custom fields
    $generalQuery = urlencode('"' . $pppoeUsername . '"');
    $url = rtrim($genieacs['url'], '/') . '/devices/?query=' . $generalQuery;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    // Add authentication if credentials are set
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() is deprecated in PHP 8.0+ - CurlHandle auto-destroys

    if ($httpCode === 200) {
        $devices = json_decode($response, true);
        if (is_array($devices) && count($devices) > 0) {
            return $devices[0]; // Return first matching device
        }
    }

    return null;
}

// Reboot device via GenieACS
function genieacsReboot($serial)
{
    $genieacs = getGenieacsSettings();
    if (empty($genieacs['url'])) {
        return false;
    }

    // Get device first to find the actual device ID
    $device = genieacsGetDevice($serial);
    if (!$device) {
        return false;
    }

    $deviceId = $device['_id'] ?? $serial;
    $url = rtrim($genieacs['url'], '/') . '/devices/' . urlencode($deviceId) . '/tasks?connection_request';

    $data = [
        'name' => 'reboot'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    // Add authentication if credentials are set
    if (!empty($genieacs['username']) && !empty($genieacs['password'])) {
        curl_setopt($ch, CURLOPT_USERPWD, $genieacs['username'] . ':' . $genieacs['password']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() is deprecated in PHP 8.0+ - CurlHandle auto-destroys

    return $httpCode === 200 || $httpCode === 201;
}

// Pagination
function paginate($table, $page = 1, $perPage = ITEMS_PER_PAGE, $where = '', $params = [])
{
    $offset = ($page - 1) * $perPage;

    // Get total
    $countSql = "SELECT COUNT(*) as total FROM {$table}";
    if ($where) {
        $countSql .= " WHERE {$where}";
    }
    $totalResult = fetchOne($countSql, $params);
    $total = $totalResult['total'] ?? 0;

    // Get data
    $dataSql = "SELECT * FROM {$table}";
    if ($where) {
        $dataSql .= " WHERE {$where}";
    }
    $perPage = (int) $perPage;
    $offset = (int) $offset;
    $dataSql .= " ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}";

    $data = fetchAll($dataSql, $params);

    return [
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => ceil($total / $perPage)
    ];
}

// Generate CSRF token
function generateCsrfToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Check if admin is logged in
function isAdminLoggedIn()
{
    return isset($_SESSION['admin']['logged_in']) && $_SESSION['admin']['logged_in'] === true;
}

// Check if customer is logged in
function isCustomerLoggedIn()
{
    return isset($_SESSION['customer']['logged_in']) && $_SESSION['customer']['logged_in'] === true;
}

// Get current admin
function getCurrentAdmin()
{
    return $_SESSION['admin'] ?? null;
}

// Get current customer
function getCurrentCustomer()
{
    return $_SESSION['customer'] ?? null;
}

// JSON response
function jsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Check if request is AJAX
function isAjax()
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// Get current URL
function getCurrentUrl()
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

// Format bytes to human readable format
function formatBytes($bytes, $precision = 2)
{
    $bytes = is_numeric($bytes) ? (float) $bytes : 0;
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Update MikroTik Hotspot User
function mikrotikUpdateHotspotUser($id, $data)
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return false;

    mikrotikWrite($socket, '/ip/hotspot/user/set');
    mikrotikWrite($socket, '=.id=' . $id);
    if (isset($data['name']))
        mikrotikWrite($socket, '=name=' . $data['name']);
    if (isset($data['password']))
        mikrotikWrite($socket, '=password=' . $data['password']);
    if (isset($data['profile']))
        mikrotikWrite($socket, '=profile=' . $data['profile']);
    if (isset($data['limit-uptime']))
        mikrotikWrite($socket, '=limit-uptime=' . $data['limit-uptime']);
    if (isset($data['limit-bytes-total']))
        mikrotikWrite($socket, '=limit-bytes-total=' . $data['limit-bytes-total']);
    if (isset($data['comment']))
        mikrotikWrite($socket, '=comment=' . $data['comment']);
    if (isset($data['disabled']))
        mikrotikWrite($socket, '=disabled=' . $data['disabled']);
    mikrotikWrite($socket, '');

    $response = mikrotikReadSentence($socket);
    // Do NOT fclose() — this is a shared persistent connection
    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0)
            return false;
    }
    return true;
}

// Get Active Hotspot Users
function mikrotikGetHotspotActive()
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return [];

    mikrotikWrite($socket, '/ip/hotspot/active/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }
    // Do NOT fclose() — this is a shared persistent connection

    $active = [];
    $current = [];
    foreach ($allWords as $word) {
        if ($word === '!re' || $word === '!done') {
            if (!empty($current)) {
                $current['user'] = $current['user'] ?? '';
                $current['address'] = $current['address'] ?? '';
                $current['uptime'] = $current['uptime'] ?? '0s';

                $active[] = $current;
                $current = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2)
                $current[$parts[0]] = $parts[1];
        }
    }
    return $active;
}

// Update Hotspot Profile
function mikrotikUpdateHotspotProfile($id, $data)
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return false;

    mikrotikWrite($socket, '/ip/hotspot/user/profile/set');
    mikrotikWrite($socket, '=.id=' . $id);
    if (isset($data['name']))
        mikrotikWrite($socket, '=name=' . $data['name']);
    if (isset($data['shared-users']))
        mikrotikWrite($socket, '=shared-users=' . $data['shared-users']);
    if (isset($data['rate-limit']))
        mikrotikWrite($socket, '=rate-limit=' . $data['rate-limit']);
    if (isset($data['keepalive-timeout']))
        mikrotikWrite($socket, '=keepalive-timeout=' . $data['keepalive-timeout']);
    if (isset($data['idle-timeout']))
        mikrotikWrite($socket, '=idle-timeout=' . $data['idle-timeout']);
    if (isset($data['address-pool']))
        mikrotikWrite($socket, '=address-pool=' . $data['address-pool']);
    if (isset($data['parent-queue']))
        mikrotikWrite($socket, '=parent-queue=' . $data['parent-queue']);
    if (isset($data['on-login']))
        mikrotikWrite($socket, '=on-login=' . $data['on-login']);
    if (isset($data['comment']))
        mikrotikWrite($socket, '=comment=' . $data['comment']);
    mikrotikWrite($socket, '');

    $response = mikrotikReadSentence($socket);
    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0)
            return false;
    }
    return true;
}

// Add Hotspot Profile
function mikrotikAddHotspotProfile($data)
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return false;

    mikrotikWrite($socket, '/ip/hotspot/user/profile/add');
    if (isset($data['name']))
        mikrotikWrite($socket, '=name=' . $data['name']);
    if (isset($data['shared-users']))
        mikrotikWrite($socket, '=shared-users=' . $data['shared-users']);
    if (isset($data['rate-limit']))
        mikrotikWrite($socket, '=rate-limit=' . $data['rate-limit']);
    if (isset($data['keepalive-timeout']))
        mikrotikWrite($socket, '=keepalive-timeout=' . $data['keepalive-timeout']);
    if (isset($data['idle-timeout']))
        mikrotikWrite($socket, '=idle-timeout=' . $data['idle-timeout']);
    if (isset($data['address-pool']))
        mikrotikWrite($socket, '=address-pool=' . $data['address-pool']);
    if (isset($data['parent-queue']))
        mikrotikWrite($socket, '=parent-queue=' . $data['parent-queue']);
    if (isset($data['on-login']))
        mikrotikWrite($socket, '=on-login=' . $data['on-login']);
    if (isset($data['comment']))
        mikrotikWrite($socket, '=comment=' . $data['comment']);
    mikrotikWrite($socket, '');

    $response = mikrotikReadSentence($socket);
    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0)
            return false;
    }
    return true;
}

// Delete Hotspot Profile
function mikrotikDeleteHotspotProfile($id)
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return false;

    mikrotikWrite($socket, '/ip/hotspot/user/profile/remove');
    mikrotikWrite($socket, '=.id=' . $id);
    mikrotikWrite($socket, '');

    $response = mikrotikReadSentence($socket);
    fclose($socket);
    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0)
            return false;
    }
    return true;
}

// Generate Mikhmon v3-style on-login script
// Mikhmon v3 format: on-login script stores comma-separated values:
// index[0]=script, [1]=script, [2]=price, [3]=validity, [4]=sellingPrice, [5]=script, [6]=lockUser
function generateHotspotExpiryScript($mode, $price = 0, $validity = '', $sellingPrice = 0, $lockUser = 'disable')
{
    // Mikhmon v3 on-login script structure (simplified)
    // The comma-separated string stores metadata at fixed positions
    $script = '';

    if ($mode === 'remove') {
        // Script that removes user after expiry
        $script = ':local date [/system clock get date];:local time [/system clock get time];:local uname \$user;';
        $script .= ':local comment [/ip hotspot user get [find name=\$uname] comment];';
        $script .= ':if ([:len \$comment] = 0) do={/ip hotspot user set [find name=\$uname] comment="\$date \$time"};';
    } elseif ($mode === 'notice') {
        $script = ':local date [/system clock get date];:local time [/system clock get time];:local uname \$user;';
        $script .= ':local comment [/ip hotspot user get [find name=\$uname] comment];';
        $script .= ':if ([:len \$comment] = 0) do={/ip hotspot user set [find name=\$uname] comment="\$date \$time"};';
    } elseif ($mode === 'record') {
        $script = ':local date [/system clock get date];:local time [/system clock get time];:local uname \$user;';
        $script .= ':local comment [/ip hotspot user get [find name=\$uname] comment];';
        $script .= ':if ([:len \$comment] = 0) do={/ip hotspot user set [find name=\$uname] comment="\$date \$time"};';
    } else {
        // mode 'none' - only store metadata, no expiry action
        $script = ':nothing';
    }

    $price = (int) $price;
    $sellingPrice = (int) $sellingPrice;

    // Mikhmon v3 comma-separated format at fixed positions:
    // [0]=script, [1]=(unused), [2]=price, [3]=validity, [4]=sellingPrice, [5]=(unused), [6]=lockUser
    $onLoginData = $script . ',' . $mode . ',' . $price . ',' . $validity . ',' . $sellingPrice . ',0,' . $lockUser;

    return $onLoginData;
}

// Parse Mikhmon v3 on-login script to extract price, validity, selling price, lock user
// Based on Mikhmon v3 source: process/getvalidprice.php
function parseMikhmonOnLogin($onLoginScript)
{
    $data = [
        'price' => 0,
        'validity' => '-',
        'selling_price' => 0,
        'datalimit' => '',
        'timelimit' => '',
        'lock_user' => 'disable',
        'mode' => 'none',
    ];

    if (empty($onLoginScript))
        return $data;

    $parts = explode(',', $onLoginScript);

    // Mikhmon v3 indices: [1]=mode, [2]=price, [3]=validity, [4]=sellingPrice, [5]=datalimit, [6]=timelimit, [7]=lockUser
    if (isset($parts[1]) && !empty($parts[1])) {
        $data['mode'] = $parts[1];
    }
    if (isset($parts[2]) && is_numeric($parts[2])) {
        $data['price'] = (int) $parts[2];
    }
    if (isset($parts[3]) && !empty($parts[3])) {
        $data['validity'] = $parts[3];
    }
    if (isset($parts[4]) && is_numeric($parts[4])) {
        $data['selling_price'] = (int) $parts[4];
    }
    if (isset($parts[5]) && !empty($parts[5])) {
        $data['datalimit'] = $parts[5];
    }
    if (isset($parts[6]) && !empty($parts[6])) {
        $data['timelimit'] = $parts[6];
    }
    if (isset($parts[7]) && !empty($parts[7])) {
        $data['lock_user'] = $parts[7];
    }

    return $data;
}

// Get MikroTik System Resource (CPU, Memory, Uptime, Board Name, etc.)
function mikrotikGetSystemResource()
{
    $socket = getMikrotikConnection();
    if (!$socket) {
        return [
            'board-name' => '-',
            'cpu-load' => 0,
            'free-memory' => 0,
            'total-memory' => 0,
            'uptime' => '-',
            'version' => '-',
            'architecture-name' => '-',
        ];
    }

    mikrotikWrite($socket, '/system/resource/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;

    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $resource = [];
    foreach ($allWords as $word) {
        if (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $resource[$parts[0]] = $parts[1];
            }
        }
    }

    return [
        'board-name' => $resource['board-name'] ?? '-',
        'cpu-load' => (int) ($resource['cpu-load'] ?? 0),
        'free-memory' => (int) ($resource['free-memory'] ?? 0),
        'total-memory' => (int) ($resource['total-memory'] ?? 0),
        'uptime' => $resource['uptime'] ?? '-',
        'version' => $resource['version'] ?? '-',
        'architecture-name' => $resource['architecture-name'] ?? '-',
    ];
}

// Get list of MikroTik interfaces
function mikrotikGetInterfaces()
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return [];

    mikrotikWrite($socket, '/interface/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;

    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $interfaces = [];
    $current = [];
    foreach ($allWords as $word) {
        if ($word === '!re') {
            if (!empty($current)) {
                $interfaces[] = $current;
            }
            $current = [];
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $current[$parts[0]] = $parts[1];
            }
        }
    }
    if (!empty($current)) {
        $interfaces[] = $current;
    }

    return $interfaces;
}

// Monitor traffic on a specific interface (one-shot read)
function mikrotikMonitorTraffic($interfaceName)
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return ['tx' => 0, 'rx' => 0];

    mikrotikWrite($socket, '/interface/monitor-traffic');
    mikrotikWrite($socket, '=interface=' . $interfaceName);
    mikrotikWrite($socket, '=once=');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;

    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $data = [];
    foreach ($allWords as $word) {
        if (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $data[$parts[0]] = $parts[1];
            }
        }
    }

    return [
        'tx' => (int) ($data['tx-bits-per-second'] ?? 0),
        'rx' => (int) ($data['rx-bits-per-second'] ?? 0),
    ];
}

// Get Hotspot Log entries from MikroTik
function mikrotikGetHotspotLog($limit = 20)
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return [];

    mikrotikWrite($socket, '/log/print');
    mikrotikWrite($socket, '?topics=hotspot,info,debug');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;

    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $logs = [];
    $current = [];
    foreach ($allWords as $word) {
        if ($word === '!re') {
            if (!empty($current)) {
                $logs[] = $current;
            }
            $current = [];
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $current[$parts[0]] = $parts[1];
            }
        }
    }
    if (!empty($current)) {
        $logs[] = $current;
    }

    // Return last N entries in reverse order (newest first)
    $logs = array_reverse($logs);
    return array_slice($logs, 0, $limit);
}

// Get MikroTik Address Pools
function mikrotikGetAddressPools()
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return [];

    mikrotikWrite($socket, '/ip/pool/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $pools = [];
    $current = [];
    foreach ($allWords as $word) {
        if ($word === '!re' || $word === '!done') {
            if (!empty($current)) {
                $pools[] = $current;
                $current = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2)
                $current[$parts[0]] = $parts[1];
        }
    }
    return $pools;
}

// Get MikroTik Parent Queues
function mikrotikGetParentQueues()
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return [];

    mikrotikWrite($socket, '/queue/simple/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $queues = [];
    $current = [];
    foreach ($allWords as $word) {
        if ($word === '!re' || $word === '!done') {
            if (!empty($current)) {
                $queues[] = $current;
                $current = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2)
                $current[$parts[0]] = $parts[1];
        }
    }
    return $queues;
}

// Record Hotspot Sale in Database
function recordHotspotSale($username, $profile, $price, $sellingPrice, $prefix = '')
{
    $data = [
        'username' => sanitize($username),
        'profile' => sanitize($profile),
        'price' => (float) $price,
        'selling_price' => (float) $sellingPrice,
        'prefix' => sanitize($prefix),
        'created_at' => date('Y-m-d H:i:s')
    ];

    try {
        return insert('hotspot_sales', $data);
    } catch (Exception $e) {
        logError("Failed to record hotspot sale: " . $e->getMessage());
        return false;
    }
}

// Kick (remove) an active hotspot user session
function mikrotikKickHotspotUser($username)
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return false;

    // First find the session .id
    mikrotikWrite($socket, '/ip/hotspot/active/print');
    mikrotikWrite($socket, '?user=' . $username);
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 5;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $sessionId = null;
    foreach ($allWords as $word) {
        if (strpos($word, '=.id=') === 0) {
            $sessionId = substr($word, 5);
            break;
        }
    }

    if (!$sessionId)
        return false;

    // Remove the session
    mikrotikWrite($socket, '/ip/hotspot/active/remove');
    mikrotikWrite($socket, '=.id=' . $sessionId);
    mikrotikWrite($socket, '');

    $response = mikrotikReadSentence($socket);
    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0)
            return false;
    }
    return true;
}

// Get MikroTik Hotspot Cookies
function mikrotikGetHotspotCookies()
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return [];

    mikrotikWrite($socket, '/ip/hotspot/cookie/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $cookies = [];
    $current = [];
    foreach ($allWords as $word) {
        if ($word === '!re' || $word === '!done') {
            if (!empty($current)) {
                $cookies[] = $current;
                $current = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2)
                $current[$parts[0]] = $parts[1];
        }
    }
    return $cookies;
}

// Delete a hotspot cookie
function mikrotikDeleteHotspotCookie($id)
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return false;

    mikrotikWrite($socket, '/ip/hotspot/cookie/remove');
    mikrotikWrite($socket, '=.id=' . $id);
    mikrotikWrite($socket, '');

    $response = mikrotikReadSentence($socket);
    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0)
            return false;
    }
    return true;
}

// Get MikroTik Hotspot Hosts (connected devices)
function mikrotikGetHotspotHosts()
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return [];

    mikrotikWrite($socket, '/ip/hotspot/host/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $hosts = [];
    $current = [];
    foreach ($allWords as $word) {
        if ($word === '!re' || $word === '!done') {
            if (!empty($current)) {
                $hosts[] = $current;
                $current = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2)
                $current[$parts[0]] = $parts[1];
        }
    }
    return $hosts;
}

// Get MikroTik System Schedulers
function mikrotikGetSchedulers()
{
    $socket = getMikrotikConnection();
    if (!$socket)
        return [];

    mikrotikWrite($socket, '/system/scheduler/print');
    mikrotikWrite($socket, '');

    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words))
            break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }

    $schedulers = [];
    $current = [];
    foreach ($allWords as $word) {
        if ($word === '!re' || $word === '!done') {
            if (!empty($current)) {
                $schedulers[] = $current;
                $current = [];
            }
        } elseif (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2)
                $current[$parts[0]] = $parts[1];
        }
    }
    return $schedulers;
}

// Get MikroTik Resource
function mikrotikGetResource() {
    $socket = getMikrotikConnection();
    if (!$socket) {
        return null;
    }
    
    mikrotikWrite($socket, '/system/resource/print');
    mikrotikWrite($socket, '');
    
    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }
    
    $resource = [];
    foreach ($allWords as $word) {
        if ($word === '!re') {
            continue;
        }
        if (strpos($word, '=') === 0) {
            $word = substr($word, 1);
            $parts = explode('=', $word, 2);
            if (count($parts) === 2) {
                $resource[$parts[0]] = $parts[1];
            }
        }
    }
    
    return $resource;
}

// Ping from MikroTik
function mikrotikPing($target, $count = 4) {
    $socket = getMikrotikConnection();
    if (!$socket) {
        return null;
    }
    
    mikrotikWrite($socket, '/ping');
    mikrotikWrite($socket, '=address=' . $target);
    mikrotikWrite($socket, '=count=' . (int)$count);
    mikrotikWrite($socket, '');
    
    $allWords = [];
    $done = false;
    $timeout = time() + 15;
    
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }
    
    $sent = 0;
    $received = 0;
    $lost = 0;
    $latencies = [];
    
    foreach ($allWords as $word) {
        if (strpos($word, '=sent=') === 0) {
            $sent = (int)substr($word, 6);
        } elseif (strpos($word, '=received=') === 0) {
            $received = (int)substr($word, 10);
        } elseif (strpos($word, '=packet-loss=') === 0) {
            $lost = (int)substr($word, 13);
        } elseif (strpos($word, '=time=') === 0) {
            $latencies[] = (float)substr($word, 6);
        }
    }
    
    $avg = null;
    if (!empty($latencies)) {
        $avg = array_sum($latencies) / count($latencies);
    }
    
    return [
        'sent' => $sent,
        'received' => $received,
        'loss' => $lost,
        'avg' => $avg
    ];
}

// Remove Active Session by Name
function mikrotikRemoveActiveSessionByName($username) {
    $socket = getMikrotikConnection();
    if (!$socket) {
        return false;
    }
    
    mikrotikWrite($socket, '/ppp/active/print');
    mikrotikWrite($socket, '?name=' . $username);
    mikrotikWrite($socket, '');
    
    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') {
                $done = true;
                break;
            }
        }
    }
    
    $sessionId = null;
    foreach ($allWords as $word) {
        if (strpos($word, '=.id=') === 0) {
            $sessionId = substr($word, 5);
            break;
        }
    }
    
    if (!$sessionId) {
        return false;
    }
    
    mikrotikWrite($socket, '/ppp/active/remove');
    mikrotikWrite($socket, '=.id=' . $sessionId);
    mikrotikWrite($socket, '');
    
    $response = mikrotikReadSentence($socket);
    
    foreach ($response as $word) {
        if (strpos($word, '!trap') === 0) {
            return false;
        }
    }
    
    return true;
}

function mikrotikGetSecretByName($username) {
    $socket = getMikrotikConnection();
    if (!$socket) return null;
    
    mikrotikWrite($socket, '/ppp/secret/print');
    mikrotikWrite($socket, '?name=' . $username);
    mikrotikWrite($socket, '');
    
    $allWords = [];
    $done = false;
    $timeout = time() + 10;
    while (!$done && time() < $timeout) {
        $words = mikrotikReadSentence($socket);
        if (empty($words)) break;
        foreach ($words as $word) {
            $allWords[] = $word;
            if ($word === '!done') { $done = true; break; }
        }
    }
    
    $secrets = mikrotikParseUsers($allWords);
    return !empty($secrets) ? $secrets[0] : null;
}


