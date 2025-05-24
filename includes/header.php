<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - QR Food Ordering' : 'QR Food Ordering Platform'; ?></title>
    <link rel="stylesheet" href="/qr-food-ordering/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🍽️</text></svg>">
    
    <!-- Meta tags for mobile optimization -->
    <meta name="description" content="QR-powered food ordering system for restaurants. Scan QR codes to order food instantly.">
    <meta name="keywords" content="QR code, food ordering, restaurant, menu, online ordering">
    
    <!-- PWA support -->
    <meta name="theme-color" content="#e67e22">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="QR Food Ordering">
</head>
<body>
    <header class="header">
        <nav class="nav">
            <!-- Logo/Brand -->
            <div class="logo">
                <a href="/qr-food-ordering/index.php" style="color: white; text-decoration: none;">
                    <h2>🍽️ QR Food Ordering</h2>
                </a>
            </div>
            
            <!-- Mobile Menu Toggle -->
            <div class="mobile-menu-toggle" onclick="toggleMobileMenu()" style="display: none; cursor: pointer; font-size: 24px;">
                ☰
            </div>
            
            <!-- Navigation Links -->
            <ul class="nav-links" id="navLinks">
                <li><a href="/qr-food-ordering/index.php">🏠 Home</a></li>
                
                <?php if (isLoggedIn()): ?>
                    <!-- Customer Links -->
                    <li><a href="/qr-food-ordering/customer/menu.php">📋 Menu</a></li>
                    
                    <!-- Cart with count -->
                    <?php
                    $cart_count = 0;
                    if (isset($_SESSION['user_id'])) {
                        $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $cart_count = $stmt->fetchColumn() ?: 0;
                    }
                    ?>
                    <li>
                        <a href="/qr-food-ordering/customer/cart.php">
                            🛒 Cart
                            <?php if ($cart_count > 0): ?>
                                <span style="background: #e74c3c; color: white; border-radius: 50%; padding: 2px 6px; font-size: 12px; margin-left: 5px;">
                                    <?php echo $cart_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <li><a href="/qr-food-ordering/customer/order_history.php">📜 Orders</a></li>
                    <li><a href="/qr-food-ordering/customer/book_table.php">📅 Book Table</a></li>
                    
                    <!-- Staff-only Links -->
                    <?php if (isStaff()): ?>
                        <li class="dropdown">
                            <a href="#" class="dropbtn">⚙️ Staff</a>
                            <div class="dropdown-content">
                                <a href="/qr-food-ordering/staff/dashboard.php">📊 Dashboard</a>
                                <a href="/qr-food-ordering/staff/manage_orders.php">📋 Manage Orders</a>
                                <a href="/qr-food-ordering/staff/manage_items.php">🍽️ Manage Items</a>
                                <a href="/qr-food-ordering/qr/generate.php">🔳 QR Codes</a>
                            </div>
                        </li>
                    <?php endif; ?>
                    
                    <!-- User Menu -->
                    <li class="dropdown">
                        <a href="#" class="dropbtn">👤 <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></a>
                        <div class="dropdown-content">
                            <div style="padding: 10px; border-bottom: 1px solid #eee; background: #f8f9fa;">
                                <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></strong><br>
                                <small><?php echo isStaff() ? 'Staff Member' : 'Customer'; ?></small>
                            </div>
                            <a href="/qr-food-ordering/customer/order_history.php">📜 My Orders</a>
                            <a href="/qr-food-ordering/customer/book_table.php">📅 My Reservations</a>
                            <?php if (isStaff()): ?>
                                <a href="/qr-food-ordering/staff/dashboard.php">⚙️ Staff Dashboard</a>
                            <?php endif; ?>
                            <a href="/qr-food-ordering/auth/logout.php" style="color: #e74c3c;">🚪 Logout</a>
                        </div>
                    </li>
                    
                <?php else: ?>
                    <!-- Guest Links -->
                    <li><a href="/qr-food-ordering/customer/menu.php">📋 Menu</a></li>
                    <li><a href="/qr-food-ordering/qr/demo.php">🔳 QR Demo</a></li>
                    <li><a href="/qr-food-ordering/auth/login.php">🔑 Login</a></li>
                    <li><a href="/qr-food-ordering/auth/register.php">📝 Register</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    
    <!-- QR Table Number Display Banner -->
    <?php if (isset($_SESSION['qr_table_number']) && $_SESSION['qr_table_number'] > 0): ?>
        <div class="qr-banner" style="background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; padding: 12px; text-align: center; font-weight: bold; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: center; align-items: center; gap: 15px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 20px;">🔳</span>
                    <span>Table <?php echo $_SESSION['qr_table_number']; ?> - Dine-in Order</span>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button onclick="viewTableInfo()" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); padding: 5px 12px; border-radius: 15px; cursor: pointer; font-size: 12px;">
                        ℹ️ Table Info
                    </button>
                    <button onclick="clearTableNumber()" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); padding: 5px 12px; border-radius: 15px; cursor: pointer; font-size: 12px;">
                        ❌ Clear Table
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Notification/Alert Area -->
    <div id="notification-area" style="display: none;"></div>
    
    <!-- Main Content Container -->
    <main class="container">
        
        <!-- Breadcrumb Navigation -->
        <?php if (isset($show_breadcrumb) && $show_breadcrumb): ?>
            <nav class="breadcrumb" style="margin: 20px 0; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                <?php
                $path = trim($_SERVER['REQUEST_URI'], '/');
                $path_parts = explode('/', $path);
                $breadcrumb_path = '';
                
                echo '<a href="/qr-food-ordering/">Home</a>';
                
                foreach ($path_parts as $part) {
                    if (!empty($part) && $part !== 'qr-food-ordering') {
                        $breadcrumb_path .= '/' . $part;
                        $display_name = ucfirst(str_replace(['-', '_', '.php'], [' ', ' ', ''], $part));
                        echo ' › <a href="' . $breadcrumb_path . '">' . $display_name . '</a>';
                    }
                }
                ?>
            </nav>
        <?php endif; ?>

<style>
/* Dropdown Menu Styles */
.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    background-color: white;
    min-width: 200px;
    box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
    border-radius: 8px;
    z-index: 1000;
    border: 1px solid #ddd;
    overflow: hidden;
}

.dropdown-content a {
    color: #333 !important;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
    transition: background-color 0.3s;
}

.dropdown-content a:hover {
    background-color: #f1f1f1;
}

.dropdown:hover .dropdown-content {
    display: block;
}

.dropbtn:after {
    content: ' ▼';
    font-size: 10px;
    margin-left: 5px;
}

/* Mobile Responsive Navigation */
@media screen and (max-width: 768px) {
    .mobile-menu-toggle {
        display: block !important;
        color: white;
    }
    
    .nav {
        flex-wrap: wrap;
    }
    
    .nav-links {
        display: none;
        width: 100%;
        flex-direction: column;
        background: rgba(0,0,0,0.1);
        border-radius: 8px;
        margin-top: 10px;
        padding: 10px;
    }
    
    .nav-links.show {
        display: flex;
    }
    
    .nav-links li {
        margin: 5px 0;
    }
    
    .dropdown-content {
        position: static;
        display: block;
        box-shadow: none;
        background: rgba(255,255,255,0.1);
        margin-top: 5px;
        border-radius: 5px;
    }
    
    .dropdown-content a {
        color: white !important;
        padding: 8px 16px;
    }
    
    .qr-banner > div {
        flex-direction: column;
        gap: 10px;
    }
}

/* Cart count animation */
@keyframes bounce {
    0%, 20%, 60%, 100% { transform: translateY(0); }
    40% { transform: translateY(-10px); }
    80% { transform: translateY(-5px); }
}

.nav-links a span {
    animation: bounce 2s infinite;
}

/* Notification styles */
.notification {
    padding: 12px 20px;
    margin: 10px 0;
    border-radius: 5px;
    font-weight: bold;
    animation: slideDown 0.3s ease-out;
}

.notification.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.notification.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.notification.info {
    background: #cce7ff;
    color: #004085;
    border: 1px solid #99d6ff;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<script>
// Mobile menu toggle
function toggleMobileMenu() {
    const navLinks = document.getElementById('navLinks');
    navLinks.classList.toggle('show');
}

// QR Table Management Functions
function clearTableNumber() {
    if (confirm('Clear table number? You can scan the QR code again if needed.')) {
        fetch('/qr-food-ordering/qr/clear_table.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Table number cleared successfully!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Failed to clear table number', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred', 'error');
        });
    }
}

function viewTableInfo() {
    const tableNumber = <?php echo isset($_SESSION['qr_table_number']) ? $_SESSION['qr_table_number'] : 'null'; ?>;
    if (tableNumber) {
        alert(`Table Information:\n\nTable Number: ${tableNumber}\nOrder Type: Dine-in\nStatus: Active\n\nYour orders will be delivered to this table automatically.`);
    }
}

// Notification system
function showNotification(message, type = 'info') {
    const notificationArea = document.getElementById('notification-area');
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    notificationArea.appendChild(notification);
    notificationArea.style.display = 'block';
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        notification.remove();
        if (notificationArea.children.length === 0) {
            notificationArea.style.display = 'none';
        }
    }, 5000);
}

// Auto-hide mobile menu when clicking outside
document.addEventListener('click', function(event) {
    const nav = document.querySelector('.nav');
    const navLinks = document.getElementById('navLinks');
    const toggleButton = document.querySelector('.mobile-menu-toggle');
    
    if (!nav.contains(event.target) && navLinks.classList.contains('show')) {
        navLinks.classList.remove('show');
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Alt + M for menu
    if (e.altKey && e.key === 'm') {
        e.preventDefault();
        window.location.href = '/qr-food-ordering/customer/menu.php';
    }
    
    // Alt + C for cart
    if (e.altKey && e.key === 'c') {
        e.preventDefault();
        window.location.href = '/qr-food-ordering/customer/cart.php';
    }
    
    // Alt + H for home
    if (e.altKey && e.key === 'h') {
        e.preventDefault();
        window.location.href = '/qr-food-ordering/index.php';
    }
    
    // Escape to close mobile menu
    if (e.key === 'Escape') {
        document.getElementById('navLinks').classList.remove('show');
    }
});

// Update cart count dynamically (if on cart-related pages)
function updateCartCount() {
    fetch('/qr-food-ordering/api/cart_count.php')
        .then(response => response.json())
        .then(data => {
            const cartBadge = document.querySelector('.nav-links a[href*="cart"] span');
            if (cartBadge && data.count !== undefined) {
                if (data.count > 0) {
                    cartBadge.textContent = data.count;
                    cartBadge.style.display = 'inline';
                } else {
                    cartBadge.style.display = 'none';
                }
            }
        })
        .catch(error => console.log('Cart count update failed:', error));
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Update cart count every 30 seconds if user is logged in
    <?php if (isLoggedIn()): ?>
    setInterval(updateCartCount, 30000);
    <?php endif; ?>
    
    // Add smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});

// Service Worker registration for PWA (optional)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/qr-food-ordering/sw.js')
            .then(function(registration) {
                console.log('SW registered: ', registration);
            }, function(registrationError) {
                console.log('SW registration failed: ', registrationError);
            });
    });
}
</script>