<?php
require_once '../config/database.php';

if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$message = '';

// Remove item from cart
if ($_GET['remove']) {
    $cart_id = $_GET['remove'];
    $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
    $stmt->execute([$cart_id, $_SESSION['user_id']]);
    $message = 'Item removed from cart';
}

// Update quantity
if ($_POST && isset($_POST['update_quantity'])) {
    $cart_id = $_POST['cart_id'];
    $quantity = (int)$_POST['quantity'];
    
    if ($quantity > 0) {
        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$quantity, $cart_id, $_SESSION['user_id']]);
        $message = 'Quantity updated';
    }
}

// Get cart items
$stmt = $pdo->prepare("
    SELECT c.id, c.quantity, m.name, m.price, (c.quantity * m.price) as subtotal
    FROM cart c 
    JOIN menu_items m ON c.menu_item_id = m.id 
    WHERE c.user_id = ?
    ORDER BY m.name
");
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll();

$total = array_sum(array_column($cart_items, 'subtotal'));

include '../includes/header.php';
?>

<h1>Shopping Cart</h1>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<?php if (empty($cart_items)): ?>
    <div style="text-align: center; padding: 50px;">
        <h3>Your cart is empty</h3>
        <p>Add some delicious items from our menu!</p>
        <a href="menu.php" class="btn">Browse Menu</a>
    </div>
<?php else: ?>
    <div style="background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <?php foreach ($cart_items as $item): ?>
            <div class="cart-item">
                <div>
                    <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                    <p>RM <?php echo number_format($item['price'], 2); ?> each</p>
                </div>
                
                <div style="display: flex; align-items: center; gap: 15px;">
                    <form method="POST" style="display: flex; align-items: center; gap: 10px;">
                        <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                               min="1" style="width: 60px; padding: 5px;">
                        <button type="submit" name="update_quantity" class="btn btn-secondary" style="padding: 5px 10px;">Update</button>
                    </form>
                    
                    <div style="text-align: right;">
                        <strong>RM <?php echo number_format($item['subtotal'], 2); ?></strong>
                    </div>
                    
                    <a href="?remove=<?php echo $item['id']; ?>" 
                       onclick="return confirm('Remove this item?')" 
                       style="color: #e74c3c; text-decoration: none;">❌</a>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="cart-total">
            <h3>Total: RM <?php echo number_format($total, 2); ?></h3>
        </div>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="menu.php" class="btn btn-secondary">Continue Shopping</a>
        <a href="place_order.php" class="btn">Proceed to Order</a>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>