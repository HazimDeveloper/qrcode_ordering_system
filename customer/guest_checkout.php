<?php
require_once '../config/database.php';

// Check if cart is empty
if (!isset($_SESSION['temp_cart']) || empty($_SESSION['temp_cart'])) {
    redirect('guest_menu.php');
}

$order_type = $_SESSION['order_type'] ?? 'takeaway';
$table_number = $_SESSION['qr_table_number'] ?? null;
$error = '';
$success = '';
$order_id = null;

// Calculate total
$total = 0;
foreach ($_SESSION['temp_cart'] as $item) {
    $total += $item['price'] * $item['quantity'];
}

// Process order
if ($_POST) {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    
    // For dine-in, get table number if not already set
    if ($order_type === 'dine-in' && !$table_number && isset($_POST['table_number'])) {
        $table_number = (int)$_POST['table_number'];
    }
    
    // Validate
    if (empty($customer_name) || empty($customer_phone)) {
        $error = 'Please provide your name and phone number';
    } elseif ($order_type === 'dine-in' && !$table_number) {
        $error = 'Table number is required for dine-in orders';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Create order (without user_id for guest)
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, order_type, table_number, total_amount, customer_name, customer_phone, status) VALUES (NULL, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$order_type, $table_number, $total, $customer_name, $customer_phone]);
            $order_id = $pdo->lastInsertId();
            
            // Add order items
            foreach ($_SESSION['temp_cart'] as $item) {
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$order_id, $item['id'], $item['quantity'], $item['price']]);
            }
            
            // Clear cart and session data
            $_SESSION['temp_cart'] = [];
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

include '../includes/header.php';
?>

<h1>Checkout</h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    
    <div style="background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px; margin: 30px 0;">
        <h2 style="text-align: center; margin-bottom: 20px;">Order Receipt</h2>
        
        <div style="border: 1px dashed #ccc; padding: 20px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <strong>Order #:</strong>
                <span><?php echo $order_id; ?></span>
            </div>
            
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <strong>Date:</strong>
                <span><?php echo date('M j, Y g:i A'); ?></span>
            </div>
            
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <strong>Customer:</strong>
                <span><?php echo htmlspecialchars($_POST['customer_name']); ?></span>
            </div>
            
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <strong>Phone:</strong>
                <span><?php echo htmlspecialchars($_POST['customer_phone']); ?></span>
            </div>
            
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <strong>Order Type:</strong>
                <span><?php echo ucfirst($order_type); ?></span>
            </div>
            
            <?php if ($table_number): ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <strong>Table Number:</strong>
                    <span><?php echo $table_number; ?></span>
                </div>
            <?php endif; ?>
            
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <strong>Total Amount:</strong>
                <span>RM <?php echo number_format($total, 2); ?></span>
            </div>
        </div>
        
        <p style="text-align: center; margin: 20px 0;">
            Please proceed to the counter for payment.<br>
            Show this receipt or provide your order number.
        </p>
        
        <div style="text-align: center;">
            <button onclick="window.print()" class="btn">Print Receipt</button>
        </div>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="../index.php" class="btn">Return to Home</a>
    </div>
<?php else: ?>
    <div style="background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 30px;">
        <h3>Order Summary</h3>
        
        <?php foreach ($_SESSION['temp_cart'] as $item): ?>
            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee;">
                <div>
                    <?php echo $item['quantity']; ?> × <?php echo htmlspecialchars($item['name']); ?>
                </div>
                <div>
                    RM <?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div style="display: flex; justify-content: space-between; padding: 15px 0; font-weight: bold; font-size: 18px;">
            <div>Total</div>
            <div>RM <?php echo number_format($total, 2); ?></div>
        </div>
    </div>
    
    <div style="background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px;">
        <h3>Customer Information</h3>
        
        <form method="POST">
            <div class="form-group">
                <label for="customer_name">Your Name:</label>
                <input type="text" id="customer_name" name="customer_name" required>
            </div>
            
            <div class="form-group">
                <label for="customer_phone">Phone Number:</label>
                <input type="text" id="customer_phone" name="customer_phone" required>
            </div>
            
            <?php if ($order_type === 'dine-in' && !$table_number): ?>
                <div class="form-group">
                    <label for="table_number">Table Number:</label>
                    <input type="number" id="table_number" name="table_number" min="1" required>
                </div>
            <?php endif; ?>
            
            <button type="submit" class="btn" style="width: 100%;">Place Order</button>
        </form>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>