<?php
// ==============================================
// FILE: customer/place_order.php (Updated with QR table integration)
// ==============================================
require_once '../config/database.php';

if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// Get cart items
$stmt = $pdo->prepare("
    SELECT c.id, c.quantity, c.menu_item_id, m.name, m.price, (c.quantity * m.price) as subtotal
    FROM cart c 
    JOIN menu_items m ON c.menu_item_id = m.id 
    WHERE c.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll();

if (empty($cart_items)) {
    redirect('cart.php');
}

$total = array_sum(array_column($cart_items, 'subtotal'));
$error = '';
$success = '';

// Get QR table number if available
$qr_table = $_SESSION['qr_table_number'] ?? null;
$qr_order_type = $_SESSION['order_type'] ?? null;

// Process order
if ($_POST) {
    $order_type = $_POST['order_type'] ?? $qr_order_type ?? 'takeaway';
    $table_number = null;
    
    // Use QR table number if available and order type is dine-in
    if ($order_type === 'dine-in') {
        if ($qr_table) {
            $table_number = $qr_table;
        } elseif (!empty($_POST['table_number'])) {
            $table_number = (int)$_POST['table_number'];
        }
        
        if (!$table_number) {
            $error = 'Table number is required for dine-in orders';
        }
    }
    
    if (!$error) {
        try {
            $pdo->beginTransaction();
            
            // Create order
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, order_type, table_number, total_amount) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $order_type, $table_number, $total]);
            $order_id = $pdo->lastInsertId();
            
            // Add order items
            foreach ($cart_items as $item) {
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$order_id, $item['menu_item_id'], $item['quantity'], $item['price']]);
            }
            
            // Clear cart
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            // Clear QR session data
            unset($_SESSION['qr_table_number'], $_SESSION['order_type']);
            
            $pdo->commit();
            
            $success = "Order placed successfully! Your order number is #$order_id";
            if ($table_number) {
                $success .= " for Table $table_number";
            }
            
        } catch (Exception $e) {
            $pdo->rollback();
            $error = 'Failed to place order. Please try again.';
        }
    }
}

$page_title = 'Place Order';
include '../includes/header.php';
?>

<h1>Place Order</h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <div style="text-align: center; margin: 30px 0;">
        <a href="order_history.php" class="btn">View Order History</a>
        <a href="menu.php" class="btn btn-secondary">Order Again</a>
    </div>
<?php else: ?>
    <!-- QR Code Information -->
    <?php if ($qr_table): ?>
        <div style="background: #e8f5e8; border: 2px solid #27ae60; border-radius: 8px; padding: 20px; margin: 20px 0;">
            <h3 style="color: #27ae60; margin-bottom: 10px;">🔳 QR Code Order Detected</h3>
            <p><strong>Table Number:</strong> <?php echo $qr_table; ?></p>
            <p><strong>Order Type:</strong> Dine-in (automatically set)</p>
            <p style="color: #666; font-size: 14px;">Your table number was detected from the QR code you scanned.</p>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 20px 0;">
        <!-- Order Summary -->
        <div>
            <h3>Order Summary</h3>
            <div style="background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px;">
                <?php foreach ($cart_items as $item): ?>
                    <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee;">
                        <div>
                            <strong><?php echo htmlspecialchars($item['name']); ?></strong><br>
                            <small>Qty: <?php echo $item['quantity']; ?> × RM <?php echo number_format($item['price'], 2); ?></small>
                        </div>
                        <div>RM <?php echo number_format($item['subtotal'], 2); ?></div>
                    </div>
                <?php endforeach; ?>
                
                <div style="padding: 15px 0; font-size: 18px; font-weight: bold;">
                    Total: RM <?php echo number_format($total, 2); ?>
                </div>
            </div>
        </div>
        
        <!-- Order Details -->
        <div>
            <h3>Order Details</h3>
            <form method="POST" style="background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px;">
                <div class="form-group">
                    <label for="order_type">Order Type:</label>
                    <select name="order_type" id="order_type" required onchange="toggleTableNumber()" <?php echo $qr_table ? 'disabled' : ''; ?>>
                        <option value="takeaway" <?php echo ($qr_order_type ?? '') === 'takeaway' ? 'selected' : ''; ?>>Takeaway</option>
                        <option value="dine-in" <?php echo ($qr_order_type ?? '') === 'dine-in' || $qr_table ? 'selected' : ''; ?>>Dine-In</option>
                    </select>
                    <?php if ($qr_table): ?>
                        <input type="hidden" name="order_type" value="dine-in">
                        <small style="color: #27ae60;">Order type locked by QR code</small>
                    <?php endif; ?>
                </div>
                
                <div class="form-group" id="table-number-group" style="<?php echo (!$qr_table && ($qr_order_type ?? '') !== 'dine-in') ? 'display: none;' : ''; ?>">
                    <label for="table_number">Table Number:</label>
                    <?php if ($qr_table): ?>
                        <input type="text" value="Table <?php echo $qr_table; ?>" disabled style="background: #f0f0f0;">
                        <input type="hidden" name="table_number" value="<?php echo $qr_table; ?>">
                        <small style="color: #27ae60;">Table number set by QR code</small>
                    <?php else: ?>
                        <input type="number" name="table_number" id="table_number" min="1" max="50">
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn" style="width: 100%;">Confirm Order</button>
            </form>
        </div>
    </div>
    
    <div style="text-align: center; margin: 20px 0;">
        <a href="cart.php" class="btn btn-secondary">Back to Cart</a>
    </div>
<?php endif; ?>

<script>
function toggleTableNumber() {
    const orderType = document.getElementById('order_type').value;
    const tableGroup = document.getElementById('table-number-group');
    const tableInput = document.getElementById('table_number');
    
    if (orderType === 'dine-in') {
        tableGroup.style.display = 'block';
        if (tableInput) tableInput.required = true;
    } else {
        tableGroup.style.display = 'none';
        if (tableInput) tableInput.required = false;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', toggleTableNumber);
</script>

<?php include '../includes/footer.php'; ?>