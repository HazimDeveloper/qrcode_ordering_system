<?php
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';

// Update order status
if ($_POST && isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $status = $_POST['status'];
    
    $valid_statuses = ['pending', 'confirmed', 'preparing', 'ready', 'completed'];
    if (in_array($status, $valid_statuses)) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        if ($stmt->execute([$status, $order_id])) {
            $message = 'Order status updated successfully!';
        } else {
            $error = 'Failed to update order status';
        }
    } else {
        $error = 'Invalid status selected';
    }
}

// Delete order (only if pending or cancelled)
if ($_GET['delete'] ?? false) {
    $order_id = (int)$_GET['delete'];
    
    // Check if order can be deleted (only pending orders)
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order_status = $stmt->fetchColumn();
    
    if ($order_status === 'pending') {
        try {
            $pdo->beginTransaction();
            
            // Delete order items first
            $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
            $stmt->execute([$order_id]);
            
            // Delete order
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            
            $pdo->commit();
            $message = 'Order deleted successfully!';
        } catch (Exception $e) {
            $pdo->rollback();
            $error = 'Failed to delete order';
        }
    } else {
        $error = 'Only pending orders can be deleted';
    }
}

// Get filter and search parameters
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build WHERE clause based on filters
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
    case 'dine-in':
        $where_conditions[] = "o.order_type = 'dine-in'";
        break;
    case 'takeaway':
        $where_conditions[] = "o.order_type = 'takeaway'";
        break;
}

// Add search condition
if (!empty($search)) {
    $where_conditions[] = "(u.username LIKE ? OR o.id = ?)";
    $params[] = "%$search%";
    $params[] = $search;
}

// Add date range conditions
if (!empty($date_from)) {
    $where_conditions[] = "DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get orders with pagination
$page = (int)($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Count total orders for pagination
$count_sql = "
    SELECT COUNT(o.id)
    FROM orders o
    JOIN users u ON o.user_id = u.id
    $where_clause
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_orders = $stmt->fetchColumn();
$total_pages = ceil($total_orders / $limit);

// Get orders
$sql = "
    SELECT o.*, u.username, u.email,
           COUNT(oi.id) as item_count,
           SUM(oi.quantity) as total_items
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    $where_clause
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get order statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status IN ('pending', 'confirmed', 'preparing') THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN total_amount ELSE 0 END) as today_revenue,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_orders
    FROM orders
";
$stmt = $pdo->query($stats_sql);
$stats = $stmt->fetch();

include '../includes/header.php';
?>

<h1>Manage Orders</h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<!-- Statistics Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0;">
    <div style="background: #3498db; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo $stats['total_orders']; ?></h3>
        <p>Total Orders</p>
    </div>
    <div style="background: #f39c12; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo $stats['pending_orders']; ?></h3>
        <p>Pending</p>
    </div>
    <div style="background: #27ae60; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo $stats['ready_orders']; ?></h3>
        <p>Ready</p>
    </div>
    <div style="background: #95a5a6; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo $stats['completed_orders']; ?></h3>
        <p>Completed</p>
    </div>
    <div style="background: #e67e22; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo $stats['today_orders']; ?></h3>
        <p>Today's Orders</p>
    </div>
    <div style="background: #8e44ad; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3>RM <?php echo number_format($stats['today_revenue'], 2); ?></h3>
        <p>Today's Revenue</p>
    </div>
</div>

<!-- Search and Filter Form -->
<div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin: 20px 0;">
    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
        <div>
            <label for="search" style="display: block; margin-bottom: 5px; font-weight: bold;">Search:</label>
            <input type="text" name="search" id="search" placeholder="Order ID or Customer name" 
                   value="<?php echo htmlspecialchars($search); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <div>
            <label for="filter" style="display: block; margin-bottom: 5px; font-weight: bold;">Filter:</label>
            <select name="filter" id="filter" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Orders</option>
                <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending Orders</option>
                <option value="ready" <?php echo $filter === 'ready' ? 'selected' : ''; ?>>Ready Orders</option>
                <option value="completed" <?php echo $filter === 'completed' ? 'selected' : ''; ?>>Completed Orders</option>
                <option value="today" <?php echo $filter === 'today' ? 'selected' : ''; ?>>Today's Orders</option>
                <option value="dine-in" <?php echo $filter === 'dine-in' ? 'selected' : ''; ?>>Dine-in Orders</option>
                <option value="takeaway" <?php echo $filter === 'takeaway' ? 'selected' : ''; ?>>Takeaway Orders</option>
            </select>
        </div>
        
        <div>
            <label for="date_from" style="display: block; margin-bottom: 5px; font-weight: bold;">From Date:</label>
            <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <div>
            <label for="date_to" style="display: block; margin-bottom: 5px; font-weight: bold;">To Date:</label>
            <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <div>
            <button type="submit" class="btn">Search & Filter</button>
        </div>
        
        <div>
            <a href="manage_orders.php" class="btn btn-secondary">Clear Filters</a>
        </div>
    </form>
</div>

<!-- Quick Filter Buttons -->
<div style="margin: 20px 0; display: flex; gap: 10px; flex-wrap: wrap;">
    <a href="?filter=all" class="btn <?php echo $filter === 'all' ? '' : 'btn-secondary'; ?>">All (<?php echo $stats['total_orders']; ?>)</a>
    <a href="?filter=pending" class="btn <?php echo $filter === 'pending' ? '' : 'btn-secondary'; ?>">Pending (<?php echo $stats['pending_orders']; ?>)</a>
    <a href="?filter=ready" class="btn <?php echo $filter === 'ready' ? '' : 'btn-secondary'; ?>">Ready (<?php echo $stats['ready_orders']; ?>)</a>
    <a href="?filter=completed" class="btn <?php echo $filter === 'completed' ? '' : 'btn-secondary'; ?>">Completed (<?php echo $stats['completed_orders']; ?>)</a>
    <a href="?filter=today" class="btn <?php echo $filter === 'today' ? '' : 'btn-secondary'; ?>">Today (<?php echo $stats['today_orders']; ?>)</a>
</div>

<!-- Orders List -->
<?php if (empty($orders)): ?>
    <div style="text-align: center; padding: 50px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h3>No orders found</h3>
        <p>No orders match the current filter criteria.</p>
        <?php if (!empty($search) || $filter !== 'all' || !empty($date_from) || !empty($date_to)): ?>
            <a href="manage_orders.php" class="btn">Clear Filters</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div style="display: grid; gap: 20px;">
        <?php foreach ($orders as $order): ?>
            <div style="background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px; border-left: 5px solid <?php 
                $status_colors = [
                    'pending' => '#f39c12',
                    'confirmed' => '#3498db',
                    'preparing' => '#e67e22',
                    'ready' => '#27ae60',
                    'completed' => '#95a5a6'
                ];
                echo $status_colors[$order['status']] ?? '#95a5a6';
            ?>;">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 20px; align-items: start;">
                    <!-- Order Basic Info -->
                    <div>
                        <h3 style="margin: 0 0 10px 0; color: #2c3e50;">Order #<?php echo $order['id']; ?></h3>
                        <p style="margin: 5px 0;"><strong>Customer:</strong> <?php echo htmlspecialchars($order['username']); ?></p>
                        <p style="margin: 5px 0;"><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
                        <p style="margin: 5px 0;"><strong>Type:</strong> 
                            <span style="background: #ecf0f1; padding: 2px 6px; border-radius: 3px;">
                                <?php echo ucfirst($order['order_type']); ?>
                            </span>
                            <?php if ($order['table_number']): ?>
                                <span style="background: #3498db; color: white; padding: 2px 6px; border-radius: 3px; margin-left: 5px;">
                                    Table <?php echo $order['table_number']; ?>
                                </span>
                            <?php endif; ?>
                        </p>
                        <p style="margin: 5px 0;"><strong>Time:</strong> <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></p>
                        <p style="margin: 5px 0;"><strong>Total:</strong> 
                            <span style="font-size: 18px; font-weight: bold; color: #e67e22;">
                                RM <?php echo number_format($order['total_amount'], 2); ?>
                            </span>
                        </p>
                    </div>
                    
                    <!-- Order Items -->
                    <div>
                        <h4 style="margin: 0 0 10px 0;">Items (<?php echo $order['total_items']; ?> total):</h4>
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT oi.quantity, m.name, oi.price, (oi.quantity * oi.price) as subtotal
                            FROM order_items oi
                            JOIN menu_items m ON oi.menu_item_id = m.id
                            WHERE oi.order_id = ?
                            ORDER BY m.name
                        ");
                        $stmt->execute([$order['id']]);
                        $order_items = $stmt->fetchAll();
                        ?>
                        
                        <div style="font-size: 14px; max-height: 120px; overflow-y: auto; border: 1px solid #ecf0f1; border-radius: 4px; padding: 8px;">
                            <?php foreach ($order_items as $item): ?>
                                <div style="margin: 3px 0; display: flex; justify-content: space-between;">
                                    <span><?php echo $item['quantity']; ?>× <?php echo htmlspecialchars($item['name']); ?></span>
                                    <span style="font-weight: bold;">RM <?php echo number_format($item['subtotal'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Status and Time Info -->
                    <div>
                        <h4 style="margin: 0 0 10px 0;">Order Status:</h4>
                        <div style="margin-bottom: 15px; text-align: center;">
                            <?php
                            $color = $status_colors[$order['status']] ?? '#95a5a6';
                            $time_diff = time() - strtotime($order['created_at']);
                            $minutes_ago = floor($time_diff / 60);
                            ?>
                            <div style="background: <?php echo $color; ?>; color: white; padding: 8px 12px; border-radius: 5px; font-weight: bold; margin-bottom: 5px;">
                                <?php echo ucfirst($order['status']); ?>
                            </div>
                            <small style="color: #7f8c8d;">
                                <?php 
                                if ($minutes_ago < 60) {
                                    echo $minutes_ago . ' min ago';
                                } else {
                                    echo floor($minutes_ago / 60) . 'h ' . ($minutes_ago % 60) . 'm ago';
                                }
                                ?>
                            </small>
                        </div>
                        
                        <!-- Status Update Form -->
                        <form method="POST" style="display: flex; flex-direction: column; gap: 8px;">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            
                            <select name="status" style="padding: 6px; font-size: 12px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>📋 Pending</option>
                                <option value="confirmed" <?php echo $order['status'] === 'confirmed' ? 'selected' : ''; ?>>✅ Confirmed</option>
                                <option value="preparing" <?php echo $order['status'] === 'preparing' ? 'selected' : ''; ?>>👨‍🍳 Preparing</option>
                                <option value="ready" <?php echo $order['status'] === 'ready' ? 'selected' : ''; ?>>🔔 Ready</option>
                                <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>✅ Completed</option>
                            </select>
                            
                            <button type="submit" name="update_status" class="btn" style="padding: 6px 12px; font-size: 12px;">
                                Update Status
                            </button>
                        </form>
                    </div>
                    
                    <!-- Actions -->
                    <div style="text-align: center;">
                        <h4 style="margin: 0 0 10px 0;">Actions:</h4>
                        
                        <!-- Print Order Button -->
                        <button onclick="printOrder(<?php echo $order['id']; ?>)" class="btn btn-secondary" style="display: block; width: 100%; margin: 5px 0; padding: 8px; font-size: 12px;">
                            🖨️ Print Order
                        </button>
                        
                        <!-- View Details Button -->
                        <button onclick="viewOrderDetails(<?php echo $order['id']; ?>)" class="btn btn-secondary" style="display: block; width: 100%; margin: 5px 0; padding: 8px; font-size: 12px;">
                            👁️ View Details
                        </button>
                        
                        <!-- Delete Button (only for pending orders) -->
                        <?php if ($order['status'] === 'pending'): ?>
                            <a href="?delete=<?php echo $order['id']; ?>" 
                               onclick="return confirm('Are you sure you want to delete this order? This action cannot be undone.')" 
                               class="btn" style="display: block; width: 100%; margin: 5px 0; padding: 8px; font-size: 12px; background: #e74c3c; text-decoration: none;">
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
            
            <span style="margin: 0 15px;">
                Page <?php echo $page; ?> of <?php echo $total_pages; ?> 
                (<?php echo $total_orders; ?> total orders)
            </span>
            
            <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn btn-secondary">Next →</a>
            <?php endif; ?>
        </div>
        
        <!-- Page Jump -->
        <div style="text-align: center; margin: 20px 0;">
            <form method="GET" style="display: inline-block;">
                <?php foreach ($_GET as $key => $value): ?>
                    <?php if ($key !== 'page'): ?>
                        <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                <label for="page_jump">Go to page:</label>
                <input type="number" name="page" id="page_jump" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $page; ?>" style="width: 60px; padding: 5px; margin: 0 5px;">
                <button type="submit" class="btn btn-secondary" style="padding: 5px 10px;">Go</button>
            </form>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Order Details Modal -->
<div id="orderModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; width: 90%; max-width: 600px; max-height: 80%; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 id="modalTitle">Order Details</h3>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">×</button>
        </div>
        <div id="modalContent">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>

<div style="text-align: center; margin: 30px 0;">
    <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    <button onclick="window.print()" class="btn">🖨️ Print Page</button>
</div>

<script>
function viewOrderDetails(orderId) {
    document.getElementById('modalTitle').textContent = 'Order #' + orderId + ' Details';
    document.getElementById('modalContent').innerHTML = '<p>Loading order details...</p>';
    document.getElementById('orderModal').style.display = 'block';
    
    // In a real application, you would fetch order details via AJAX
    // For now, we'll just show a placeholder
    setTimeout(() => {
        document.getElementById('modalContent').innerHTML = `
            <div style="text-align: center; padding: 20px;">
                <h4>Order #${orderId}</h4>
                <p>Detailed order information would be displayed here.</p>
                <p>This could include:</p>
                <ul style="text-align: left; max-width: 300px; margin: 0 auto;">
                    <li>Customer contact information</li>
                    <li>Detailed item specifications</li>
                    <li>Special instructions</li>
                    <li>Payment details</li>
                    <li>Order timeline</li>
                </ul>
                <button onclick="closeModal()" class="btn" style="margin-top: 20px;">Close</button>
            </div>
        `;
    }, 500);
}

function printOrder(orderId) {
    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Order #${orderId}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
                .order-info { margin: 20px 0; }
                .items { border-collapse: collapse; width: 100%; }
                .items th, .items td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .items th { background-color: #f2f2f2; }
                .total { text-align: right; font-weight: bold; font-size: 18px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>QR Food Ordering</h1>
                <h2>Order #${orderId}</h2>
                <p>Date: ${new Date().toLocaleDateString()}</p>
            </div>
            <div class="order-info">
                <p><strong>Order details would be printed here</strong></p>
                <p>This is a sample print format.</p>
            </div>
            <script>
                window.onload = function() {
                    window.print();
                    window.close();
                }
            </script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

function closeModal() {
    document.getElementById('orderModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('orderModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Auto-refresh orders every 30 seconds for real-time updates
setInterval(function() {
    // Only refresh if no modals are open and no forms are being submitted
    if (document.getElementById('orderModal').style.display === 'none') {
        // You could implement AJAX refresh here
        // For now, we'll just add a visual indicator
        console.log('Auto-refresh: Checking for new orders...');
    }
}, 30000);

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Escape key to close modal
    if (e.key === 'Escape') {
        closeModal();
    }
    
    // Ctrl/Cmd + P to print
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        window.print();
    }
});

// Status color coding for better UX
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects and animations
    const orderCards = document.querySelectorAll('[style*="border-left: 5px solid"]');
    orderCards.forEach(card => {
        card.style.transition = 'transform 0.2s, box-shadow 0.2s';
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 15px rgba(0,0,0,0.15)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 2px 5px rgba(0,0,0,0.1)';
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>