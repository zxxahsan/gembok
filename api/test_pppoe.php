<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mikrotik_api.php';

$phone = $_GET['phone'] ?? $_GET['username'] ?? '';

if (empty($phone)) {
    echo "ERROR: Please provide ?phone=XXX\n";
    exit;
}

$customer = fetchOne("SELECT * FROM customers WHERE phone = ? OR pppoe_username = ?", [$phone, $phone]);

if (!$customer) {
    echo "CUSTOMER NOT FOUND in DB.\n";
    exit;
}

echo "CUSTOMER FOUND: " . $customer['name'] . "\n";
$pppoeUser = $customer['pppoe_username'] ?? '';
echo "PPPOE USERNAME: '$pppoeUser'\n\n";

if (!empty($pppoeUser)) {
    echo "Testing MikroTik Connection...\n";
    $socket = getMikrotikConnection();
    if (!$socket) {
        echo "FAILED TO CONNECT TO MIKROTIK.\n";
    } else {
        echo "MikroTik Socket Connected.\n";
        
        echo "\nAttempting /interface/print for name='<pppoe-$pppoeUser>'...\n";
        mikrotikWrite($socket, '/interface/print');
        mikrotikWrite($socket, '?name=<pppoe-' . $pppoeUser . '>');
        mikrotikWrite($socket, '');
        
        $allWords = [];
        $done = false;
        $timeout = time() + 5;
        while (!$done && time() < $timeout) {
            $words = mikrotikReadSentence($socket);
            if (empty($words)) break;
            foreach ($words as $word) {
                $allWords[] = $word;
                if ($word === '!done') { $done = true; break; }
            }
        }
        
        $sessions = [];
        $currentSession = [];
        foreach ($allWords as $word) {
            if ($word === '!done') {
                if (!empty($currentSession)) $sessions[] = $currentSession;
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
        
        if (!empty($sessions)) {
            echo "INTERFACE FOUND:\n";
            print_r($sessions[0]);
        } else {
            echo "NO INTERFACE FOUND FOR '<pppoe-$pppoeUser>'.\n";
        }
    }
}
