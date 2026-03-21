<?php
/**
 * WhatsApp Gateway Engine (Standalone Node.js Integration)
 */

require_once 'config.php';
require_once 'db.php';

// Helper to get settings from database
function getWhatsAppSetting($key) {
    try {
        $row = fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
        return $row ? $row['setting_value'] : '';
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Builds a dynamic WhatsApp message using the database templates
 * @param string $type - The template key (e.g 'new_customer', 'invoice_reminder')
 * @param array $variables - Associative array of string replacements
 * @return string
 */
function buildWhatsAppMessage($type, $variables = []) {
    try {
        $row = fetchOne("SELECT message FROM whatsapp_templates WHERE type = ?", [$type]);
        if (!$row) return ''; // Fallback if template is completely missing
        
        $message = $row['message'];
        
        foreach ($variables as $key => $value) {
            $message = str_replace('{' . $key . '}', $value, $message);
        }
        
        return trim($message);
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Custom Node.js WhatsApp Sender
 */
function sendCustomNodeWhatsApp($phone, $message) {
    $waBotUrl = getWhatsAppSetting('wa_bot_url');
    if (empty($waBotUrl)) {
        return ['success' => false, 'message' => 'Custom Gateway URL (wa_bot_url) is not configured.'];
    }
    
    $url = rtrim($waBotUrl, '/') . '/send-message';
    $data = ['number' => $phone, 'message' => $message];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 || $httpCode === 201) {
        return ['success' => true, 'data' => json_decode($response, true)];
    } else {
        return ['success' => false, 'message' => 'Proxy Gateway Failed (HTTP ' . $httpCode . ')', 'raw' => $response];
    }
}

/**
 * Fonnte WhatsApp Sender
 */
function sendFonnteWhatsApp($phone, $message) {
    $token = getWhatsAppSetting('FONNTE_TOKEN');
    if (empty($token)) {
        return ['success' => false, 'message' => 'Fonnte API token not configured'];
    }
    
    $url = 'https://api.fonnte.com/send';
    $data = ['target' => $phone, 'message' => $message, 'countryCode' => '62'];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: ' . $token]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return ['success' => true, 'data' => json_decode($response, true)];
    } else {
        return ['success' => false, 'message' => 'Failed to send WhatsApp via Fonnte (HTTP ' . $httpCode . ')'];
    }
}

/**
 * Wablas WhatsApp Sender
 */
function sendWablasWhatsApp($phone, $message) {
    $token = getWhatsAppSetting('WABLAS_TOKEN');
    $domain = getWhatsAppSetting('WABLAS_DOMAIN'); // e.g. https://solo.wablas.com
    
    if (empty($token) || empty($domain)) {
        return ['success' => false, 'message' => 'Wablas API token or domain not configured'];
    }
    
    $url = rtrim($domain, '/') . '/api/send-message';
    $data = ['phone' => $phone, 'message' => $message, 'secret' => $token];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return ['success' => true, 'data' => json_decode($response, true)];
    } else {
        return ['success' => false, 'message' => 'Failed to send WhatsApp via Wablas (HTTP ' . $httpCode . ')'];
    }
}

/**
 * MPWA WhatsApp Sender
 */
function sendMpwaWhatsApp($phone, $message) {
    $token = getWhatsAppSetting('MPWA_TOKEN');
    $urlRaw = getWhatsAppSetting('MPWA_URL'); // e.g. https://mpwa.official.id/api/send
    
    if (empty($token) || empty($urlRaw)) {
        return ['success' => false, 'message' => 'MPWA API key or URL not configured'];
    }
    
    $url = rtrim($urlRaw, '/');
    $data = ['target' => $phone, 'message' => $message];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: ' . $token]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return ['success' => true, 'data' => json_decode($response, true)];
    } else {
        return ['success' => false, 'message' => 'Failed to send WhatsApp via MPWA (HTTP ' . $httpCode . ')'];
    }
}

/**
 * Master Gateway Router
 */
function sendWhatsAppMessage($phone, $message) {
    if (empty($phone) || empty($message)) return ['success' => false, 'message' => 'Empty payload'];

    // Clean phone number format for standard consumption
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) === '0') {
        $phone = '62' . substr($phone, 1);
    }
    
    $gateway = getWhatsAppSetting('WA_GATEWAY');
    if (empty($gateway)) $gateway = 'fonnte'; // Default fallback
    
    switch ($gateway) {
        case 'fonnte':
            return sendFonnteWhatsApp($phone, $message);
        case 'wablas':
            return sendWablasWhatsApp($phone, $message);
        case 'mpwa':
            return sendMpwaWhatsApp($phone, $message);
        case 'custom':
        case 'nodejs':
            return sendCustomNodeWhatsApp($phone, $message);
        default:
            return ['success' => false, 'message' => 'WhatsApp gateway provider not recognized: ' . $gateway];
    }
}
