<?php
require_once '../config/database.php';

if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

if ($_POST && isset($_POST['order_type'])) {
    $_SESSION['order_type'] = $_POST['order_type'];
    redirect('menu.php');
}

include '../includes/header.php';
?>

<div style="text-align: center; padding: 50px 0;">
    <h1>Select Order Types</h1>
    <p style="margin: 20px 0; color: #666;">Choose how you'd like to receive your order</p>
    
    <form method="POST">
        <div class="order-types">
            <button type="submit" name="order_type" value="dine-in" class="order-type">
                🍽️<br>Dine-In
            </button>
            <button type="submit" name="order_type" value="takeaway" class="order-type">
                📱<br>Takeaway
            </button>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>