<?php
// ==============================================
// FILE: staff/dashboard.php (Updated with QR statistics)
// ==============================================
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

// Get statistics with improved queries and error handling
try {
    // Today's orders - using DATE() function to extract date part from timestamp
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()");
    $today_orders = $stmt->fetchColumn() ?: 0;

    // Pending orders - including all active statuses
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending', 'confirmed', 'preparing')");
    $pending_orders = $stmt->fetchColumn() ?: 0;

    // Today's revenue with proper NULL handling
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = CURDATE()");
    $today_revenue = $stmt->fetchColumn() ?: 0;

    // Menu items count
    $stmt = $pdo->query("SELECT COUNT(*) FROM menu_items");
    $total_items = $stmt->fetchColumn() ?: 0;

    // QR Code statistics - tables used today
    $stmt = $pdo->query("SELECT COUNT(DISTINCT table_number) FROM orders WHERE table_number IS NOT NULL AND DATE(created_at) = CURDATE()");
    $qr_orders_today = $stmt->fetchColumn() ?: 0;

    // Total QR orders today
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE table_number IS NOT NULL AND DATE(created_at) = CURDATE()");
    $total_qr_orders = $stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
    // Log error and set default values
    error_log('Dashboard statistics error: ' . $e->getMessage());
    $today_orders = $pending_orders = $total_items = $qr_orders_today = $total_qr_orders = 0;
    $today_revenue = 0.00;
}

$page_title = 'Staff Dashboard';
include '../includes/header.php';
?>

<h1>Staff Dashboard</h1>
<!-- 
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0;">
    <div style="background: #3498db; color: white; padding: 30px; border-radius: 8px; text-align: center;">
        <h2><?php echo $today_orders; ?></h2>
        <p>Today's Orders</p>
    </div>
    
    <div style="background: #e67e22; color: white; padding: 30px; border-radius: 8px; text-align: center;">
        <h2><?php echo $pending_orders; ?></h2>
        <p>Pending Orders</p>
    </div>
    
    <div style="background: #27ae60; color: white; padding: 30px; border-radius: 8px; text-align: center;">
        <h2>RM <?php echo number_format($today_revenue, 2); ?></h2>
        <p>Today's Revenue</p>
    </div>
    
    <div style="background: #8e44ad; color: white; padding: 30px; border-radius: 8px; text-align: center;">
        <h2><?php echo $total_items; ?></h2>
        <p>Menu Items</p>
    </div>
    
    <div style="background: #f39c12; color: white; padding: 30px; border-radius: 8px; text-align: center;">
        <h2><?php echo $total_qr_orders; ?></h2>
        <p>QR Orders Today</p>
    </div>
    
    <div style="background: #16a085; color: white; padding: 30px; border-radius: 8px; text-align: center;">
        <h2><?php echo $qr_orders_today; ?></h2>
        <p>Tables Used Today</p>
    </div>
</div> -->

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 30px 0;">
    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h3>Quick Actions</h3>
        <div style="margin: 20px 0;">
            <a href="manage_items.php" class="btn" style="display: block; margin: 10px 0;">Manage Menu Items</a>
            <a href="manage_orders.php" class="btn" style="display: block; margin: 10px 0;">Manage Orders</a>
            <!-- In the Quick Actions section -->
            <a href="generate.php" class="btn" style="display: block; margin: 10px 0; background: #f39c12;">🔳 Generate Restaurant QR Code</a>
         </div>
    </div>
    
    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h3>Recent Orders</h3>
        <?php
        try {
            $stmt = $pdo->query("
                SELECT o.id, o.order_type, o.table_number, o.total_amount, o.status, o.created_at, 
                       COALESCE(u.username, o.customer_name, 'Guest') as username
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                ORDER BY o.created_at DESC
                LIMIT 5
            ");
            $recent_orders = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Recent orders error: ' . $e->getMessage());
            $recent_orders = [];
        }
        ?>
        
        <?php if (empty($recent_orders)): ?>
            <p style="color: #666; margin: 20px 0;">No recent orders</p>
        <?php else: ?>
            <?php foreach ($recent_orders as $order): ?>
                <div style="padding: 10px 0; border-bottom: 1px solid #eee;">
                    <div style="display: flex; justify-content: space-between;">
                        <div>
                            <strong>Order #<?php echo $order['id']; ?></strong>
                            <?php if ($order['table_number']): ?>
                                <span style="background: #3498db; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px;">
                                    🔳 Table <?php echo $order['table_number']; ?>
                                </span>
                            <?php endif; ?>
                            <br>
                            <small><?php echo $order['username']; ?> • <?php echo ucfirst($order['order_type']); ?></small>
                        </div>
                        <div style="text-align: right;">
                            <strong>RM <?php echo number_format($order['total_amount'], 2); ?></strong><br>
                            <small style="color: #666;"><?php echo ucfirst($order['status']); ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div style="margin-top: 15px;">
            <a href="manage_orders.php" class="btn btn-secondary">View All Orders</a>
        </div>
    </div>
</div>

<!-- QR Code Quick Actions -->
<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 30px 0;">
    <h3>QR Code Management</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0;">
        <a href="../qr/generate.php" class="btn" style="text-decoration: none; padding: 15px; text-align: center;">
            🔳 Generate QR Codes<br>
            <small>Create QR codes for tables</small>
        </a>
        <a href="manage_orders.php?filter=dine-in" class="btn btn-secondary" style="text-decoration: none; padding: 15px; text-align: center;">
            🍽️ Table Orders<br>
            <small>View dine-in orders</small>
        </a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>