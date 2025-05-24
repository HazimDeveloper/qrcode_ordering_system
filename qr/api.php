<?php
// ==============================================
// FILE: qr/api.php - QR API Endpoints
// ==============================================
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get the action from URL parameter
$action = $_GET['action'] ?? '';

// Handle different API endpoints
switch ($action) {
    case 'validate_table':
        validateTable();
        break;
        
    case 'generate_qr':
        generateQR();
        break;
        
    case 'qr_stats':
        getQRStats();
        break;
        
    case 'table_status':
        getTableStatus();
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}

function validateTable() {
    global $pdo;
    
    $table_number = (int)($_GET['table'] ?? 0);
    
    if ($table_number <= 0 || $table_number > 999) {
        echo json_encode([
            'valid' => false,
            'error' => 'Invalid table number'
        ]);
        return;
    }
    
    // Check if table has active orders
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as active_orders 
            FROM orders 
            WHERE table_number = ? 
            AND status IN ('pending', 'confirmed', 'preparing') 
            AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$table_number]);
        $result = $stmt->fetch();
        
        echo json_encode([
            'valid' => true,
            'table_number' => $table_number,
            'active_orders' => (int)$result['active_orders'],
            'status' => $result['active_orders'] > 0 ? 'occupied' : 'available'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'valid' => false,
            'error' => 'Database error'
        ]);
    }
}

function generateQR() {
    if (!isLoggedIn() || !isStaff()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $table_number = (int)($input['table_number'] ?? 0);
    $restaurant_name = trim($input['restaurant_name'] ?? 'QR Food Ordering');
    
    if ($table_number <= 0 || $table_number > 999) {
        echo json_encode(['error' => 'Invalid table number']);
        return;
    }
    
    $base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/qr-food-ordering';
    $qr_url = $base_url . '/qr/scan.php?table=' . $table_number;
    $qr_image_url = 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . urlencode($qr_url) . '&choe=UTF-8';
    
    echo json_encode([
        'success' => true,
        'table_number' => $table_number,
        'qr_url' => $qr_url,
        'qr_image_url' => $qr_image_url,
        'restaurant_name' => $restaurant_name
    ]);
}

function getQRStats() {
    global $pdo;
    
    if (!isLoggedIn() || !isStaff()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    try {
        // Get QR usage statistics
        $stats = [];
        
        // Today's QR scans
        $stmt = $pdo->query("
            SELECT COUNT(*) as qr_scans_today 
            FROM orders 
            WHERE table_number IS NOT NULL 
            AND DATE(created_at) = CURDATE()
        ");
        $stats['qr_scans_today'] = $stmt->fetchColumn();
        
        // Unique tables used today
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT table_number) as tables_used_today 
            FROM orders 
            WHERE table_number IS NOT NULL 
            AND DATE(created_at) = CURDATE()
        ");
        $stats['tables_used_today'] = $stmt->fetchColumn();
        
        // Revenue from QR orders today
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(total_amount), 0) as qr_revenue_today 
            FROM orders 
            WHERE table_number IS NOT NULL 
            AND DATE(created_at) = CURDATE()
        ");
        $stats['qr_revenue_today'] = $stmt->fetchColumn();
        
        // Most popular tables (last 7 days)
        $stmt = $pdo->query("
            SELECT table_number, COUNT(*) as order_count 
            FROM orders 
            WHERE table_number IS NOT NULL 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY table_number 
            ORDER BY order_count DESC 
            LIMIT 5
        ");
        $stats['popular_tables'] = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'error' => 'Failed to fetch statistics'
        ]);
    }
}

function getTableStatus() {
    global $pdo;
    
    if (!isLoggedIn() || !isStaff()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    try {
        // Get status of all tables with recent activity
        $stmt = $pdo->query("
            SELECT 
                table_number,
                COUNT(*) as total_orders,
                SUM(CASE WHEN status IN ('pending', 'confirmed', 'preparing') THEN 1 ELSE 0 END) as active_orders,
                MAX(created_at) as last_order_time,
                SUM(total_amount) as total_revenue
            FROM orders 
            WHERE table_number IS NOT NULL 
            AND DATE(created_at) = CURDATE()
            GROUP BY table_number 
            ORDER BY table_number ASC
        ");
        
        $tables = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'tables' => $tables,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'error' => 'Failed to fetch table status'
        ]);
    }
}
?>