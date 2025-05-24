<?php
// ==============================================
// FILE: qr/scan.php - QR Code Scanner Landing
// ==============================================
require_once '../config/database.php';

$table_number = (int)($_GET['table'] ?? 0);
$error = '';
$success = '';

// Validate table number
if ($table_number <= 0 || $table_number > 999) {
    $error = 'Invalid QR code. Table number not found or invalid.';
}

// Check if user is logged in
$needs_login = !isLoggedIn();
$return_url = '';

if ($needs_login) {
    // Store the current URL for redirect after login
    $return_url = urlencode($_SERVER['REQUEST_URI']);
}

// If user is logged in and table number is valid, set session data
if (!$needs_login && !$error) {
    $_SESSION['qr_table_number'] = $table_number;
    $_SESSION['order_type'] = 'dine-in';
    
    // Log QR scan activity (optional)
    try {
        $stmt = $pdo->prepare("INSERT INTO qr_scans (table_number, user_id, scan_time, ip_address) VALUES (?, ?, NOW(), ?)");
        $stmt->execute([$table_number, $_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
    } catch (Exception $e) {
        // Continue if logging fails (table might not exist)
    }
    
    $success = true;
}

$page_title = $error ? 'QR Code Error' : "Table $table_number - QR Scan";
include '../includes/header.php';
?>

<div style="text-align: center; padding: 20px; min-height: 60vh; display: flex; align-items: center; justify-content: center;">
    <div style="max-width: 600px; width: 100%;">
        
        <?php if ($error): ?>
            <!-- Error State -->
            <div style="background: #fee; border: 2px solid #e74c3c; border-radius: 12px; padding: 40px;">
                <div style="font-size: 64px; margin-bottom: 20px;">❌</div>
                <h1 style="color: #e74c3c; margin-bottom: 20px;">QR Code Error</h1>
                <p style="font-size: 18px; color: #666; margin-bottom: 30px;">
                    <?php echo $error; ?>
                </p>
                
                <div style="margin: 30px 0;">
                    <a href="../index.php" class="btn">🏠 Go to Home</a>
                    <a href="demo.php" class="btn btn-secondary">🔳 Try QR Demo</a>
                </div>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: left;">
                    <h4>What you can do:</h4>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>Check if you scanned the correct QR code</li>
                        <li>Ask restaurant staff for assistance</li>
                        <li>Try scanning a different QR code</li>
                        <li>Browse our menu without a table number</li>
                    </ul>
                </div>
            </div>
            
        <?php elseif ($needs_login): ?>
            <!-- Login Required State -->
            <div style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 12px; padding: 40px;">
                <div style="font-size: 64px; margin-bottom: 20px;">🔐</div>
                <h1 style="color: #856404; margin-bottom: 20px;">Login Required</h1>
                <p style="font-size: 18px; color: #856404; margin-bottom: 20px;">
                    You need to login to order from <strong>Table <?php echo $table_number; ?></strong>
                </p>
                <p style="color: #666; margin-bottom: 30px;">
                    Don't worry! After logging in, you'll be automatically redirected back to this table.
                </p>
                
                <div style="margin: 30px 0;">
                    <a href="../auth/login.php?return=<?php echo $return_url; ?>" class="btn" style="font-size: 18px; padding: 15px 30px;">
                        🔑 Login to Order
                    </a>
                </div>
                
                <div style="margin: 20px 0;">
                    <p style="color: #666;">Don't have an account?</p>
                    <a href="../auth/register.php?return=<?php echo $return_url; ?>" class="btn btn-secondary">
                        📝 Register Now
                    </a>
                </div>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    <h4 style="color: #2c3e50;">Quick Registration:</h4>
                    <p style="font-size: 14px; color: #666; margin: 0;">
                        Registration takes less than 30 seconds. We only need your name, email, and a password.
                    </p>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Success State -->
            <div style="background: #e8f5e8; border: 2px solid #27ae60; border-radius: 12px; padding: 40px;">
                <div style="font-size: 64px; margin-bottom: 20px;">✅</div>
                <h1 style="color: #27ae60; margin-bottom: 20px;">QR Code Scanned Successfully!</h1>
                
                <!-- Table Information Card -->
                <div style="background: white; padding: 25px; border-radius: 12px; margin: 25px 0; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 15px; margin-bottom: 15px;">
                        <span style="font-size: 32px;">🍽️</span>
                        <div>
                            <h2 style="color: #2c3e50; margin: 0; font-size: 32px;">Table <?php echo $table_number; ?></h2>
                            <p style="color: #666; margin: 5px 0 0 0;">Dine-in Service</p>
                        </div>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <p style="margin: 0; color: #2c3e50; font-weight: bold;">
                            Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>! 👋
                        </p>
                        <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">
                            Your table number has been automatically set for your order.
                        </p>
                    </div>
                </div>
                
                <!-- Main Action Button -->
                <div style="margin: 30px 0;">
                    <a href="../customer/menu.php" class="btn" style="font-size: 20px; padding: 18px 40px; background: linear-gradient(135deg, #e67e22, #f39c12); border: none; box-shadow: 0 4px 15px rgba(230, 126, 34, 0.4);">
                        🍽️ View Menu & Start Ordering
                    </a>
                </div>
                
                <!-- Secondary Actions -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin: 25px 0;">
                    <a href="../customer/cart.php" class="btn btn-secondary">
                        🛒 View Cart
                        <?php 
                        $cart_count = 0;
                        $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $cart_count = $stmt->fetchColumn() ?: 0;
                        if ($cart_count > 0): ?>
                            <span style="background: #e74c3c; color: white; border-radius: 50%; padding: 2px 6px; font-size: 12px; margin-left: 5px;">
                                <?php echo $cart_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <a href="../customer/order_history.php" class="btn btn-secondary">📜 My Orders</a>
                    <a href="../customer/book_table.php" class="btn btn-secondary">📅 Reservations</a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Information Section -->
        <?php if (!$error): ?>
            <div style="background: #f8f9fa; padding: 25px; border-radius: 12px; margin: 30px 0; text-align: left;">
                <h3 style="color: #2c3e50; margin-bottom: 15px;">🔄 What happens next?</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div style="text-align: center;">
                        <div style="font-size: 32px; margin-bottom: 10px;">📋</div>
                        <h4 style="color: #34495e;">1. Browse Menu</h4>
                        <p style="font-size: 14px; color: #666;">Explore our delicious food options and daily specials</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 32px; margin-bottom: 10px;">🛒</div>
                        <h4 style="color: #34495e;">2. Add to Cart</h4>
                        <p style="font-size: 14px; color: #666;">Select items and quantities, customize your order</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 32px; margin-bottom: 10px;">✅</div>
                        <h4 style="color: #34495e;">3. Place Order</h4>
                        <p style="font-size: 14px; color: #666;">Review and confirm your order</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 32px; margin-bottom: 10px;">🍽️</div>
                        <h4 style="color: #34495e;">4. Enjoy!</h4>
                        <p style="font-size: 14px; color: #666;">Food will be served to Table <?php echo $table_number; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Help Section -->
        <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0;">
            <h4 style="color: #2c3e50; margin-bottom: 15px;">Need Help? 🤔</h4>
            <div style="text-align: left; font-size: 14px; color: #666;">
                <p><strong>Having trouble ordering?</strong></p>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>Ask your server for assistance</li>
                    <li>Call restaurant staff if needed</li>
                    <li>Check your internet connection</li>
                    <li>Try refreshing the page</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php if ($success): ?>
<script>
// Auto-redirect to menu after 5 seconds with countdown
let countdown = 5;
const countdownElement = document.createElement('div');
countdownElement.style.cssText = `
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: #3498db;
    color: white;
    padding: 15px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    z-index: 1000;
    font-weight: bold;
`;

function updateCountdown() {
    countdownElement.innerHTML = `
        <div>Auto-redirecting to menu in ${countdown}s</div>
        <button onclick="cancelRedirect()" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 5px 10px; border-radius: 4px; margin-top: 8px; cursor: pointer;">
            Cancel
        </button>
    `;
    
    if (countdown <= 0) {
        window.location.href = '../customer/menu.php';
    } else {
        countdown--;
        setTimeout(updateCountdown, 1000);
    }
}

function cancelRedirect() {
    countdown = -1;
    countdownElement.remove();
}

// Show countdown after 2 seconds
setTimeout(() => {
    document.body.appendChild(countdownElement);
    updateCountdown();
}, 2000);

// Add page visibility API to pause countdown when tab is not active
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        countdown = Math.max(countdown, 10); // Reset to at least 10 seconds when tab becomes visible again
    }
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>