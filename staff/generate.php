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
$generated_qrs = [];

// Generate QR codes
if ($_POST && isset($_POST['generate_qrs'])) {
    $table_numbers = array_filter(array_map('intval', explode(',', $_POST['table_numbers'])));
    $restaurant_name = trim($_POST['restaurant_name']) ?: 'QR Food Ordering';
    $base_url = 'https://qr-code-online.infinityfreeapp.com';
    
    if (empty($table_numbers)) {
        $error = 'Please enter valid table numbers';
    } else {
        try {
            $pdo->beginTransaction();
            
            foreach ($table_numbers as $table_number) {
                if ($table_number < 1 || $table_number > 999) {
                    continue; // Skip invalid table numbers
                }
                
                // Generate QR URL
                $qr_url = $base_url . '/qr/scan.php?table=' . $table_number;
                
                // Check if table already exists
                $stmt = $pdo->prepare("SELECT id FROM qr_codes WHERE table_number = ?");
                $stmt->execute([$table_number]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Update existing QR code
                    $stmt = $pdo->prepare("
                        UPDATE qr_codes 
                        SET qr_url = ?, generated_by = ?, generated_at = NOW(), 
                            restaurant_name = ?, is_active = TRUE 
                        WHERE table_number = ?
                    ");
                    $stmt->execute([$qr_url, $_SESSION['user_id'], $restaurant_name, $table_number]);
                } else {
                    // Insert new QR code
                    $stmt = $pdo->prepare("
                        INSERT INTO qr_codes (table_number, qr_url, generated_by, restaurant_name, is_active) 
                        VALUES (?, ?, ?, ?, TRUE)
                    ");
                    $stmt->execute([$table_number, $qr_url, $_SESSION['user_id'], $restaurant_name]);
                }
                
                // Log the generation
                $stmt = $pdo->prepare("
                    INSERT INTO qr_activity_log (user_id, table_number, action, timestamp) 
                    VALUES (?, ?, 'qr_generated', NOW())
                ");
                $stmt->execute([$_SESSION['user_id'], $table_number]);
                
                $generated_qrs[] = [
                    'table_number' => $table_number,
                    'qr_url' => $qr_url,
                    'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qr_url)
                ];
            }
            
            $pdo->commit();
            $message = 'Successfully generated ' . count($generated_qrs) . ' QR code(s)!';
            
        } catch (Exception $e) {
            $pdo->rollback();
            $error = 'Failed to generate QR codes: ' . $e->getMessage();
        }
    }
}

// Bulk generate for multiple tables
if ($_POST && isset($_POST['bulk_generate'])) {
    $start_table = (int)$_POST['start_table'];
    $end_table = (int)$_POST['end_table'];
    $restaurant_name = trim($_POST['restaurant_name']) ?: 'QR Food Ordering';
    $base_url = 'https://qr-code-online.infinityfreeapp.com';
    
    if ($start_table < 1 || $end_table < 1 || $start_table > $end_table || ($end_table - $start_table) > 100) {
        $error = 'Invalid table range. Maximum 100 tables at once.';
    } else {
        try {
            $pdo->beginTransaction();
            
            for ($table_number = $start_table; $table_number <= $end_table; $table_number++) {
                $qr_url = $base_url . '/qr/scan.php?table=' . $table_number;
                
                // Insert or update QR code
                $stmt = $pdo->prepare("
                    INSERT INTO qr_codes (table_number, qr_url, generated_by, restaurant_name, is_active) 
                    VALUES (?, ?, ?, ?, TRUE)
                    ON DUPLICATE KEY UPDATE 
                    qr_url = VALUES(qr_url), 
                    generated_by = VALUES(generated_by), 
                    generated_at = NOW(),
                    restaurant_name = VALUES(restaurant_name),
                    is_active = TRUE
                ");
                $stmt->execute([$table_number, $qr_url, $_SESSION['user_id'], $restaurant_name]);
                
                // Log the generation
                $stmt = $pdo->prepare("
                    INSERT INTO qr_activity_log (user_id, table_number, action, timestamp) 
                    VALUES (?, ?, 'qr_generated', NOW())
                ");
                $stmt->execute([$_SESSION['user_id'], $table_number]);
                
                $generated_qrs[] = [
                    'table_number' => $table_number,
                    'qr_url' => $qr_url,
                    'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qr_url)
                ];
            }
            
            $pdo->commit();
            $message = 'Successfully generated ' . count($generated_qrs) . ' QR codes (Tables ' . $start_table . '-' . $end_table . ')!';
            
        } catch (Exception $e) {
            $pdo->rollback();
            $error = 'Failed to generate QR codes: ' . $e->getMessage();
        }
    }
}

// Get existing QR codes for display
$stmt = $pdo->query("
    SELECT qc.*, u.username as generated_by_name,
           (SELECT COUNT(*) FROM qr_scans qs WHERE qs.table_number = qc.table_number) as total_scans
    FROM qr_codes qc 
    LEFT JOIN users u ON qc.generated_by = u.id 
    ORDER BY qc.table_number
");
$existing_qrs = $stmt->fetchAll();

// Get QR statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_qr_codes,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_qr_codes,
        COUNT(CASE WHEN last_scanned IS NOT NULL THEN 1 END) as scanned_qr_codes,
        COALESCE(SUM(scan_count), 0) as total_scans
    FROM qr_codes
");
$qr_stats = $stmt->fetch();

$page_title = 'QR Code Generator';
include '../includes/header.php';
?>

<h1>🔳 QR Code Generator</h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- QR Statistics -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin: 30px 0;">
    <div style="background: #3498db; color: white; padding: 25px; border-radius: 8px; text-align: center;">
        <h2><?php echo (int)$qr_stats['total_qr_codes']; ?></h2>
        <p>Total QR Codes</p>
    </div>
    
    <div style="background: #27ae60; color: white; padding: 25px; border-radius: 8px; text-align: center;">
        <h2><?php echo (int)$qr_stats['active_qr_codes']; ?></h2>
        <p>Active Codes</p>
    </div>
    
    <div style="background: #e67e22; color: white; padding: 25px; border-radius: 8px; text-align: center;">
        <h2><?php echo (int)$qr_stats['scanned_qr_codes']; ?></h2>
        <p>Ever Scanned</p>
    </div>
    
    <div style="background: #8e44ad; color: white; padding: 25px; border-radius: 8px; text-align: center;">
        <h2><?php echo (int)$qr_stats['total_scans']; ?></h2>
        <p>Total Scans</p>
    </div>
</div>

<!-- Generation Forms -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 30px 0;">
    <!-- Individual Tables -->
    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h3 style="margin-bottom: 20px; color: #2c3e50;">📋 Generate Specific Tables</h3>
        <form method="POST">
            <div class="form-group">
                <label for="table_numbers">Table Numbers:</label>
                <input type="text" name="table_numbers" id="table_numbers" 
                       placeholder="e.g., 1,2,3,5,8" required
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                <small style="color: #666; display: block; margin-top: 5px;">
                    Enter comma-separated table numbers (e.g., 1,2,3,5,8)
                </small>
            </div>
            
            <div class="form-group">
                <label for="restaurant_name">Restaurant Name:</label>
                <input type="text" name="restaurant_name" id="restaurant_name" 
                       value="QR Food Ordering" maxlength="100"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            
            <button type="submit" name="generate_qrs" class="btn" style="width: 100%; margin-top: 10px;">
                🔳 Generate QR Codes
            </button>
        </form>
    </div>
    
    <!-- Bulk Generation -->
    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h3 style="margin-bottom: 20px; color: #2c3e50;">⚡ Bulk Generate Range</h3>
        <form method="POST">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label for="start_table">Start Table:</label>
                    <input type="number" name="start_table" id="start_table" 
                           min="1" max="999" value="1" required
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                </div>
                
                <div class="form-group">
                    <label for="end_table">End Table:</label>
                    <input type="number" name="end_table" id="end_table" 
                           min="1" max="999" value="20" required
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                </div>
            </div>
            
            <div class="form-group">
                <label for="bulk_restaurant_name">Restaurant Name:</label>
                <input type="text" name="restaurant_name" id="bulk_restaurant_name" 
                       value="QR Food Ordering" maxlength="100"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            
            <small style="color: #f39c12; display: block; margin: 10px 0;">
                ⚠️ Maximum 100 tables per batch
            </small>
            
            <button type="submit" name="bulk_generate" class="btn" style="width: 100%; background: #e67e22;">
                ⚡ Bulk Generate
            </button>
        </form>
    </div>
</div>

<!-- Generated QR Codes Display -->
<?php if (!empty($generated_qrs)): ?>
    <div style="background: #f8f9fa; padding: 25px; border-radius: 8px; margin: 30px 0;">
        <h3 style="margin-bottom: 20px; color: #2c3e50;">✅ Recently Generated QR Codes</h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
            <?php foreach ($generated_qrs as $qr): ?>
                <div style="background: white; border: 2px solid #27ae60; border-radius: 8px; padding: 20px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    <h4 style="margin-bottom: 15px; color: #27ae60;">Table <?php echo (int)$qr['table_number']; ?></h4>
                    
                    <img src="<?php echo htmlspecialchars($qr['qr_image_url']); ?>" 
                         alt="QR Code for Table <?php echo (int)$qr['table_number']; ?>"
                         style="width: 150px; height: 150px; border: 1px solid #ddd; border-radius: 5px; margin: 10px 0;">
                    
                    <div style="font-size: 11px; color: #666; word-break: break-all; margin: 10px 0; padding: 8px; background: #f8f9fa; border-radius: 4px;">
                        <?php echo htmlspecialchars($qr['qr_url']); ?>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 15px;">
                        <a href="<?php echo htmlspecialchars($qr['qr_image_url']); ?>" 
                           download="table_<?php echo (int)$qr['table_number']; ?>_qr.png" 
                           class="btn btn-secondary" style="padding: 8px; font-size: 12px; text-decoration: none;">
                            📥 Download
                        </a>
                        <button onclick="printQR(<?php echo (int)$qr['table_number']; ?>, '<?php echo htmlspecialchars($qr['qr_image_url']); ?>')" 
                                class="btn" style="padding: 8px; font-size: 12px;">
                            🖨️ Print
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <button onclick="downloadAllQRs()" class="btn" style="margin-right: 10px;">
                📥 Download All
            </button>
            <button onclick="printAllQRs()" class="btn btn-secondary">
                🖨️ Print All
            </button>
        </div>
    </div>
<?php endif; ?>

<!-- Existing QR Codes Management -->
<div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin: 30px 0;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin: 0; color: #2c3e50;">📊 Existing QR Codes</h3>
        <div>
            <button onclick="toggleView()" class="btn btn-secondary" id="viewToggle">
                📋 Switch to List View
            </button>
            <button onclick="exportQRData()" class="btn">
                📄 Export Data
            </button>
        </div>
    </div>
    
    <?php if (empty($existing_qrs)): ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <p>No QR codes generated yet. Create your first QR code above!</p>
        </div>
    <?php else: ?>
        <!-- Grid View -->
        <div id="gridView" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            <?php foreach ($existing_qrs as $qr): ?>
                <div style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; background: <?php echo $qr['is_active'] ? '#f9f9f9' : '#f5f5f5'; ?>; opacity: <?php echo $qr['is_active'] ? '1' : '0.7'; ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h4 style="margin: 0; color: #2c3e50;">Table <?php echo (int)$qr['table_number']; ?></h4>
                        <span style="background: <?php echo $qr['is_active'] ? '#27ae60' : '#e74c3c'; ?>; color: white; padding: 3px 8px; border-radius: 10px; font-size: 11px;">
                            <?php echo $qr['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                    
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($qr['qr_url']); ?>" 
                         alt="QR Code for Table <?php echo (int)$qr['table_number']; ?>"
                         style="width: 100px; height: 100px; border: 1px solid #ddd; border-radius: 4px; display: block; margin: 10px auto;">
                    
                    <div style="font-size: 12px; color: #666; margin: 10px 0;">
                        <div><strong>Generated:</strong> <?php echo htmlspecialchars(date('M j, Y', strtotime($qr['generated_at']))); ?></div>
                        <div><strong>By:</strong> <?php echo htmlspecialchars($qr['generated_by_name'] ?? 'Unknown'); ?></div>
                        <div><strong>Scans:</strong> <?php echo (int)$qr['total_scans']; ?></div>
                        <?php if ($qr['last_scanned']): ?>
                            <div><strong>Last Scan:</strong> <?php echo htmlspecialchars(date('M j, Y', strtotime($qr['last_scanned']))); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px; margin-top: 10px;">
                        <a href="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?php echo urlencode($qr['qr_url']); ?>" 
                           download="table_<?php echo (int)$qr['table_number']; ?>_qr.png" 
                           class="btn btn-secondary" style="padding: 6px; font-size: 11px; text-decoration: none; text-align: center;">
                            📥
                        </a>
                        <button onclick="printQR(<?php echo (int)$qr['table_number']; ?>, 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?php echo urlencode($qr['qr_url']); ?>')" 
                                class="btn" style="padding: 6px; font-size: 11px;">
                            🖨️
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- List View (Hidden by default) -->
        <div id="listView" style="display: none;">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; background: white;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Table</th>
                            <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Status</th>
                            <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Restaurant</th>
                            <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Generated</th>
                            <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Scans</th>
                            <th style="padding: 12px; border: 1px solid #ddd; text-align: left;">Last Scan</th>
                            <th style="padding: 12px; border: 1px solid #ddd; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($existing_qrs as $qr): ?>
                            <tr style="<?php echo $qr['is_active'] ? '' : 'opacity: 0.6;'; ?>">
                                <td style="padding: 12px; border: 1px solid #ddd; font-weight: bold;">
                                    Table <?php echo (int)$qr['table_number']; ?>
                                </td>
                                <td style="padding: 12px; border: 1px solid #ddd;">
                                    <span style="background: <?php echo $qr['is_active'] ? '#27ae60' : '#e74c3c'; ?>; color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px;">
                                        <?php echo $qr['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; border: 1px solid #ddd;"><?php echo htmlspecialchars($qr['restaurant_name']); ?></td>
                                <td style="padding: 12px; border: 1px solid #ddd; font-size: 12px;">
                                    <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($qr['generated_at']))); ?><br>
                                    <small style="color: #666;">by <?php echo htmlspecialchars($qr['generated_by_name'] ?? 'Unknown'); ?></small>
                                </td>
                                <td style="padding: 12px; border: 1px solid #ddd; text-align: center; font-weight: bold;">
                                    <?php echo (int)$qr['total_scans']; ?>
                                </td>
                                <td style="padding: 12px; border: 1px solid #ddd; font-size: 12px;">
                                    <?php echo $qr['last_scanned'] ? date('M j, Y g:i A', strtotime($qr['last_scanned'])) : 'Never'; ?>
                                </td>
                                <td style="padding: 12px; border: 1px solid #ddd; text-align: center;">
                                    <div style="display: flex; gap: 5px; justify-content: center;">
                                        <a href="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?php echo urlencode($qr['qr_url']); ?>" 
                                           download="table_<?php echo (int)$qr['table_number']; ?>_qr.png" 
                                           class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px; text-decoration: none;">
                                            📥
                                        </a>
                                        <button onclick="printQR(<?php echo (int)$qr['table_number']; ?>, 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?php echo urlencode($qr['qr_url']); ?>')" 
                                                class="btn" style="padding: 4px 8px; font-size: 11px;">
                                            🖨️
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>


<script>
// Quick generate function
function quickGenerate(tableNumbers) {
    document.getElementById('table_numbers').value = tableNumbers.join(',');
    document.querySelector('input[name="generate_qrs"]').click();
}

// Print single QR code
function printQR(tableNumber, imageUrl) {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>QR Code - Table ${tableNumber}</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    text-align: center; 
                    margin: 20px; 
                    background: white;
                }
                .qr-container { 
                    border: 2px solid #333; 
                    padding: 30px; 
                    margin: 20px auto; 
                    display: inline-block; 
                    background: white;
                    border-radius: 10px;
                }
                img { 
                    border: 1px solid #ddd; 
                    margin: 20px 0;
                }
                h1 { 
                    margin: 10px 0; 
                    color: #2c3e50;
                }
                .instructions {
                    font-size: 14px;
                    color: #666;
                    margin-top: 20px;
                    max-width: 300px;
                    margin-left: auto;
                    margin-right: auto;
                }
                @media print {
                    body { margin: 0; }
                    .qr-container { 
                        page-break-inside: avoid; 
                        margin: 0;
                    }
                }
            </style>
        </head>
        <body>
            <div class="qr-container">
                <h1>🍽️ QR Food Ordering</h1>
                <h2>Table ${tableNumber}</h2>
                <img src="${imageUrl}" alt="QR Code for Table ${tableNumber}" width="250" height="250">
                <div class="instructions">
                    <p><strong>📱 How to Order:</strong></p>
                    <p>1. Scan this QR code with your phone camera</p>
                    <p>2. Browse our menu online</p>
                    <p>3. Add items to cart and place order</p>
                    <p>4. Your food will be delivered to this table</p>
                </div>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
}

// Print all generated QR codes
function printAllQRs() {
    const qrCodes = <?php echo json_encode($generated_qrs); ?>;
    if (qrCodes.length === 0) {
        alert('No QR codes to print. Generate some first!');
        return;
    }
    
    const printWindow = window.open('', '_blank');
    let content = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>QR Codes - All Tables</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 20px; 
                    background: white;
                }
                .qr-grid { 
                    display: grid; 
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
                    gap: 30px; 
                    margin: 20px 0;
                }
                .qr-container { 
                    border: 2px solid #333; 
                    padding: 20px; 
                    text-align: center; 
                    background: white;
                    border-radius: 10px;
                    page-break-inside: avoid;
                }
                img { 
                    border: 1px solid #ddd; 
                    margin: 15px 0;
                }
                h1 { 
                    text-align: center; 
                    color: #2c3e50; 
                    margin-bottom: 30px;
                }
                h3 { 
                    margin: 10px 0; 
                    color: #2c3e50;
                }
                .instructions {
                    font-size: 12px;
                    color: #666;
                    margin-top: 15px;
                }
                @media print {
                    body { margin: 10px; }
                    .qr-container { margin: 0; }
                }
            </style>
        </head>
        <body>
            <h1>🍽️ QR Food Ordering - Table QR Codes</h1>
            <div class="qr-grid">
    `;
    
    qrCodes.forEach(qr => {
        content += `
            <div class="qr-container">
                <h3>Table ${qr.table_number}</h3>
                <img src="${qr.qr_image_url}" alt="QR Code for Table ${qr.table_number}" width="200" height="200">
                <div class="instructions">
                    <p><strong>📱 Scan to Order</strong></p>
                    <p>Use phone camera to scan and order food</p>
                </div>
            </div>
        `;
    });
    
    content += `
            </div>
        </body>
        </html>
    `;
    
    printWindow.document.write(content);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 1000);
}

// Download all QR codes
function downloadAllQRs() {
    const qrCodes = <?php echo json_encode($generated_qrs); ?>;
    if (qrCodes.length === 0) {
        alert('No QR codes to download. Generate some first!');
        return;
    }
    
    qrCodes.forEach((qr, index) => {
        setTimeout(() => {
            const link = document.createElement('a');
            link.href = qr.qr_image_url;
            link.download = `table_${qr.table_number}_qr.png`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }, index * 200); // Delay downloads to avoid browser blocking
    });
}

// Toggle between grid and list view
function toggleView() {
    const gridView = document.getElementById('gridView');
    const listView = document.getElementById('listView');
    const toggleBtn = document.getElementById('viewToggle');
    
    if (gridView.style.display === 'none') {
        gridView.style.display = 'grid';
        listView.style.display = 'none';
        toggleBtn.textContent = '📋 Switch to List View';
    } else {
        gridView.style.display = 'none';
        listView.style.display = 'block';
        toggleBtn.textContent = '⚙️ Switch to Grid View';
    }
}

// Export QR data as CSV
function exportQRData() {
    const qrData = <?php echo json_encode($existing_qrs); ?>;
    if (qrData.length === 0) {
        alert('No QR code data to export.');
        return;
    }
    
    let csv = 'Table Number,Status,Restaurant Name,Generated Date,Generated By,Total Scans,Last Scanned,QR URL\n';
    
    qrData.forEach(qr => {
        csv += `${qr.table_number},${qr.is_active ? 'Active' : 'Inactive'},"${qr.restaurant_name}","${qr.generated_at}","${qr.generated_by_name || 'Unknown'}",${qr.total_scans},"${qr.last_scanned || 'Never'}","${qr.qr_url}"\n`;
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `qr_codes_export_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
}

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    // Table numbers validation
    const tableNumbersInput = document.getElementById('table_numbers');
    tableNumbersInput.addEventListener('input', function() {
        const value = this.value.trim();
        if (value) {
            const numbers = value.split(',').map(n => parseInt(n.trim())).filter(n => !isNaN(n));
            if (numbers.length === 0) {
                this.setCustomValidity('Please enter valid table numbers');
            } else if (numbers.some(n => n < 1 || n > 999)) {
                this.setCustomValidity('Table numbers must be between 1 and 999');
            } else {
                this.setCustomValidity('');
            }
        } else {
            this.setCustomValidity('');
        }
    });
    
    // Bulk generation validation
    const startTable = document.getElementById('start_table');
    const endTable = document.getElementById('end_table');
    
    function validateRange() {
        const start = parseInt(startTable.value);
        const end = parseInt(endTable.value);
        
        if (start > end) {
            endTable.setCustomValidity('End table must be greater than or equal to start table');
        } else if ((end - start) > 100) {
            endTable.setCustomValidity('Maximum 100 tables can be generated at once');
        } else {
            endTable.setCustomValidity('');
        }
    }
    
    startTable.addEventListener('input', validateRange);
    endTable.addEventListener('input', validateRange);
    
    // Auto-focus on first input
    tableNumbersInput.focus();
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + G for quick generate
    if ((e.ctrlKey || e.metaKey) && e.key === 'g') {
        e.preventDefault();
        document.getElementById('table_numbers').focus();
    }
    
    // Ctrl/Cmd + P for print
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        if (<?php echo json_encode(!empty($generated_qrs)); ?>) {
            printAllQRs();
        } else {
            alert('No QR codes to print. Generate some first!');
        }
    }
    
    // Escape to clear forms
    if (e.key === 'Escape') {
        document.getElementById('table_numbers').value = '';
        document.getElementById('start_table').value = '1';
        document.getElementById('end_table').value = '20';
    }
});

// Auto-suggestion for common table setups
function showTableSuggestions() {
    const suggestions = [
        { name: 'Small Cafe (1-10)', value: '1,2,3,4,5,6,7,8,9,10' },
        { name: 'Medium Restaurant (1-20)', value: Array.from({length: 20}, (_, i) => i + 1).join(',') },
        { name: 'Large Restaurant (1-50)', value: Array.from({length: 50}, (_, i) => i + 1).join(',') },
        { name: 'Food Court (101-120)', value: Array.from({length: 20}, (_, i) => i + 101).join(',') }
    ];
    
    let suggestion = '';
    suggestions.forEach((s, i) => {
        suggestion += `${i + 1}. ${s.name}\n`;
    });
    
    const choice = prompt(`Choose a table setup:\n\n${suggestion}\nEnter number (1-${suggestions.length}):`);
    const index = parseInt(choice) - 1;
    
    if (index >= 0 && index < suggestions.length) {
        document.getElementById('table_numbers').value = suggestions[index].value;
    }
}

// Add suggestion button
document.addEventListener('DOMContentLoaded', function() {
    const tableNumbersGroup = document.getElementById('table_numbers').parentElement;
    const suggestionBtn = document.createElement('button');
    suggestionBtn.type = 'button';
    suggestionBtn.textContent = '💡 Suggestions';
    suggestionBtn.className = 'btn btn-secondary';
    suggestionBtn.style.marginTop = '10px';
    suggestionBtn.style.fontSize = '12px';
    suggestionBtn.onclick = showTableSuggestions;
    tableNumbersGroup.appendChild(suggestionBtn);
});

// Real-time table number preview
document.getElementById('table_numbers').addEventListener('input', function() {
    const value = this.value.trim();
    const preview = document.getElementById('tablePreview') || createPreviewElement();
    
    if (value) {
        const numbers = value.split(',').map(n => parseInt(n.trim())).filter(n => !isNaN(n) && n >= 1 && n <= 999);
        if (numbers.length > 0) {
            preview.innerHTML = `<strong>Preview:</strong> Will generate ${numbers.length} QR code(s) for table(s): ${numbers.sort((a,b) => a-b).join(', ')}`;
            preview.style.color = '#27ae60';
        } else {
            preview.innerHTML = '<strong>Preview:</strong> No valid table numbers entered';
            preview.style.color = '#e74c3c';
        }
    } else {
        preview.innerHTML = '';
    }
});

function createPreviewElement() {
    const preview = document.createElement('div');
    preview.id = 'tablePreview';
    preview.style.fontSize = '12px';
    preview.style.marginTop = '8px';
    preview.style.padding = '8px';
    preview.style.backgroundColor = '#f8f9fa';
    preview.style.borderRadius = '4px';
    preview.style.border = '1px solid #e9ecef';
    
    const tableNumbersGroup = document.getElementById('table_numbers').parentElement;
    tableNumbersGroup.appendChild(preview);
    
    return preview;
}

// Bulk range preview
document.getElementById('start_table').addEventListener('input', updateBulkPreview);
document.getElementById('end_table').addEventListener('input', updateBulkPreview);

function updateBulkPreview() {
    const start = parseInt(document.getElementById('start_table').value);
    const end = parseInt(document.getElementById('end_table').value);
    const preview = document.getElementById('bulkPreview') || createBulkPreviewElement();
    
    if (start && end && start <= end && start >= 1 && end <= 999) {
        const count = end - start + 1;
        if (count <= 100) {
            preview.innerHTML = `<strong>Preview:</strong> Will generate ${count} QR code(s) for tables ${start} to ${end}`;
            preview.style.color = '#27ae60';
        } else {
            preview.innerHTML = `<strong>Preview:</strong> Too many tables (${count}). Maximum 100 allowed.`;
            preview.style.color = '#e74c3c';
        }
    } else if (start > end) {
        preview.innerHTML = '<strong>Preview:</strong> Start table must be less than or equal to end table';
        preview.style.color = '#e74c3c';
    } else {
        preview.innerHTML = '';
    }
}

function createBulkPreviewElement() {
    const preview = document.createElement('div');
    preview.id = 'bulkPreview';
    preview.style.fontSize = '12px';
    preview.style.marginTop = '8px';
    preview.style.padding = '8px';
    preview.style.backgroundColor = '#f8f9fa';
    preview.style.borderRadius = '4px';
    preview.style.border = '1px solid #e9ecef';
    
    const endTableGroup = document.getElementById('end_table').parentElement;
    endTableGroup.appendChild(preview);
    
    return preview;
}

// Loading states for form submissions
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = submitBtn.innerHTML.replace(/🔳|⚡/, '⏳') + ' Generating...';
        }
    });
});

// Success message auto-hide
<?php if ($message): ?>
setTimeout(() => {
    const successAlert = document.querySelector('.alert-success');
    if (successAlert) {
        successAlert.style.opacity = '0';
        successAlert.style.transition = 'opacity 0.5s';
        setTimeout(() => {
            successAlert.style.display = 'none';
        }, 500);
    }
}, 5000);
<?php endif; ?>

// QR code image error handling
document.addEventListener('DOMContentLoaded', function() {
    const qrImages = document.querySelectorAll('img[alt*="QR Code"]');
    qrImages.forEach(img => {
        img.addEventListener('error', function() {
            this.style.display = 'none';
            const placeholder = document.createElement('div');
            placeholder.style.cssText = `
                width: ${this.width || 150}px;
                height: ${this.height || 150}px;
                background: #f8f9fa;
                border: 1px solid #ddd;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #666;
                font-size: 12px;
                text-align: center;
                border-radius: 4px;
                margin: ${this.style.margin || '10px auto'};
            `;
            placeholder.innerHTML = '⚠️<br>QR Image<br>Failed to Load';
            this.parentNode.insertBefore(placeholder, this.nextSibling);
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>