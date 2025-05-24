<?php
require_once '../config/database.php';

if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$message = '';

// Add to cart
if ($_POST && isset($_POST['add_to_cart'])) {
    $menu_item_id = $_POST['menu_item_id'];
    $quantity = (int)$_POST['quantity'];
    
    // Check if item already in cart
    $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND menu_item_id = ?");
    $stmt->execute([$_SESSION['user_id'], $menu_item_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update quantity
        $new_quantity = $existing['quantity'] + $quantity;
        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $stmt->execute([$new_quantity, $existing['id']]);
    } else {
        // Insert new cart item
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, menu_item_id, quantity) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $menu_item_id, $quantity]);
    }
    
    $message = 'Item added to cart successfully!';
}

// Get menu items
$stmt = $pdo->query("SELECT * FROM menu_items ORDER BY name");
$menu_items = $stmt->fetchAll();

// Get cart count
$stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$cart_count = $stmt->fetchColumn() ?: 0;

include '../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <h1>Menu Page</h1>
    <div>
        <a href="cart.php" class="btn">
            🛒 Cart (<?php echo $cart_count; ?>)
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<div class="menu-grid">
    <?php foreach ($menu_items as $item): ?>
        <div class="menu-item">
            <img src="https://via.placeholder.com/300x200?text=<?php echo urlencode($item['name']); ?>" 
                 alt="<?php echo htmlspecialchars($item['name']); ?>">
            <div class="menu-item-content">
                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                <p><?php echo htmlspecialchars($item['description']); ?></p>
                <div class="price">RM <?php echo number_format($item['price'], 2); ?></div>
                
                <form method="POST" style="display: flex; gap: 10px; align-items: center;">
                    <input type="hidden" name="menu_item_id" value="<?php echo $item['id']; ?>">
                    <input type="number" name="quantity" value="1" min="1" style="width: 60px; padding: 5px;">
                    <button type="submit" name="add_to_cart" class="btn">Add to Cart</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (empty($menu_items)): ?>
    <div style="text-align: center; padding: 50px;">
        <h3>No menu items available</h3>
        <p>Please check back later or contact the restaurant.</p>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>