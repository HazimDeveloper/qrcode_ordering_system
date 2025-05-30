<?php
require_once '../config/database.php';

if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// Get QR table number if available
$qr_table = $_SESSION['qr_table_number'] ?? null;

include '../includes/header.php';
?>

<div style="text-align: center; padding: 50px 0;">
    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
    
    <?php if ($qr_table): ?>
        <p style="margin: 20px 0; color: #666;">
            You are at Table <?php echo $qr_table; ?>
        </p>
    <?php endif; ?>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; max-width: 800px; margin: 40px auto;">
        <a href="select_order_type.php" class="option-card">
            <div class="option-icon">🍽️</div>
            <h3>Order Food</h3>
            <p>Browse our menu and place an order</p>
        </a>
        
        <a href="book_table_enhanced.php" class="option-card">
            <div class="option-icon">📅</div>
            <h3>Book Table & Events</h3>
            <p>Reserve a table or plan a special event</p>
        </a>
        
        <a href="order_history_enhanced.php" class="option-card">
            <div class="option-icon">📜</div>
            <h3>Order History</h3>
            <p>View your past orders by date</p>
        </a>
    </div>
</div>

<style>
.option-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 30px 20px;
    text-align: center;
    text-decoration: none;
    color: #333;
    transition: transform 0.2s, box-shadow 0.2s;
}

.option-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.15);
}

.option-icon {
    font-size: 48px;
    margin-bottom: 15px;
}
</style>

<?php include '../includes/footer.php'; ?>