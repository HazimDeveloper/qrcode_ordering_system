<?php
require_once '../config/database.php';

// Security check - ensure user is logged in and is staff
if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';

// Update order status - FIXED with better error handling
if ($_POST && isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $status = trim($_POST['status']);
    
    // Validate inputs
    $valid_statuses = ['pending', 'confirmed', 'preparing', 'ready', 'completed'];
    
    if (!$order_id || $order_id <= 0) {
        $error = 'Invalid order ID';
    } elseif (!in_array($status, $valid_statuses)) {
        $error = 'Invalid status selected';
    } else {
        try {
            // First check if order exists
            $check_stmt = $pdo->prepare("SELECT id, status FROM orders WHERE id = ?");
            $check_stmt->execute([$order_id]);
            $existing_order = $check_stmt->fetch();
            
            if (!$existing_order) {
                $error = 'Order not found';
            } else {
                // Update the order status
                $update_stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $update_result = $update_stmt->execute([$status, $order_id]);
                
                if ($update_result) {
                    // Verify the update worked by checking the affected rows
                    $affected_rows = $update_stmt->rowCount();
                    
                    if ($affected_rows > 0) {
                        // Double-check by fetching the updated status
                        $verify_stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
                        $verify_stmt->execute([$order_id]);
                        $new_status = $verify_stmt->fetchColumn();
                        
                        if ($new_status === $status) {
                            $message = "Order #$order_id status successfully updated from '{$existing_order['status']}' to '$status'";
                            
                            // Redirect to maintain GET parameters
                            if (isset($_POST['current_filter']) || isset($_POST['current_search']) || isset($_POST['current_page'])) {
                                $redirect_params = [];
                                if (isset($_POST['current_filter'])) $redirect_params['filter'] = $_POST['current_filter'];
                                if (isset($_POST['current_search'])) $redirect_params['search'] = $_POST['current_search'];
                                if (isset($_POST['current_page'])) $redirect_params['page'] = $_POST['current_page'];
                                
                                $redirect_url = 'manage_orders.php?' . http_build_query($redirect_params) . '&message=' . urlencode($message);
                                header("Location: $redirect_url");
                                exit;
                            }
                        } else {
                            $error = "Update appeared to succeed but verification failed. Expected: $status, Got: $new_status";
                        }
                    } else {
                        // This could happen if the status is already set to the selected value
                        $message = "Order #$order_id status is already set to '$status'";
                    }
                } else {
                    $error = 'Failed to execute update query';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        } catch (Exception $e) {
            $error = 'Unexpected error: ' . $e->getMessage();
        }
    }
}

// Handle message from redirect
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

// Delete order (only if pending) - IMPROVED
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $order_id = (int)$_GET['delete'];
    
    try {
        // Check order status before deletion
        $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order_status = $stmt->fetchColumn();
        
        if (!$order_status) {
            $error = 'Order not found';
        } elseif ($order_status !== 'pending') {
            $error = 'Only pending orders can be deleted. This order is: ' . $order_status;
        } else {
            // Use transaction for safe deletion
            $pdo->beginTransaction();
            
            // Delete order items first (foreign key constraint)
            $stmt1 = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
            $result1 = $stmt1->execute([$order_id]);
            
            // Then delete the order
            $stmt2 = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            $result2 = $stmt2->execute([$order_id]);
            
            if ($result1 && $result2) {
                $pdo->commit();
                $message = "Order #$order_id deleted successfully!";
            } else {
                $pdo->rollback();
                $error = 'Failed to delete order completely';
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        $error = 'Failed to delete order: ' . $e->getMessage();
    }
}

// Get filter and search parameters with validation
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Validate filter parameter
$valid_filters = ['all', 'pending', 'ready', 'completed', 'today', 'dine-in'];
if (!in_array($filter, $valid_filters)) {
    $filter = 'all';
}

// Build WHERE clause with proper parameter binding
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
        $where_conditions[] = "o.table_number IS NOT NULL";
        break;
}

if (!empty($search)) {
    if (is_numeric($search)) {
        // Search by order ID
        $where_conditions[] = "o.id = ?";
        $params[] = (int)$search;
    } else {
        // Search by customer name, email, or phone
        $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Get order statistics - IMPROVED with better error handling
    $stats_sql = "
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status IN ('pending', 'confirmed', 'preparing') THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready_orders,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_orders,
            COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN total_amount ELSE 0 END), 0) as today_revenue,
            SUM(CASE WHEN table_number IS NOT NULL THEN 1 ELSE 0 END) as qr_orders
        FROM orders
    ";
    $stmt = $pdo->query($stats_sql);
    $stats = $stmt->fetch();
    
    // Ensure all stats are integers/floats
    foreach ($stats as $key => $value) {
        $stats[$key] = is_numeric($value) ? (float)$value : 0;
    }

    // Count total orders for pagination
    $count_sql = "SELECT COUNT(DISTINCT o.id) FROM orders o LEFT JOIN users u ON o.user_id = u.id $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_orders = $count_stmt->fetchColumn();
    $total_pages = ceil($total_orders / $limit);

    // Get orders with all necessary information
    $sql = "
        SELECT o.*, 
               COALESCE(u.username, o.customer_name, 'Guest') as username, 
               COALESCE(u.email, o.customer_phone, 'N/A') as contact_info,
               o.customer_name,
               o.customer_phone,
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
    $order_stmt = $pdo->prepare($sql);
    $order_stmt->execute($params);
    $orders = $order_stmt->fetchAll();

} catch (Exception $e) {
    $error = 'Database error while fetching data: ' . $e->getMessage();
    $orders = [];
    $stats = [
        'total_orders' => 0, 
        'pending_orders' => 0, 
        'ready_orders' => 0, 
        'completed_orders' => 0, 
        'today_orders' => 0, 
        'today_revenue' => 0,
        'qr_orders' => 0
    ];
    $total_pages = 0;
}

$page_title = 'Manage Orders';
include '../includes/header.php';
?>

<h1>Manage Orders</h1>

<?php if ($error): ?>
    <div class="alert alert-error" style="margin: 20px 0; padding: 15px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px;">
        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-success" style="margin: 20px 0; padding: 15px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 5px;">
        <strong>Success:</strong> <?php echo htmlspecialchars($message); ?>
    </div>
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
        <h3>RM <?php echo number_format($stats['today_revenue'], 2); ?></h3>
        <p>Today's Revenue</p>
    </div>
    <div style="background: #16a085; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo (int)$stats['qr_orders']; ?></h3>
        <p>QR Orders</p>
    </div>
</div>

<!-- Search and Filter -->
<div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin: 20px 0;">
    <form method="GET" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 200px;">
            <label for="search" style="display: block; margin-bottom: 5px; font-weight: bold;">Search:</label>
            <input type="text" name="search" id="search" placeholder="Order ID, customer name, email, or phone" 
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
                <option value="dine-in" <?php echo $filter === 'dine-in' ? 'selected' : ''; ?>>QR/Dine-in Orders</option>
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
    <a href="?filter=dine-in" class="btn <?php echo $filter === 'dine-in' ? '' : 'btn-secondary'; ?>">QR Orders (<?php echo (int)$stats['qr_orders']; ?>)</a>
</div>

<!-- Orders List -->
<?php if (empty($orders)): ?>
    <div style="text-align: center; padding: 50px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h3>No orders found</h3>
        <p>No orders match the current filter criteria.</p>
        <?php if (!empty($search) || $filter !== 'all'): ?>
            <a href="manage_orders.php" class="btn">Show All Orders</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div style="display: grid; gap: 20px;">
        <?php foreach ($orders as $order): ?>
            <?php
            // Define status colors
            $status_colors = [
                'pending' => '#f39c12',
                'confirmed' => '#3498db', 
                'preparing' => '#e67e22',
                'ready' => '#27ae60',
                'completed' => '#95a5a6'
            ];
            $color = $status_colors[$order['status']] ?? '#95a5a6';
            
            // Determine customer display name
            $customer_display = $order['username'];
            if ($order['customer_name'] && $order['username'] === 'Guest') {
                $customer_display = $order['customer_name'];
            }
            
            // Determine contact info
            $contact_display = $order['contact_info'];
            if ($order['customer_phone'] && !$order['user_id']) {
                $contact_display = $order['customer_phone'];
            }
            ?>
            <div style="background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px; border-left: 5px solid <?php echo $color; ?>;">
                <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 20px; align-items: start;">
                    <!-- Order Info -->
                    <div>
                        <h3 style="margin: 0 0 10px 0; color: #2c3e50;">
                            Order #<?php echo (int)$order['id']; ?>
                            <?php if ($order['table_number']): ?>
                                <span style="background: #3498db; color: white; padding: 3px 8px; border-radius: 12px; font-size: 12px; margin-left: 10px;">
                                    🔳 Table <?php echo (int)$order['table_number']; ?>
                                </span>
                            <?php endif; ?>
                        </h3>
                        <p><strong>Customer:</strong> <?php echo htmlspecialchars($customer_display); ?></p>
                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($contact_display); ?></p>
                        <p><strong>Type:</strong> <?php echo htmlspecialchars(ucfirst($order['order_type'])); ?></p>
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
                        
                        <!-- Status Update Form with CSRF protection -->
                        <form method="POST" action="manage_orders.php" style="margin-bottom: 10px;" onsubmit="return confirmStatusUpdate(this)">
                            <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                            <!-- Preserve current filter and search parameters -->
                            <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($filter); ?>">
                            <input type="hidden" name="current_search" value="<?php echo htmlspecialchars($search); ?>">
                            <input type="hidden" name="current_page" value="<?php echo (int)$page; ?>">
                            
                            <select name="status" id="status_<?php echo $order['id']; ?>" style="width: 100%; padding: 6px; margin-bottom: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>📋 Pending</option>
                                <option value="confirmed" <?php echo $order['status'] === 'confirmed' ? 'selected' : ''; ?>>✅ Confirmed</option>
                                <option value="preparing" <?php echo $order['status'] === 'preparing' ? 'selected' : ''; ?>>👨‍🍳 Preparing</option>
                                <option value="ready" <?php echo $order['status'] === 'ready' ? 'selected' : ''; ?>>🔔 Ready</option>
                                <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>✅ Completed</option>
                            </select>
                            <!-- <button type="submit" name="update_status" value="1" class="btn" style="width: 100%; padding: 6px; font-size: 12px;">
                                Update Status
                            </button> -->
                        </form>
                        
                        <!-- Delete Button (only for pending orders) -->
                        <?php if ($order['status'] === 'pending'): ?>
                            <a href="?delete=<?php echo (int)$order['id']; ?>&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page; ?>" 
                               onclick="return confirm('Are you sure you want to delete Order #<?php echo $order['id']; ?>? This action cannot be undone.')" 
                               class="btn" style="width: 100%; padding: 6px; font-size: 12px; background: #e74c3c; text-decoration: none; display: block;">
                                🗑️ Delete Order
                            </a>
                        <?php endif; ?>
                        
                        <!-- Additional Quick Actions -->
                        <div style="margin-top: 10px; font-size: 11px;">
                            <?php if ($order['status'] === 'pending'): ?>
                                <button onclick="quickUpdateStatus(<?php echo $order['id']; ?>, 'confirmed')" 
                                        class="btn btn-secondary" style="width: 100%; padding: 4px; margin-bottom: 3px;">
                                    Quick Confirm
                                </button>
                            <?php elseif ($order['status'] === 'confirmed'): ?>
                                <button onclick="quickUpdateStatus(<?php echo $order['id']; ?>, 'preparing')" 
                                        class="btn btn-secondary" style="width: 100%; padding: 4px; margin-bottom: 3px;">
                                    Start Preparing
                                </button>
                            <?php elseif ($order['status'] === 'preparing'): ?>
                                <button onclick="quickUpdateStatus(<?php echo $order['id']; ?>, 'ready')" 
                                        class="btn btn-secondary" style="width: 100%; padding: 4px; margin-bottom: 3px;">
                                    Mark Ready
                                </button>
                            <?php elseif ($order['status'] === 'ready'): ?>
                                <button onclick="quickUpdateStatus(<?php echo $order['id']; ?>, 'completed')" 
                                        class="btn btn-secondary" style="width: 100%; padding: 4px; margin-bottom: 3px;">
                                    Complete Order
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div style="display: flex; justify-content: center; align-items: center; margin: 30px 0; gap: 10px;">
            <?php
            // Build query string for pagination links
            $query_params = [];
            if (!empty($search)) $query_params['search'] = $search;
            if ($filter !== 'all') $query_params['filter'] = $filter;
            ?>
            
            <?php if ($page > 1): ?>
                <?php $prev_params = array_merge($query_params, ['page' => $page - 1]); ?>
                <a href="?<?php echo http_build_query($prev_params); ?>" class="btn btn-secondary">← Previous</a>
            <?php endif; ?>
            
            <span style="margin: 0 15px;">
                Page <?php echo (int)$page; ?> of <?php echo (int)$total_pages; ?> 
                (<?php echo (int)$total_orders; ?> total orders)
            </span>
            
            <?php if ($page < $total_pages): ?>
                <?php $next_params = array_merge($query_params, ['page' => $page + 1]); ?>
                <a href="?<?php echo http_build_query($next_params); ?>" class="btn btn-secondary">Next →</a>
            <?php endif; ?>
        </div>
        
        <!-- Page Jump -->
        <div style="text-align: center; margin: 20px 0;">
            <form method="GET" style="display: inline-block;">
                <?php foreach ($query_params as $key => $value): ?>
                    <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                <?php endforeach; ?>
                <label for="jump_page">Jump to page:</label>
                <input type="number" name="page" id="jump_page" min="1" max="<?php echo $total_pages; ?>" 
                       value="<?php echo $page; ?>" style="width: 60px; padding: 5px; margin: 0 5px;">
                <button type="submit" class="btn btn-secondary" style="padding: 5px 10px;">Go</button>
            </form>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div style="text-align: center; margin: 30px 0;">
    <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    <button onclick="window.print()" class="btn">🖨️ Print Page</button>
    <button onclick="refreshPage()" class="btn btn-secondary">🔄 Refresh</button>
</div>

<script>
// Confirm status update to prevent accidental changes
function confirmStatusUpdate(form) {
    const orderId = form.querySelector('input[name="order_id"]').value;
    const newStatus = form.querySelector('select[name="status"]').value;
    const currentStatus = form.querySelector('select[name="status"]').selectedOptions[0].text;
    
    return confirm(`Are you sure you want to update Order #${orderId} status to: ${currentStatus}?`);
}

// Quick status update function
function quickUpdateStatus(orderId, newStatus) {
    if (confirm(`Are you sure you want to update Order #${orderId} to ${newStatus}?`)) {
        // Create a temporary form to submit the update
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        // Add order ID
        const orderIdInput = document.createElement('input');
        orderIdInput.type = 'hidden';
        orderIdInput.name = 'order_id';
        orderIdInput.value = orderId;
        form.appendChild(orderIdInput);
        
        // Add status
        const statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'status';
        statusInput.value = newStatus;
        form.appendChild(statusInput);
        
        // Add update_status trigger
        const updateInput = document.createElement('input');
        updateInput.type = 'hidden';
        updateInput.name = 'update_status';
        updateInput.value = '1';
        form.appendChild(updateInput);
        
        // Preserve current URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        ['filter', 'search', 'page'].forEach(param => {
            if (urlParams.has(param)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'current_' + param;
                input.value = urlParams.get(param);
                form.appendChild(input);
            }
        });
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Form loading states
document.querySelectorAll('form[method="POST"]').forEach(form => {
    form.addEventListener('submit', function() {
        const btn = this.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            const originalText = btn.textContent;
            btn.textContent = 'Updating...';
            
            // Re-enable after 5 seconds in case of issues
            setTimeout(() => {
                btn.disabled = false;
                btn.textContent = originalText;
            }, 5000);
        }
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + P for print
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        window.print();
    }
    
    // Ctrl/Cmd + F for search
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        document.getElementById('search').focus();
    }
    
    // F5 or Ctrl/Cmd + R for refresh
    if (e.key === 'F5' || ((e.ctrlKey || e.metaKey) && e.key === 'r')) {
        e.preventDefault();
        refreshPage();
    }
});

// Refresh page function
function refreshPage() {
    window.location.reload();
}

// Auto-refresh functionality (optional - can be enabled/disabled)
let autoRefreshEnabled = false;
let refreshInterval;

function toggleAutoRefresh() {
    if (autoRefreshEnabled) {
        clearInterval(refreshInterval);
        autoRefreshEnabled = false;
        document.getElementById('auto-refresh-btn').textContent = 'Enable Auto-Refresh';
    } else {
        refreshInterval = setInterval(() => {
            // Only refresh if no forms are focused (to prevent interrupting user input)
            if (!document.querySelector('input:focus, select:focus')) {
                window.location.reload();
            }
        }, 30000); // Refresh every 30 seconds
        autoRefreshEnabled = true;
        document.getElementById('auto-refresh-btn').textContent = 'Disable Auto-Refresh';
    }
}

// Add auto-refresh button on page load
document.addEventListener('DOMContentLoaded', function() {
    const buttonContainer = document.querySelector('div[style*="text-align: center; margin: 30px 0;"]');
    if (buttonContainer) {
        const autoRefreshBtn = document.createElement('button');
        autoRefreshBtn.id = 'auto-refresh-btn';
        autoRefreshBtn.className = 'btn btn-secondary';
        autoRefreshBtn.textContent = 'Enable Auto-Refresh';
        autoRefreshBtn.onclick = toggleAutoRefresh;
        autoRefreshBtn.style.marginLeft = '10px';
        buttonContainer.appendChild(autoRefreshBtn);
    }
});

// Status color coding for better visual feedback
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to order cards
    const orderCards = document.querySelectorAll('div[style*="border-left: 5px solid"]');
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
    
    // Add sound notification for new orders (if browser supports it)
    if ('Notification' in window && Notification.permission !== 'denied') {
        Notification.requestPermission();
    }
});

// Export functionality
function exportOrders() {
    const orders = <?php echo json_encode($orders); ?>;
    let csv = 'Order ID,Customer,Contact,Type,Table,Status,Total,Date,Items\n';
    
    orders.forEach(order => {
        const customer = order.username || 'Guest';
        const contact = order.contact_info || '';
        const table = order.table_number || '';
        const items = order.total_items || 0;
        const date = new Date(order.created_at).toLocaleString();
        
        csv += `"${order.id}","${customer}","${contact}","${order.order_type}","${table}","${order.status}","${order.total_amount}","${date}","${items}"\n`;
    });
    
    // Download CSV
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'orders_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Add export button
document.addEventListener('DOMContentLoaded', function() {
    const buttonContainer = document.querySelector('div[style*="text-align: center; margin: 30px 0;"]');
    if (buttonContainer && <?php echo count($orders); ?> > 0) {
        const exportBtn = document.createElement('button');
        exportBtn.className = 'btn btn-secondary';
        exportBtn.textContent = '📄 Export CSV';
        exportBtn.onclick = exportOrders;
        exportBtn.style.marginLeft = '10px';
        buttonContainer.appendChild(exportBtn);
    }
});

// Print specific order
function printOrder(orderId) {
    const order = <?php echo json_encode($orders); ?>.find(o => o.id == orderId);
    if (!order) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Order #${order.id}</title>
            <style>
                body { font-family: Arial, sans-serif; }
                .header { text-align: center; margin-bottom: 20px; }
                .order-info { margin-bottom: 15px; }
                .items { border-collapse: collapse; width: 100%; }
                .items th, .items td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .total { font-weight: bold; text-align: right; margin-top: 10px; }
                @media print { .no-print { display: none; } }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Order Receipt</h1>
                <h2>Order #${order.id}</h2>
            </div>
            <div class="order-info">
                <p><strong>Customer:</strong> ${order.username}</p>
                <p><strong>Date:</strong> ${new Date(order.created_at).toLocaleString()}</p>
                <p><strong>Type:</strong> ${order.order_type}${order.table_number ? ` - Table ${order.table_number}` : ''}</p>
                <p><strong>Status:</strong> ${order.status}</p>
            </div>
            <div class="total">
                <h3>Total: RM ${parseFloat(order.total_amount).toFixed(2)}</h3>
            </div>
            <div class="no-print">
                <button onclick="window.print()">Print</button>
                <button onclick="window.close()">Close</button>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
}

console.log('Orders management system loaded successfully');
</script>

<?php include '../includes/footer.php'; ?>