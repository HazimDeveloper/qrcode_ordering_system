<?php
require_once '../config/database.php';

// Initialize temp cart if not exists
if (!isset($_SESSION['temp_cart'])) {
    $_SESSION['temp_cart'] = [];
}

$message = '';

// Remove item from cart
if (isset($_GET['remove']) && $_GET['remove']) {
    $menu_item_id = $_GET['remove'];
    if (isset($_SESSION['temp_cart'][$menu_item_id])) {
        unset($_SESSION['temp_cart'][$menu_item_id]);
        $message = 'Item removed from cart';
    }
}

// Update quantity
if ($_POST && isset($_POST['update_quantity'])) {
    $menu_item_id = $_POST['menu_item_id'];
    $quantity = (int)$_POST['quantity'];
    
    if ($quantity > 0 && isset($_SESSION['temp_cart'][$menu_item_id])) {
        $_SESSION['temp_cart'][$menu_item_id]['quantity'] = $quantity;
        $message = 'Quantity updated';
    }
}

// Calculate total
$total = 0;
foreach ($_SESSION['temp_cart'] as $item) {
    $total += $item['price'] * $item['quantity'];
}

include '../includes/header.php';
?>

<h1>Shopping Cart</h1>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<?php if (empty($_SESSION['temp_cart'])): ?>
    <div style="text-align: center; padding: 50px;">
        <h3>Your cart is empty</h3>
        <p>Add some delicious items from our menu!</p>
        <a href="guest_menu.php" class="btn">Browse Menu</a>
    </div>
<?php else: ?>
    <div style="background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <?php foreach ($_SESSION['temp_cart'] as $menu_item_id => $item): ?>
            <div class="cart-item" style="display: flex; justify-content: space-between; padding: 15px; border-bottom: 1px solid #eee;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <?php if (isset($item['image']) && $item['image']): ?>
                        <img src="../images/<?php echo urlencode($item['image']); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 5px;">
                    <?php endif; ?>
                    <div>
                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                        <p>RM <?php echo number_format($item['price'], 2); ?> each</p>
                    </div>
                </div>
                
                <div style="display: flex; align-items: center; gap: 15px;">
                    <form method="POST" style="display: flex; align-items: center; gap: 10px;">
                        <input type="hidden" name="menu_item_id" value="<?php echo $menu_item_id; ?>">
                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                               min="1" style="width: 60px; padding: 5px;">
                        <button type="submit" name="update_quantity" class="btn btn-secondary" style="padding: 5px 10px;">Update</button>
                    </form>
                    
                    <div style="text-align: right;">
                        <strong>RM <?php echo number_format($item['price'] * $item['quantity'], 2); ?></strong>
                    </div>
                    
                    <a href="?remove=<?php echo $menu_item_id; ?>" 
                       onclick="return confirm('Remove this item?')" 
                       style="color: #e74c3c; text-decoration: none;">❌</a>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="cart-total" style="padding: 20px; text-align: right;">
            <h3>Total: RM <?php echo number_format($total, 2); ?></h3>
        </div>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="guest_menu.php" class="btn btn-secondary">Continue Shopping</a>
        <a href="guest_checkout.php" class="btn">Proceed to Checkout</a>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>