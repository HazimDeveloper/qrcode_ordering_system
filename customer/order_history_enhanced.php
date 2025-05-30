<?php
require_once '../config/database.php';

if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// Get dates with orders
$stmt = $pdo->prepare("SELECT DATE(created_at) as order_date, COUNT(*) as order_count FROM orders WHERE user_id = ? GROUP BY DATE(created_at) ORDER BY order_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$order_dates = $stmt->fetchAll();

// Get selected date or default to most recent
$selected_date = isset($_GET['date']) ? $_GET['date'] : ($order_dates[0]['order_date'] ?? null);

// Get orders for selected date
$orders = [];
if ($selected_date) {
    $stmt = $pdo->prepare("SELECT o.*, COUNT(oi.id) as item_count FROM orders o LEFT JOIN order_items oi ON o.id = oi.order_id WHERE o.user_id = ? AND DATE(o.created_at) = ? GROUP BY o.id ORDER BY o.created_at DESC");
    $stmt->execute([$_SESSION['user_id'], $selected_date]);
    $orders = $stmt->fetchAll();
}

include '../includes/header.php';
?>

<h1>Order History</h1>

<?php if (empty($order_dates)): ?>
    <div style="text-align: center; padding: 50px;">
        <h3>No orders yet</h3>
        <p>Start ordering to see your order history here!</p>
        <a href="menu.php" class="btn">Browse Menu</a>
    </div>
<?php else: ?>
    <div style="display: flex; gap: 20px; margin-bottom: 30px;">
        <div style="flex: 0 0 250px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px;">
            <h3 style="margin-bottom: 15px;">Order Dates</h3>
            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php foreach ($order_dates as $date): ?>
                    <li style="margin-bottom: 10px;">
                        <a href="?date=<?php echo $date['order_date']; ?>" 
                           style="display: flex; justify-content: space-between; padding: 10px; border-radius: 5px; text-decoration: none; color: #333; <?php echo ($selected_date === $date['order_date']) ? 'background: #e8f4f8; font-weight: bold;' : ''; ?>">
                            <span><?php echo date('M j, Y', strtotime($date['order_date'])); ?></span>
                            <span style="background: #f0f0f0; padding: 2px 8px; border-radius: 10px; font-size: 12px;">
                                <?php echo $date['order_count']; ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <div style="flex: 1;">
            <h3 style="margin-bottom: 15px;">
                Orders for <?php echo date('F j, Y', strtotime($selected_date)); ?>
            </h3>
            
            <div style="display: grid; gap: 20px;">
                <?php foreach ($orders as $order): ?>
                    <div style="background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                            <div>
                                <h3>Order #<?php echo $order['id']; ?></h3>
                                <p style="color: #666; margin: 5px 0;">
                                    <?php echo date('g:i A', strtotime($order['created_at'])); ?>
                                </p>
                                <p style="margin: 5px 0;">
                                    <span style="background: #e8f4f8; padding: 3px 8px; border-radius: 3px; font-size: 12px;">
                                        <?php echo ucfirst($order['order_type']); ?>
                                    </span>
                                    <?php if ($order['table_number']): ?>
                                        <span style="background: #f0f0f0; padding: 3px 8px; border-radius: 3px; font-size: 12px; margin-left: 5px;">
                                            Table <?php echo $order['table_number']; ?>
                                        </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <div style="text-align: right;">
                                <div style="font-size: 18px; font-weight: bold; color: #e67e22;">
                                    RM <?php echo number_format($order['total_amount'], 2); ?>
                                </div>
                                <div style="margin-top: 5px;">
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
                                    <span style="background: <?php echo $color; ?>; color: white; padding: 5px 10px; border-radius: 5px; font-size: 12px;">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div style="border-top: 1px solid #eee; padding-top: 15px;">
                            <?php
                            // Get order items
                            $stmt = $pdo->prepare("SELECT oi.quantity, oi.price, m.name, m.image FROM order_items oi JOIN menu_items m ON oi.menu_item_id = m.id WHERE oi.order_id = ?");
                            $stmt->execute([$order['id']]);
                            $order_items = $stmt->fetchAll();
                            ?>
                            
                            <h4 style="margin-bottom: 10px;">Items (<?php echo $order['item_count']; ?>):</h4>
                            
                            <div style="display: grid; gap: 10px;">
                                <?php foreach ($order_items as $item): ?>
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <?php if ($item['image']): ?>
                                            <img src="../images/<?php echo urlencode($item['image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                        <?php endif; ?>
                                        <div style="flex: 1;">
                                            <div><?php echo $item['quantity']; ?>× <?php echo htmlspecialchars($item['name']); ?></div>
                                        </div>
                                        <div>
                                            RM <?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px; text-align: right;">
                            <a href="#" class="btn btn-secondary" onclick="window.print(); return false;">Print Receipt</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>