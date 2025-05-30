<?php
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
            <a href="customer/account_options.php" class="btn" style="font-size: 20px; padding: 15px 30px;">
                My Account 👤
            </a>
        </div>
        <div style="margin: 20px 0;">
            <a href="customer/menu.php" class="btn btn-secondary">Order Food</a>
            <a href="customer/book_table_enhanced.php" class="btn btn-secondary">Book Table</a>
        </div>
    <?php else: ?>
        <div style="margin: 30px 0;">
            <a href="qr/scan.php" class="btn" style="font-size: 20px; padding: 15px 30px; margin-right: 10px;">
                Scan QR & Order
            </a>
            <a href="auth/login.php" class="btn" style="font-size: 20px; padding: 15px 30px;">
                Login / Register
            </a>
        </div>
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

<?php include 'includes/footer.php'; ?>