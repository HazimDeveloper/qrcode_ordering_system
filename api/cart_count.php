<?php
/**
 * API Endpoint: Cart Count
 * Purpose: Returns the current cart count for logged-in users as JSON
 * Used by: JavaScript cart count updater in header.php
 */

// Set proper headers for JSON API response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Prevent any HTML output that could corrupt JSON
ob_start();

try {
    // Include database configuration
    require_once '../config/database.php';
    
    // Initialize response structure
    $response = [
        'success' => false,
        'count' => 0,
        'message' => '',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Check if user is logged in
    if (!isLoggedIn()) {
        $response['message'] = 'User not logged in';
        $response['success'] = true; // Not an error, just no count needed
    } else {
        // Get user's cart count from database
        $stmt = $pdo->prepare("
            SELECT SUM(quantity) as total 
            FROM cart 
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $cart_count = $stmt->fetchColumn();
        
        // Set response data
        $response['success'] = true;
        $response['count'] = (int)($cart_count ?: 0);
        $response['message'] = 'Cart count retrieved successfully';
    }
    
} catch (PDOException $e) {
    // Database error
    $response['success'] = false;
    $response['message'] = 'Database error occurred';
    $response['error_code'] = 'DB_ERROR';
    
    // Log the actual error for debugging (don't expose to client)
    error_log('Cart count API - Database error: ' . $e->getMessage());
    
} catch (Exception $e) {
    // General error
    $response['success'] = false;
    $response['message'] = 'An unexpected error occurred';
    $response['error_code'] = 'GENERAL_ERROR';
    
    // Log the actual error for debugging
    error_log('Cart count API - General error: ' . $e->getMessage());
}

// Clean any accidental output that might corrupt JSON
ob_clean();

// Return JSON response
echo json_encode($response, JSON_PRETTY_PRINT);

// Ensure no additional output
exit;
?>