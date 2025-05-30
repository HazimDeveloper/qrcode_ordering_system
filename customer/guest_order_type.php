<?php
require_once '../config/database.php';

// No login check - this is for non-logged in users

if ($_POST && isset($_POST['order_type'])) {
    $_SESSION['order_type'] = $_POST['order_type'];
    
    // If dine-in but no table number, redirect to table input page
    if ($_POST['order_type'] === 'dine-in' && !isset($_SESSION['qr_table_number'])) {
        redirect('guest_table_input.php');
    } else {
        redirect('guest_menu.php');
    }
}

include '../includes/header.php';
?>

<div style="text-align: center; padding: 50px 0;">
    <h1>Select Order Type</h1>
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