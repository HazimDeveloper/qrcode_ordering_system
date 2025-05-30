<?php
require_once '../config/database.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Get the table number from session before clearing it
$table_number = $_SESSION['qr_table_number'] ?? null;

// Initialize response array
$response = ['success' => false];

// Only proceed if there's a table number to clear
if ($table_number) {
    try {
        // Clear the table number from session
        unset($_SESSION['qr_table_number']);
        
        // Log the table cleared action
        $stmt = $pdo->prepare("
            INSERT INTO qr_activity_log (user_id, table_number, action, timestamp, ip_address, user_agent, session_id)
            VALUES (?, ?, 'table_cleared', NOW(), ?, ?, ?)
        ");
        
        $user_id = $_SESSION['user_id'] ?? null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $session_id = session_id();
        
        $stmt->execute([$user_id, $table_number, $ip_address, $user_agent, $session_id]);
        
        // Set success response
        $response['success'] = true;
        $response['message'] = 'Table number cleared successfully';
    } catch (PDOException $e) {
        // Log error but don't expose details to client
        error_log('Error in clear_table.php: ' . $e->getMessage());
        $response['message'] = 'Database error occurred';
    }
} else {
    $response['message'] = 'No table number to clear';
}

// Return JSON response
echo json_encode($response);