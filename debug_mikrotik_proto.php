<?php
require_once 'includes/config.php';

echo "=== MikroTik Protocol Debug ===\n";
echo "Host: " . MIKROTIK_HOST . "\n";
echo "Port: " . MIKROTIK_PORT . "\n";

$socket = @fsockopen(MIKROTIK_HOST, MIKROTIK_PORT, $errno, $errstr, 5);
if (!$socket) {
    echo "FAILED to connect: $errstr ($errno)\n";
    exit;
}

echo "CONNECTED. Sending /login...\n";

// Function to encode length the MikroTik way
function encodeLength($len) {
    if ($len < 0x80) {
        return chr($len);
    } elseif ($len < 0x4000) {
        return chr(($len >> 8) | 0x80) . chr($len & 0xFF);
    } elseif ($len < 0x200000) {
        return chr(($len >> 16) | 0xC0) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
    } elseif ($len < 0x10000000) {
        return chr(($len >> 24) | 0xE0) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
    } else {
        return chr(0xF0) . chr(($len >> 24) & 0xFF) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
    }
}

function writeWord($socket, $word) {
    fwrite($socket, encodeLength(strlen($word)) . $word);
}

// Send /login
writeWord($socket, "/login");
fwrite($socket, chr(0)); // End of sentence marker for MikroTik is a zero-length word (single null byte)

echo "Waiting for response (hex dump)...\n";

$data = fread($socket, 1024);
if ($data === false || strlen($data) === 0) {
    echo "No data received.\n";
} else {
    echo "Received " . strlen($data) . " bytes:\n";
    echo bin2hex($data) . "\n";
    
    // Try to decode first word length
    $firstByte = ord($data[0]);
    echo "First byte: 0x" . dechex($firstByte) . "\n";
}

fclose($socket);
