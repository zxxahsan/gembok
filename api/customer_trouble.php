<?php
/**
 * Customer Trouble Tickets API
 */

require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if customer is logged in
if (!isCustomerLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$customerId = $_SESSION['customer']['id'];
$pdo = getDB();

// Handle GET request (fetch tickets)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM trouble_tickets 
            WHERE customer_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$customerId]);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'tickets' => $tickets]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// Handle POST request (create ticket)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $description = trim($input['description'] ?? '');
    $priority = trim($input['priority'] ?? 'medium');
    
    // Validate inputs
    if (empty($description)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Description is required']);
        exit;
    }
    
    if (!in_array($priority, ['low', 'medium', 'high'])) {
        $priority = 'medium';
    }
    
    try {
        // Insert the new ticket
        $stmt = $pdo->prepare("
            INSERT INTO trouble_tickets (customer_id, description, priority, status, created_at) 
            VALUES (?, ?, ?, 'pending', NOW())
        ");
        $result = $stmt->execute([$customerId, $description, $priority]);
        
        if ($result) {
            $ticketId = $pdo->lastInsertId();
            
            // Optionally send notification to admin
            // This would require a notification system
            
            echo json_encode([
                'success' => true, 
                'message' => 'Ticket created successfully',
                'ticket_id' => $ticketId
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create ticket']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// Method not allowed
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
