<?php
require_once '../config/database.php';

// Initialize temp cart if not exists
if (!isset($_SESSION['temp_cart'])) {
    $_SESSION['temp_cart'] = [];
}

$message = '';

// Add to cart
if ($_POST && isset($_POST['add_to_cart'])) {
    $menu_item_id = $_POST['menu_item_id'];
    $quantity = (int)$_POST['quantity'];
    
    // Get menu item details
    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ?");
    $stmt->execute([$menu_item_id]);
    $item = $stmt->fetch();
    
    if ($item) {
        // Add to temporary cart
        if (isset($_SESSION['temp_cart'][$menu_item_id])) {
            $_SESSION['temp_cart'][$menu_item_id]['quantity'] += $quantity;
        } else {
            $_SESSION['temp_cart'][$menu_item_id] = [
                'id' => $menu_item_id,
                'name' => $item['name'],
                'price' => $item['price'],
                'quantity' => $quantity,
                'image' => $item['image']
            ];
        }
        $message = 'Item added to cart successfully!';
    }
}

// Get menu items
$stmt = $pdo->query("SELECT * FROM menu_items ORDER BY name");
$menu_items = $stmt->fetchAll();

// Get cart count
$cart_count = 0;
foreach ($_SESSION['temp_cart'] as $item) {
    $cart_count += $item['quantity'];
}

include '../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <h1>Menu Page</h1>
    <div>
        <a href="guest_cart.php" class="btn">
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
            <img src="../images/<?php echo urlencode($item['image']); ?>" 
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

<?php include '../includes/footer.php'; ?>