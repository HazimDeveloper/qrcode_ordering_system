<?php
// ==============================================
// FILE: index.php (Updated with QR demo links)
// ==============================================
require_once 'config/database.php';
$page_title = 'QR Food Ordering Platform';
include 'includes/header.php';
?>

<div style="text-align: center; padding: 50px 0;">
    <h1>Welcome to QR Food Ordering Platform</h1>
    <p style="font-size: 18px; margin: 20px 0; color: #666;">
        Order your favorite food with ease using our QR-powered system
    </p>
    
    <?php if (isLoggedIn()): ?>
        <div style="margin: 30px 0;">
            <a href="customer/select_order_type.php" class="btn" style="font-size: 20px; padding: 15px 30px;">
                Start Ordering 🍕
            </a>
        </div>
        <div style="margin: 20px 0;">
            <a href="customer/menu.php" class="btn btn-secondary">View Menu</a>
            <a href="customer/book_table.php" class="btn btn-secondary">Book Table</a>
            <a href="qr/demo.php" class="btn btn-secondary">🔳 Try QR Demo</a>
        </div>
    <?php else: ?>
        <div style="margin: 30px 0;">
            <a href="auth/login.php" class="btn" style="font-size: 20px; padding: 15px 30px;">
                Login to Order
            </a>
        </div>
        <div style="margin: 20px 0;">
            <a href="qr/demo.php" class="btn btn-secondary">🔳 Try QR Demo</a>
        </div>
        <p style="margin: 20px 0;">
            Don't have an account? <a href="auth/register.php">Register here</a>
        </p>
    <?php endif; ?>
</div>

<div style="background: #f8f9fa; padding: 40px; border-radius: 8px; margin: 40px 0;">
    <h2 style="text-align: center; margin-bottom: 30px;">How It Works</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px;">
        <div style="text-align: center;">
            <div style="font-size: 48px; margin-bottom: 15px;">📱</div>
            <h3>1. Scan QR Code</h3>
            <p>Scan the QR code at your table to get started. Your table number is automatically detected.</p>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 48px; margin-bottom: 15px;">🍽️</div>
            <h3>2. Browse Menu</h3>
            <p>Browse our delicious menu and add items to your cart with prices in Malaysian Ringgit.</p>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 48px; margin-bottom: 15px;">🛒</div>
            <h3>3. Place Order</h3>
            <p>Review your order and confirm. Your table number is already set from the QR code.</p>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 48px; margin-bottom: 15px;">✅</div>
            <h3>4. Enjoy!</h3>
            <p>Sit back and enjoy your freshly prepared meal delivered to your table.</p>
        </div>
    </div>
</div>

<!-- QR Code Demo Section -->
<div style="background: #e8f4f8; padding: 30px; border-radius: 8px; margin: 40px 0; text-align: center;">
    <h2 style="margin-bottom: 20px;">🔳 Try Our QR System</h2>
    <p style="font-size: 16px; margin-bottom: 20px;">
        Experience our QR code ordering system right now! No need to visit the restaurant.
    </p>
    <a href="qr/demo.php" class="btn" style="font-size: 18px; padding: 12px 30px;">
        Test QR Code Demo
    </a>
    <p style="font-size: 14px; color: #666; margin-top: 15px;">
        See how customers will experience ordering from your tables
    </p>
</div>

<?php include 'includes/footer.php'; ?>