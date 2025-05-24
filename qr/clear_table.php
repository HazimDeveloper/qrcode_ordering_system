<?php
// ==============================================
// FILE: qr/clear_table.php (Clear QR session)
// ==============================================
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // Store the table number before clearing (for logging)
    $table_number = $_SESSION['qr_table_number'] ?? null;
    
    // Clear QR-related session data
    unset($_SESSION['qr_table_number']);
    unset($_SESSION['order_type']);
    
    // Optional: Log the table clearing action
    if ($table_number && isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO qr_activity_log (user_id, table_number, action, timestamp, ip_address) VALUES (?, ?, 'table_cleared', NOW(), ?)");
            $stmt->execute([$_SESSION['user_id'], $table_number, $_SERVER['REMOTE_ADDR']]);
        } catch (Exception $e) {
            // Continue if logging fails (table might not exist)
        }
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Table number cleared successfully',
        'cleared_table' => $table_number
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => 'Failed to clear table number'
    ]);
}
?>