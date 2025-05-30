<?php
// ==============================================
// FILE: staff/generate.php - QR Code Generator for Staff
// ==============================================
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';
$generated_qr = null;

// Generate single QR code
if ($_POST && isset($_POST['generate_qr'])) {
    $restaurant_name = trim($_POST['restaurant_name']) ?: 'QR Food Ordering';
    $base_url = 'https://qr-code-online.infinityfreeapp.com';
    
    try {
        $pdo->beginTransaction();
        
        // Generate QR URL - direct to website
        $qr_url = $base_url;
        
        // Check if a global QR already exists
        $stmt = $pdo->prepare("SELECT id FROM qr_codes WHERE table_number IS NULL");
        $stmt->execute();
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing QR code
            $stmt = $pdo->prepare("
                UPDATE qr_codes 
                SET qr_url = ?, generated_by = ?, generated_at = NOW(), 
                    restaurant_name = ?, is_active = TRUE 
                WHERE table_number IS NULL
            ");
            $stmt->execute([$qr_url, $_SESSION['user_id'], $restaurant_name]);
        } else {
            // Insert new QR code
            $stmt = $pdo->prepare("
                INSERT INTO qr_codes (table_number, qr_url, generated_by, restaurant_name, is_active) 
                VALUES (NULL, ?, ?, ?, TRUE)
            ");
            $stmt->execute([$qr_url, $_SESSION['user_id'], $restaurant_name]);
        }
        
        // Log the generation
        $stmt = $pdo->prepare("
            INSERT INTO qr_activity_log (user_id, table_number, action, timestamp) 
            VALUES (?, NULL, 'qr_generated', NOW())
        ");
        $stmt->execute([$_SESSION['user_id']]);
        
        $generated_qr = [
            'qr_url' => $qr_url,
            'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qr_url)
        ];
        
        $pdo->commit();
        $message = 'Successfully generated restaurant QR code!';
        
    } catch (Exception $e) {
        $pdo->rollback();
        $error = 'Failed to generate QR code: ' . $e->getMessage();
    }
}

// Get existing QR code if any
$stmt = $pdo->query("
    SELECT qc.*, u.username as generated_by_name
    FROM qr_codes qc 
    LEFT JOIN users u ON qc.generated_by = u.id 
    WHERE qc.table_number IS NULL
    LIMIT 1
");
$existing_qr = $stmt->fetch();

$page_title = 'QR Code Generator';
include '../includes/header.php';
?>

<h1>🔳 Restaurant QR Code Generator</h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- Generation Form -->
<div style="margin: 30px 0;">
    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto;">
        <h3 style="margin-bottom: 20px; color: #2c3e50; text-align: center;">📋 Generate Restaurant QR Code</h3>
        <p style="margin-bottom: 20px; color: #666; text-align: center;">
            This will generate a single QR code that links directly to your restaurant website.
        </p>
        
        <form method="POST">
            <div class="form-group">
                <label for="restaurant_name">Restaurant Name:</label>
                <input type="text" name="restaurant_name" id="restaurant_name" 
                       value="QR Food Ordering" maxlength="100"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            
            <button type="submit" name="generate_qr" class="btn" style="width: 100%; margin-top: 20px;">
                🔳 Generate QR Code
            </button>
        </form>
    </div>
</div>

<!-- Generated QR Code Display -->
<?php if ($generated_qr): ?>
    <div style="background: #f8f9fa; padding: 25px; border-radius: 8px; margin: 30px 0; text-align: center;">
        <h3 style="margin-bottom: 20px; color: #2c3e50;">✅ Generated Restaurant QR Code</h3>
        
        <div style="background: white; border: 2px solid #27ae60; border-radius: 8px; padding: 20px; display: inline-block; margin: 0 auto; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <h4 style="margin-bottom: 15px; color: #27ae60;">Restaurant QR Code</h4>
            
            <img src="<?php echo htmlspecialchars($generated_qr['qr_image_url']); ?>" 
                 alt="Restaurant QR Code"
                 style="width: 250px; height: 250px; border: 1px solid #ddd; border-radius: 5px; margin: 10px 0;">
            
            <div style="font-size: 14px; color: #666; word-break: break-all; margin: 15px 0; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                <?php echo htmlspecialchars($generated_qr['qr_url']); ?>
            </div>
            
            <div style="display: flex; justify-content: center; gap: 15px; margin-top: 15px;">
                <a href="<?php echo htmlspecialchars($generated_qr['qr_image_url']); ?>" 
                   download="restaurant_qr.png" 
                   class="btn btn-secondary" style="padding: 10px 20px; text-decoration: none;">
                    📥 Download
                </a>
                <button onclick="printQR('restaurant', '<?php echo htmlspecialchars($generated_qr['qr_image_url']); ?>')" 
                        class="btn" style="padding: 10px 20px;">
                    🖨️ Print
                </button>
            </div>
        </div>
    </div>
<?php elseif ($existing_qr): ?>
    <div style="background: #f8f9fa; padding: 25px; border-radius: 8px; margin: 30px 0; text-align: center;">
        <h3 style="margin-bottom: 20px; color: #2c3e50;">Existing Restaurant QR Code</h3>
        
        <div style="background: white; border: 2px solid #3498db; border-radius: 8px; padding: 20px; display: inline-block; margin: 0 auto; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <h4 style="margin-bottom: 15px; color: #3498db;">Restaurant QR Code</h4>
            
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?php echo urlencode($existing_qr['qr_url']); ?>" 
                 alt="Restaurant QR Code"
                 style="width: 250px; height: 250px; border: 1px solid #ddd; border-radius: 5px; margin: 10px 0;">
            
            <div style="font-size: 14px; color: #666; word-break: break-all; margin: 15px 0; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                <?php echo htmlspecialchars($existing_qr['qr_url']); ?>
            </div>
            
            <div style="margin-top: 10px; color: #666; font-size: 12px;">
                Generated by: <?php echo htmlspecialchars($existing_qr['generated_by_name']); ?><br>
                Date: <?php echo date('M j, Y g:i A', strtotime($existing_qr['generated_at'])); ?>
            </div>
            
            <div style="display: flex; justify-content: center; gap: 15px; margin-top: 15px;">
                <a href="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?php echo urlencode($existing_qr['qr_url']); ?>" 
                   download="restaurant_qr.png" 
                   class="btn btn-secondary" style="padding: 10px 20px; text-decoration: none;">
                    📥 Download
                </a>
                <button onclick="printQR('restaurant', 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?php echo urlencode($existing_qr['qr_url']); ?>')" 
                        class="btn" style="padding: 10px 20px;">
                    🖨️ Print
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function printQR(label, imageUrl) {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Print QR Code</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; }
                .container { margin: 20px; }
                img { max-width: 300px; }
                h2 { color: #2c3e50; }
                .info { margin: 15px 0; color: #666; }
                @media print {
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h2>Restaurant QR Code</h2>
                <img src="${imageUrl}" alt="QR Code">
                <div class="info">
                    <p>Scan to order online</p>
                    <p>${new Date().toLocaleDateString()}</p>
                </div>
                <button class="no-print" onclick="window.print();setTimeout(() => window.close(), 500)">
                    Print
                </button>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
}
</script>

<?php include '../includes/footer.php'; ?>