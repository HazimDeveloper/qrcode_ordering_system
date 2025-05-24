<?php
require_once '../config/database.php';

if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// Get user's orders
$stmt = $pdo->prepare("
    SELECT o.*, 
           COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();

include '../includes/header.php';
?>

<h1>Order History</h1>

<?php if (empty($orders)): ?>
    <div style="text-align: center; padding: 50px;">
        <h3>No orders yet</h3>
        <p>Start ordering to see your order history here!</p>
        <a href="menu.php" class="btn">Browse Menu</a>
    </div>
<?php else: ?>
    <div style="display: grid; gap: 20px;">
        <?php foreach ($orders as $order): ?>
            <div style="background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                    <div>
                        <h3>Order #<?php echo $order['id']; ?></h3>
                        <p style="color: #666; margin: 5px 0;">
                            <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
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
                    $stmt = $pdo->prepare("
                        SELECT oi.quantity, oi.price, m.name
                        FROM order_items oi
                        JOIN menu_items m ON oi.menu_item_id = m.id
                        WHERE oi.order_id = ?
                    ");
                    $stmt->execute([$order['id']]);
                    $order_items = $stmt->fetchAll();
                    ?>
                    
                    <h4 style="margin-bottom: 10px;">Items (<?php echo $order['item_count']; ?>):</h4>
                    <?php foreach ($order_items as $item): ?>
                        <div style="display: flex; justify-content: space-between; padding: 5px 0;">
                            <span><?php echo $item['quantity']; ?>× <?php echo htmlspecialchars($item['name']); ?></span>
                            <span>RM <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>