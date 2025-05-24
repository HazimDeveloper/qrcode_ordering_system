<?php
// ==============================================
// FILE: qr/demo.php - QR Code Demo Page
// ==============================================
require_once '../config/database.php';

$page_title = 'QR Code Demo';
include '../includes/header.php';
?>

<div style="text-align: center; padding: 20px;">
    <h1>🔳 QR Code Demo</h1>
    <p style="font-size: 18px; margin: 20px 0; color: #666; max-width: 600px; margin-left: auto; margin-right: auto;">
        Experience our QR-powered ordering system! Try scanning these demo QR codes with your phone camera 
        or click the buttons to simulate the experience.
    </p>
    
    <!-- Demo QR Codes -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; margin: 40px 0; max-width: 1000px; margin-left: auto; margin-right: auto;">
        <?php 
        $demo_tables = [
            ['number' => 1, 'description' => 'Basic table demo'],
            ['number' => 5, 'description' => 'Mid-range table demo'],
            ['number' => 10, 'description' => 'High number table demo']
        ];
        $base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/qr-food-ordering';
        
        foreach ($demo_tables as $table): 
            $qr_url = $base_url . '/qr/scan.php?table=' . $table['number'];
            $qr_image_url = 'https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=' . urlencode($qr_url) . '&choe=UTF-8';
        ?>
            <div style="background: white; border: 2px solid #ddd; border-radius: 12px; padding: 25px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s;"
                 onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 20px rgba(0,0,0,0.15)'"
                 onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.1)'">
                
                <!-- Table Header -->
                <div style="border-bottom: 2px solid #e67e22; padding-bottom: 15px; margin-bottom: 20px;">
                    <h3 style="color: #e67e22; margin: 0; font-size: 24px;">Demo Table <?php echo $table['number']; ?></h3>
                    <p style="color: #666; margin: 5px 0 0 0; font-size: 14px;"><?php echo $table['description']; ?></p>
                </div>
                
                <!-- QR Code -->
                <div style="margin: 20px 0;">
                    <img src="<?php echo $qr_image_url; ?>" 
                         alt="Demo QR Code for Table <?php echo $table['number']; ?>" 
                         style="width: 200px; height: 200px; border: 2px solid #ecf0f1; border-radius: 8px;"
                         loading="lazy">
                </div>
                
                <!-- Instructions -->
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">
                    <p style="margin: 0 0 8px 0; font-weight: bold; color: #2c3e50;">
                        📱 Scan with Phone Camera
                    </p>
                    <p style="margin: 0; font-size: 12px; color: #666;">
                        Point your camera at the QR code above<br>
                        Most phones detect QR codes automatically
                    </p>
                </div>
                
                <!-- Action Buttons -->
                <div style="margin: 20px 0;">
                    <a href="<?php echo $qr_url; ?>" class="btn" 
                       style="display: block; margin: 10px 0; text-decoration: none; padding: 12px 20px;">
                        🔗 Simulate QR Scan
                    </a>
                    <button onclick="copyToClipboard('<?php echo $qr_url; ?>')" 
                            class="btn btn-secondary" style="width: 100%; padding: 8px;">
                        📋 Copy Link
                    </button>
                </div>
                
                <!-- Technical Info -->
                <div style="background: #ecf0f1; padding: 10px; border-radius: 5px; margin: 15px 0;">
                    <small style="color: #7f8c8d; font-family: monospace; word-break: break-all; display: block;">
                        <?php echo $qr_url; ?>
                    </small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- How to Test Section -->
    <div style="background: #e8f4f8; padding: 30px; border-radius: 12px; margin: 40px auto; max-width: 800px; text-align: left;">
        <h2 style="text-align: center; color: #2c3e50; margin-bottom: 25px;">🧪 How to Test the QR System</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px;">
            <!-- Mobile Testing -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="font-size: 32px; margin-bottom: 15px; text-align: center;">📱</div>
                <h3 style="color: #2c3e50; margin-bottom: 15px;">Mobile Testing</h3>
                <ol style="color: #666; font-size: 14px; padding-left: 20px;">
                    <li>Open your phone's camera app</li>
                    <li>Point it at any QR code above</li>
                    <li>Tap the notification that appears</li>
                    <li>Experience the ordering flow</li>
                </ol>
                <div style="background: #fff3cd; padding: 10px; border-radius: 5px; margin-top: 15px;">
                    <small style="color: #856404;">
                        <strong>Tip:</strong> Works on iOS 11+ and Android 8+ automatically
                    </small>
                </div>
            </div>
            
            <!-- Desktop Testing -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="font-size: 32px; margin-bottom: 15px; text-align: center;">💻</div>
                <h3 style="color: #2c3e50; margin-bottom: 15px;">Desktop Testing</h3>
                <ol style="color: #666; font-size: 14px; padding-left: 20px;">
                    <li>Click "Simulate QR Scan" button</li>
                    <li>This mimics scanning the QR code</li>
                    <li>You'll see the table detection</li>
                    <li>Experience the full ordering process</li>
                </ol>
                <div style="background: #d4edda; padding: 10px; border-radius: 5px; margin-top: 15px;">
                    <small style="color: #155724;">
                        <strong>Perfect for:</strong> Testing before going live
                    </small>
                </div>
            </div>
            
            <!-- QR Scanner Apps -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="font-size: 32px; margin-bottom: 15px; text-align: center;">📸</div>
                <h3 style="color: #2c3e50; margin-bottom: 15px;">QR Scanner Apps</h3>
                <ol style="color: #666; font-size: 14px; padding-left: 20px;">
                    <li>Download any QR scanner app</li>
                    <li>Open the app and scan codes above</li>
                    <li>Great for older phones</li>
                    <li>More reliable in low light</li>
                </ol>
                <div style="background: #cce7ff; padding: 10px; border-radius: 5px; margin-top: 15px;">
                    <small style="color: #004085;">
                        <strong>Recommended:</strong> QR Code Reader, QR Scanner
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Features Showcase -->
    <div style="background: #f8f9fa; padding: 30px; border-radius: 12px; margin: 40px auto; max-width: 800px;">
        <h2 style="color: #2c3e50; margin-bottom: 25px;">✨ QR System Features</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; text-align: left;">
            <div style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #3498db;">
                <h4 style="color: #3498db; margin-bottom: 10px;">🎯 Automatic Detection</h4>
                <p style="font-size: 14px; color: #666; margin: 0;">
                    Table number is automatically detected and set when customers scan QR codes.
                </p>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #27ae60;">
                <h4 style="color: #27ae60; margin-bottom: 10px;">📱 Mobile Optimized</h4>
                <p style="font-size: 14px; color: #666; margin: 0;">
                    Works perfectly on all mobile devices with camera QR code scanning.
                </p>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #e67e22;">
                <h4 style="color: #e67e22; margin-bottom: 10px;">🍽️ Seamless Ordering</h4>
                <p style="font-size: 14px; color: #666; margin: 0;">
                    Direct link from QR scan to menu with table number pre-filled.
                </p>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #9b59b6;">
                <h4 style="color: #9b59b6; margin-bottom: 10px;">⚙️ Staff Management</h4>
                <p style="font-size: 14px; color: #666; margin: 0;">
                    Staff can generate, print, and manage QR codes for all tables.
                </p>
            </div>
        </div>
    </div>
    
    <!-- Next Steps -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin: 40px auto; max-width: 600px;">
        <h2 style="margin-bottom: 20px;">🚀 Ready to Implement?</h2>
        <p style="margin-bottom: 25px; font-size: 16px; opacity: 0.9;">
            The demo shows you how the system works. Ready to set it up for your restaurant?
        </p>
        
        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
            <?php if (isLoggedIn() && isStaff()): ?>
                <a href="generate.php" class="btn" style="background: white; color: #667eea; text-decoration: none; font-weight: bold;">
                    🔧 Generate QR Codes
                </a>
                <a href="../staff/dashboard.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; text-decoration: none;">
                    📊 Staff Dashboard
                </a>
            <?php elseif (isLoggedIn()): ?>
                <a href="../customer/menu.php" class="btn" style="background: white; color: #667eea; text-decoration: none; font-weight: bold;">
                    🍽️ Browse Menu
                </a>
                <a href="../customer/order_history.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; text-decoration: none;">
                    📜 My Orders
                </a>
            <?php else: ?>
                <a href="../auth/login.php" class="btn" style="background: white; color: #667eea; text-decoration: none; font-weight: bold;">
                    🔑 Login to Try
                </a>
                <a href="../auth/register.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; text-decoration: none;">
                    📝 Register Free
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Back Navigation -->
    <div style="margin: 30px 0;">
        <a href="../index.php" class="btn btn-secondary">← Back to Home</a>
        <?php if (isStaff()): ?>
            <a href="generate.php" class="btn">🔧 Generate Real QR Codes</a>
        <?php endif; ?>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show temporary success message
        const notification = document.createElement('div');
        notification.textContent = '✅ Link copied to clipboard!';
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #27ae60;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 1000;
            font-weight: bold;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }).catch(function(err) {
        alert('Could not copy link: ' + err);
    });
}

// Add analytics tracking for demo usage (optional)
document.addEventListener('DOMContentLoaded', function() {
    // Track demo page views
    console.log('QR Demo page loaded');
    
    // Add click tracking for demo links
    document.querySelectorAll('a[href*="/qr/scan.php"]').forEach(link => {
        link.addEventListener('click', function() {
            console.log('Demo QR link clicked:', this.href);
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>