<?php
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';

// Update order status
// At the top of the file, after the existing update code
if ($_POST && isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $status = $_POST['status'];
    
    $valid_statuses = ['pending', 'confirmed', 'preparing', 'ready', 'completed'];
    if (in_array($status, $valid_statuses)) {
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            if ($stmt->execute([$status, $order_id])) {
                $message = 'Order status updated successfully! Order ID: ' . $order_id . ', New Status: ' . $status;
                
                // Verify the update
                $verify = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
                $verify->execute([$order_id]);
                $new_status = $verify->fetchColumn();
                $message .= " | Verified new status: $new_status";
            } else {
                $error = 'Failed to update order status';
            }
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid status selected';
    }
}

// Delete order (only if pending)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $order_id = (int)$_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order_status = $stmt->fetchColumn();
        
        if ($order_status === 'pending') {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $pdo->commit();
            $message = 'Order deleted successfully!';
        } else {
            $error = 'Only pending orders can be deleted';
        }
    } catch (Exception $e) {
        $pdo->rollback();
        $error = 'Failed to delete order';
    }
}

// Get filter and search parameters
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_conditions = [];
$params = [];

switch ($filter) {
    case 'pending':
        $where_conditions[] = "o.status IN ('pending', 'confirmed', 'preparing')";
        break;
    case 'ready':
        $where_conditions[] = "o.status = 'ready'";
        break;
    case 'completed':
        $where_conditions[] = "o.status = 'completed'";
        break;
    case 'today':
        $where_conditions[] = "DATE(o.created_at) = CURDATE()";
        break;
}

if (!empty($search)) {
    if (is_numeric($search)) {
        $where_conditions[] = "o.id = ?";
        $params[] = (int)$search;
    } else {
        $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Get order statistics
    $stats_sql = "
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status IN ('pending', 'confirmed', 'preparing') THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready_orders,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_orders,
            COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN total_amount ELSE 0 END), 0) as today_revenue
        FROM orders
    ";
    $stmt = $pdo->query($stats_sql);
    $stats = $stmt->fetch();

    // Count total orders for pagination
    $count_sql = "SELECT COUNT(DISTINCT o.id) FROM orders o LEFT JOIN users u ON o.user_id = u.id $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_orders = $stmt->fetchColumn();
    $total_pages = ceil($total_orders / $limit);

    // Get orders - using direct values for LIMIT and OFFSET since they can't be bound parameters
    $sql = "
        SELECT o.*, 
               COALESCE(u.username, 'Guest') as username, 
               COALESCE(u.email, o.customer_phone) as email,
               COUNT(oi.id) as item_count,
               SUM(oi.quantity) as total_items
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        $where_clause
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
    $orders = [];
    $stats = ['total_orders' => 0, 'pending_orders' => 0, 'ready_orders' => 0, 'completed_orders' => 0, 'today_orders' => 0, 'today_revenue' => 0];
}

include '../includes/header.php';
?>

<h1>Manage Orders</h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- Statistics Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0;">
    <div style="background: #3498db; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo (int)$stats['total_orders']; ?></h3>
        <p>Total Orders</p>
    </div>
    <div style="background: #f39c12; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo (int)$stats['pending_orders']; ?></h3>
        <p>Pending</p>
    </div>
    <div style="background: #27ae60; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo (int)$stats['ready_orders']; ?></h3>
        <p>Ready</p>
    </div>
    <div style="background: #95a5a6; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo (int)$stats['completed_orders']; ?></h3>
        <p>Completed</p>
    </div>
    <div style="background: #e67e22; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo (int)$stats['today_orders']; ?></h3>
        <p>Today's Orders</p>
    </div>
    <div style="background: #8e44ad; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3>RM <?php echo number_format((float)$stats['today_revenue'], 2); ?></h3>
        <p>Today's Revenue</p>
    </div>
</div>

<!-- Search and Filter -->
<div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin: 20px 0;">
    <form method="GET" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 200px;">
            <label for="search" style="display: block; margin-bottom: 5px; font-weight: bold;">Search:</label>
            <input type="text" name="search" id="search" placeholder="Order ID or Customer name/email" 
                   value="<?php echo htmlspecialchars($search); ?>" 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <div style="flex: 1; min-width: 150px;">
            <label for="filter" style="display: block; margin-bottom: 5px; font-weight: bold;">Filter:</label>
            <select name="filter" id="filter" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Orders</option>
                <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending Orders</option>
                <option value="ready" <?php echo $filter === 'ready' ? 'selected' : ''; ?>>Ready Orders</option>
                <option value="completed" <?php echo $filter === 'completed' ? 'selected' : ''; ?>>Completed Orders</option>
                <option value="today" <?php echo $filter === 'today' ? 'selected' : ''; ?>>Today's Orders</option>
            </select>
        </div>
        
        <div>
            <button type="submit" class="btn">Search</button>
        </div>
        
        <div>
            <a href="manage_orders.php" class="btn btn-secondary">Clear</a>
        </div>
    </form>
</div>

<!-- Quick Filter Buttons -->
<div style="margin: 20px 0; display: flex; gap: 10px; flex-wrap: wrap;">
    <a href="?filter=all" class="btn <?php echo $filter === 'all' ? '' : 'btn-secondary'; ?>">All (<?php echo (int)$stats['total_orders']; ?>)</a>
    <a href="?filter=pending" class="btn <?php echo $filter === 'pending' ? '' : 'btn-secondary'; ?>">Pending (<?php echo (int)$stats['pending_orders']; ?>)</a>
    <a href="?filter=ready" class="btn <?php echo $filter === 'ready' ? '' : 'btn-secondary'; ?>">Ready (<?php echo (int)$stats['ready_orders']; ?>)</a>
    <a href="?filter=completed" class="btn <?php echo $filter === 'completed' ? '' : 'btn-secondary'; ?>">Completed (<?php echo (int)$stats['completed_orders']; ?>)</a>
    <a href="?filter=today" class="btn <?php echo $filter === 'today' ? '' : 'btn-secondary'; ?>">Today (<?php echo (int)$stats['today_orders']; ?>)</a>
</div>

<!-- Orders List -->
<?php if (empty($orders)): ?>
    <div style="text-align: center; padding: 50px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h3>No orders found</h3>
        <p>No orders match the current filter criteria.</p>
    </div>
<?php else: ?>
    <div style="display: grid; gap: 20px;">
        <?php foreach ($orders as $order): ?>
            <?php
            $status_colors = [
                'pending' => '#f39c12',
                'confirmed' => '#3498db', 
                'preparing' => '#e67e22',
                'ready' => '#27ae60',
                'completed' => '#95a5a6'
            ];
            $color = $status_colors[$order['status']] ?? '#95a5a6';
            ?>
            <div style="background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px; border-left: 5px solid <?php echo $color; ?>;">
                <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 20px; align-items: start;">
                    <!-- Order Info -->
                    <div>
                        <h3 style="margin: 0 0 10px 0; color: #2c3e50;">Order #<?php echo (int)$order['id']; ?></h3>
                        <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['username']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
                        <p><strong>Type:</strong> <?php echo htmlspecialchars(ucfirst($order['order_type'])); ?>
                            <?php if (!empty($order['table_number'])): ?>
                                | Table <?php echo (int)$order['table_number']; ?>
                            <?php endif; ?>
                        </p>
                        <p><strong>Time:</strong> <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($order['created_at']))); ?></p>
                        <p><strong>Total:</strong> <span style="font-size: 18px; font-weight: bold; color: #e67e22;">RM <?php echo number_format((float)$order['total_amount'], 2); ?></span></p>
                    </div>
                    
                    <!-- Order Items -->
                    <div>
                        <h4 style="margin: 0 0 10px 0;">Items (<?php echo (int)$order['total_items']; ?> total):</h4>
                        <?php
                        try {
                            $stmt = $pdo->prepare("
                                SELECT oi.quantity, m.name, oi.price, (oi.quantity * oi.price) as subtotal
                                FROM order_items oi
                                JOIN menu_items m ON oi.menu_item_id = m.id
                                WHERE oi.order_id = ?
                                ORDER BY m.name
                            ");
                            $stmt->execute([$order['id']]);
                            $order_items = $stmt->fetchAll();
                        } catch (Exception $e) {
                            $order_items = [];
                        }
                        ?>
                        
                        <div style="font-size: 14px; max-height: 120px; overflow-y: auto; border: 1px solid #ecf0f1; border-radius: 4px; padding: 8px;">
                            <?php if (!empty($order_items)): ?>
                                <?php foreach ($order_items as $item): ?>
                                    <div style="margin: 3px 0; display: flex; justify-content: space-between;">
                                        <span><?php echo (int)$item['quantity']; ?>× <?php echo htmlspecialchars($item['name']); ?></span>
                                        <span style="font-weight: bold;">RM <?php echo number_format((float)$item['subtotal'], 2); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: #7f8c8d; font-style: italic;">No items found</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Status & Actions -->
                    <div style="text-align: center; min-width: 200px;">
                        <div style="background: <?php echo $color; ?>; color: white; padding: 8px 12px; border-radius: 5px; font-weight: bold; margin-bottom: 15px;">
                            <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                        </div>
                        
                        <!-- Status Update Form -->
                        <form method="POST" style="margin-bottom: 10px;">
                            <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                            <input type="hidden" name="page" value="<?php echo (int)$page; ?>">
                            <?php if (!empty($search)): ?>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <?php endif; ?>
                            <select name="status" style="width: 100%; padding: 6px; margin-bottom: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>📋 Pending</option>
                                <option value="confirmed" <?php echo $order['status'] === 'confirmed' ? 'selected' : ''; ?>>✅ Confirmed</option>
                                <option value="preparing" <?php echo $order['status'] === 'preparing' ? 'selected' : ''; ?>>👨‍🍳 Preparing</option>
                                <option value="ready" <?php echo $order['status'] === 'ready' ? 'selected' : ''; ?>>🔔 Ready</option>
                                <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>✅ Completed</option>
                            </select>
                            <button type="submit" name="update_status" class="btn" style="width: 100%; padding: 6px; font-size: 12px;">Update Status</button>
                        </form>
                        
                        <!-- Delete Button (only for pending orders) -->
                        <?php if ($order['status'] === 'pending'): ?>
                            <a href="?delete=<?php echo (int)$order['id']; ?>" 
                               onclick="return confirm('Are you sure you want to delete this order?')" 
                               class="btn" style="width: 100%; padding: 6px; font-size: 12px; background: #e74c3c; text-decoration: none; display: block;">
                                🗑️ Delete Order
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div style="display: flex; justify-content: center; align-items: center; margin: 30px 0; gap: 10px;">
            <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn btn-secondary">← Previous</a>
            <?php endif; ?>
            
            <span style="margin: 0 15px;">Page <?php echo (int)$page; ?> of <?php echo (int)$total_pages; ?></span>
            
            <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn btn-secondary">Next →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div style="text-align: center; margin: 30px 0;">
    <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    <button onclick="window.print()" class="btn">🖨️ Print Page</button>
</div>

<script>
// Comment out or remove this code
/*
// Auto-refresh every 30 seconds
setInterval(function() {
    if (!document.querySelector('input:focus, select:focus')) {
        location.reload();
    }
}, 30000);
*/

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        window.print();
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        document.getElementById('search').focus();
    }
});

// Form loading states
document.querySelectorAll('form[method="POST"]').forEach(form => {
    form.addEventListener('submit', function() {
        const btn = this.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Updating...';
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>