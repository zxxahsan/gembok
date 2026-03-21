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
 * Sends a raw text message via the Custom Node.js Gateway
 */
function sendWhatsAppMessage($phone, $message) {
    if (empty($phone) || empty($message)) return ['success' => false, 'message' => 'Empty payload'];

    // Clean phone number format for standard consumption
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) === '0') {
        $phone = '62' . substr($phone, 1);
    }
    
    $waBotUrl = getWhatsAppSetting('wa_bot_url');
    
    // Fallback if URL is empty or null
    if (empty($waBotUrl)) {
        return ['success' => false, 'message' => 'WhatsApp Gateway URL (wa_bot_url) is not configured.'];
    }
    
    // Common API endpoint structure for Baileys/WWeb.js Wrappers
    $url = rtrim($waBotUrl, '/') . '/send-message';
    
    // Common JSON object requirement
    $data = [
        'number' => $phone,
        'message' => $message
    ];
    
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
