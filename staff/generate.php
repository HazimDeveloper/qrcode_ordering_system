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
$qr_codes = [];

// Base domain for QR codes
$base_domain = 'https://qr-code-online.infinityfreeapp.com';

// Handle image upload for custom logo
function handleLogoUpload($file) {
    $upload_dir = '../images/qr_logos/';
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    // Check if directory exists, create if not
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload failed with error code: ' . $file['error']);
    }
    
    if ($file['size'] > $max_size) {
        throw new Exception('Logo file size too large. Maximum 2MB allowed.');
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('Invalid logo file type. Only JPEG, PNG, and GIF are allowed.');
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'logo_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to move uploaded logo file.');
    }
    
    return $filename;
}

// Generate QR Code for single table
if ($_POST && isset($_POST['generate_qr'])) {
    $table_number = (int)$_POST['table_number'];
    $restaurant_name = trim($_POST['restaurant_name']) ?: 'QR Food Ordering';
    $qr_size = (int)($_POST['qr_size'] ?? 300);
    $error_correction = $_POST['error_correction'] ?? 'M';
    $logo_filename = '';
    
    if ($table_number > 0 && $table_number <= 999) {
        try {
            // Handle logo upload if provided
            if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
                $logo_filename = handleLogoUpload($_FILES['logo_upload']);
            }
            
            // Create QR code URL - points to the ordering page with table number
            $qr_url = $base_domain . '/qr/scan.php?table=' . $table_number;
            
            // Generate QR code using Google Charts API with custom parameters
            $chart_params = [
                'chs' => $qr_size . 'x' . $qr_size,
                'cht' => 'qr',
                'chl' => $qr_url,
                'choe' => 'UTF-8',
                'chld' => $error_correction . '|0'
            ];
            
            $qr_image_url = 'https://chart.googleapis.com/chart?' . http_build_query($chart_params);
            
            $qr_codes[] = [
                'table_number' => $table_number,
                'url' => $qr_url,
                'image_url' => $qr_image_url,
                'restaurant_name' => $restaurant_name,
                'qr_size' => $qr_size,
                'error_correction' => $error_correction,
                'logo' => $logo_filename,
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
            // Save to database
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO qr_codes (table_number, qr_url, generated_by, restaurant_name, is_active) 
                    VALUES (?, ?, ?, ?, 1) 
                    ON DUPLICATE KEY UPDATE 
                        qr_url = VALUES(qr_url), 
                        generated_by = VALUES(generated_by), 
                        restaurant_name = VALUES(restaurant_name),
                        generated_at = CURRENT_TIMESTAMP,
                        is_active = 1
                ");
                $stmt->execute([$table_number, $qr_url, $_SESSION['user_id'], $restaurant_name]);
                
                // Log the generation activity
                $stmt = $pdo->prepare("
                    INSERT INTO qr_activity_log (user_id, table_number, action, timestamp) 
                    VALUES (?, ?, 'qr_generated', NOW())
                ");
                $stmt->execute([$_SESSION['user_id'], $table_number]);
            } catch (Exception $e) {
                // Continue without database logging if tables don't exist
            }
            
            $message = "QR Code generated successfully for Table $table_number!";
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Please enter a valid table number (1-999)';
    }
}

// Generate multiple QR codes
if ($_POST && isset($_POST['generate_multiple'])) {
    $start_table = (int)$_POST['start_table'];
    $end_table = (int)$_POST['end_table'];
    $restaurant_name = trim($_POST['restaurant_name']) ?: 'QR Food Ordering';
    $qr_size = (int)($_POST['qr_size'] ?? 300);
    $error_correction = $_POST['error_correction'] ?? 'M';
    
    if ($start_table > 0 && $end_table >= $start_table && $start_table <= 999 && $end_table <= 999) {
        $table_count = $end_table - $start_table + 1;
        
        if ($table_count <= 50) {
            try {
                for ($i = $start_table; $i <= $end_table; $i++) {
                    $qr_url = $base_domain . '/qr/scan.php?table=' . $i;
                    
                    $chart_params = [
                        'chs' => $qr_size . 'x' . $qr_size,
                        'cht' => 'qr',
                        'chl' => $qr_url,
                        'choe' => 'UTF-8',
                        'chld' => $error_correction . '|0'
                    ];
                    
                    $qr_image_url = 'https://chart.googleapis.com/chart?' . http_build_query($chart_params);
                    
                    $qr_codes[] = [
                        'table_number' => $i,
                        'url' => $qr_url,
                        'image_url' => $qr_image_url,
                        'restaurant_name' => $restaurant_name,
                        'qr_size' => $qr_size,
                        'error_correction' => $error_correction,
                        'logo' => '',
                        'generated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    // Save to database
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO qr_codes (table_number, qr_url, generated_by, restaurant_name, is_active) 
                            VALUES (?, ?, ?, ?, 1) 
                            ON DUPLICATE KEY UPDATE 
                                qr_url = VALUES(qr_url), 
                                generated_by = VALUES(generated_by), 
                                restaurant_name = VALUES(restaurant_name),
                                generated_at = CURRENT_TIMESTAMP,
                                is_active = 1
                        ");
                        $stmt->execute([$i, $qr_url, $_SESSION['user_id'], $restaurant_name]);
                    } catch (Exception $e) {
                        // Continue without database logging
                    }
                }
                
                $message = "Generated $table_count QR codes for tables $start_table to $end_table!";
            } catch (Exception $e) {
                $error = 'Error generating QR codes: ' . $e->getMessage();
            }
        } else {
            $error = 'Maximum 50 tables can be generated at once';
        }
    } else {
        $error = 'Please enter valid table numbers (1-999, start must be less than or equal to end)';
    }
}

// Get existing QR codes from database
$existing_qr_codes = [];
try {
    $stmt = $pdo->query("
        SELECT qr.*, u.username as generated_by_name 
        FROM qr_codes qr 
        LEFT JOIN users u ON qr.generated_by = u.id 
        WHERE qr.is_active = 1 
        ORDER BY qr.table_number ASC
    ");
    $existing_qr_codes = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue if table doesn't exist
}

// Get QR statistics
$qr_stats = [];
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_qr_codes,
            COUNT(DISTINCT table_number) as unique_tables,
            SUM(scan_count) as total_scans,
            MAX(last_scanned) as last_scan_time
        FROM qr_codes 
        WHERE is_active = 1
    ");
    $qr_stats = $stmt->fetch();
} catch (Exception $e) {
    $qr_stats = ['total_qr_codes' => 0, 'unique_tables' => 0, 'total_scans' => 0, 'last_scan_time' => null];
}

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
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
    <div style="background: #3498db; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo $qr_stats['total_qr_codes']; ?></h3>
        <p>Total QR Codes</p>
    </div>
    <div style="background: #27ae60; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo $qr_stats['unique_tables']; ?></h3>
        <p>Active Tables</p>
    </div>
    <div style="background: #e67e22; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo $qr_stats['total_scans']; ?></h3>
        <p>Total Scans</p>
    </div>
    <div style="background: #9b59b6; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo $qr_stats['last_scan_time'] ? date('M j, g:i A', strtotime($qr_stats['last_scan_time'])) : 'Never'; ?></h3>
        <p>Last Scan</p>
    </div>
</div>

<!-- Generator Forms -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 20px 0;">
    <!-- Single QR Code Generator -->
    <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h3 style="color: #2c3e50; margin-bottom: 20px;">📱 Generate Single QR Code</h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="restaurant_name">Restaurant Name:</label>
                <input type="text" name="restaurant_name" id="restaurant_name" 
                       value="QR Food Ordering" maxlength="50" 
                       placeholder="Enter your restaurant name">
                <small style="color: #666;">This will appear on the QR code</small>
            </div>
            
            <div class="form-group">
                <label for="table_number">Table Number: *</label>
                <input type="number" name="table_number" id="table_number" 
                       min="1" max="999" required placeholder="e.g., 5">
                <small style="color: #666;">Enter a number between 1-999</small>
            </div>
            
            <div class="form-group">
                <label for="qr_size">QR Code Size:</label>
                <select name="qr_size" id="qr_size" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="200">Small (200x200)</option>
                    <option value="300" selected>Medium (300x300)</option>
                    <option value="400">Large (400x400)</option>
                    <option value="500">Extra Large (500x500)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="error_correction">Error Correction:</label>
                <select name="error_correction" id="error_correction" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="L">Low (7%)</option>
                    <option value="M" selected>Medium (15%)</option>
                    <option value="Q">Quartile (25%)</option>
                    <option value="H">High (30%)</option>
                </select>
                <small style="color: #666;">Higher correction allows scanning even if damaged</small>
            </div>
            
            <div class="form-group">
                <label for="logo_upload">Restaurant Logo (Optional):</label>
                <input type="file" name="logo_upload" id="logo_upload" accept="image/*">
                <small style="color: #666;">Max 2MB. Will be embedded in QR code center.</small>
            </div>
            
            <button type="submit" name="generate_qr" class="btn" style="width: 100%;">
                🔳 Generate QR Code
            </button>
        </form>
    </div>
    
    <!-- Multiple QR Code Generator -->
    <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h3 style="color: #2c3e50; margin-bottom: 20px;">📱 Generate Multiple QR Codes</h3>
        <form method="POST" onsubmit="return validateBulkGeneration()">
            <div class="form-group">
                <label for="restaurant_name_multi">Restaurant Name:</label>
                <input type="text" name="restaurant_name" id="restaurant_name_multi" 
                       value="QR Food Ordering" maxlength="50" 
                       placeholder="Enter your restaurant name">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div class="form-group">
                    <label for="start_table">From Table: *</label>
                    <input type="number" name="start_table" id="start_table" 
                           min="1" max="999" required placeholder="1"
                           onchange="updateTableCount()">
                </div>
                
                <div class="form-group">
                    <label for="end_table">To Table: *</label>
                    <input type="number" name="end_table" id="end_table" 
                           min="1" max="999" required placeholder="10"
                           onchange="updateTableCount()">
                </div>
            </div>
            
            <div class="form-group">
                <label for="qr_size_multi">QR Code Size:</label>
                <select name="qr_size" id="qr_size_multi" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="200">Small (200x200)</option>
                    <option value="300" selected>Medium (300x300)</option>
                    <option value="400">Large (400x400)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="error_correction_multi">Error Correction:</label>
                <select name="error_correction" id="error_correction_multi" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="L">Low (7%)</option>
                    <option value="M" selected>Medium (15%)</option>
                    <option value="Q">Quartile (25%)</option>
                    <option value="H">High (30%)</option>
                </select>
            </div>
            
            <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0;">
                <small id="table-count-info" style="color: #666;">
                    📊 Will generate <span id="table-count">0</span> QR codes
                </small>
            </div>
            
            <p style="font-size: 12px; color: #e74c3c; margin: 10px 0;">
                ⚠️ Maximum 50 tables at once to prevent server overload
            </p>
            
            <button type="submit" name="generate_multiple" class="btn" style="width: 100%;">
                🔳 Generate Multiple QR Codes
            </button>
        </form>
    </div>
</div>

<!-- Domain Information -->
<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h3 style="margin-bottom: 15px;">🌐 QR Code Domain</h3>
    <p style="margin: 5px 0; font-size: 16px;">
        <strong>Domain:</strong> <code style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 4px;"><?php echo $base_domain; ?></code>
    </p>
    <p style="margin: 5px 0; font-size: 14px; opacity: 0.9;">
        All QR codes will redirect customers to this domain for ordering.
    </p>
</div>

<!-- Generated QR Codes Display -->
<?php if (!empty($qr_codes)): ?>
    <div style="margin: 30px 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
            <h3 style="color: #2c3e50;">Generated QR Codes (<?php echo count($qr_codes); ?>)</h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button onclick="printAllQRCodes()" class="btn">🖨️ Print All</button>
                <button onclick="downloadAllQRCodes()" class="btn btn-secondary">📥 Download All</button>
                <button onclick="previewPrintLayout()" class="btn btn-secondary">👁️ Preview Print</button>
            </div>
        </div>
        
        <div id="qr-codes-container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <?php foreach ($qr_codes as $qr): ?>
                <div class="qr-code-card" data-table="<?php echo $qr['table_number']; ?>" 
                     style="background: white; border: 2px solid #ddd; border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s;">
                    
                    <!-- Restaurant Header -->
                    <div style="border-bottom: 2px solid #e67e22; padding-bottom: 15px; margin-bottom: 15px;">
                        <h4 style="margin: 0 0 5px 0; color: #2c3e50; font-size: 16px;">
                            <?php echo htmlspecialchars($qr['restaurant_name']); ?>
                        </h4>
                        <h2 style="margin: 0; color: #e67e22; font-size: 28px; font-weight: bold;">
                            Table <?php echo $qr['table_number']; ?>
                        </h2>
                    </div>
                    
                    <!-- QR Code Image -->
                    <div style="margin: 20px 0; position: relative;">
                        <img src="<?php echo $qr['image_url']; ?>" 
                             alt="QR Code for Table <?php echo $qr['table_number']; ?>" 
                             style="width: <?php echo min($qr['qr_size'], 250); ?>px; height: <?php echo min($qr['qr_size'], 250); ?>px; border: 2px solid #ecf0f1; border-radius: 8px;"
                             loading="lazy"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        
                        <!-- Fallback if image fails to load -->
                        <div style="display: none; width: 250px; height: 250px; border: 2px dashed #bdc3c7; border-radius: 8px; 
                                    margin: 0 auto; display: flex; align-items: center; justify-content: center; color: #7f8c8d; flex-direction: column;">
                            <div style="font-size: 24px;">📱</div>
                            <div>QR Code</div>
                            <div style="font-size: 12px;">Table <?php echo $qr['table_number']; ?></div>
                        </div>
                    </div>
                    
                    <!-- Instructions -->
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">
                        <p style="margin: 0 0 8px 0; font-weight: bold; color: #2c3e50; font-size: 14px;">
                            📱 Scan to Order
                        </p>
                        <p style="margin: 0; font-size: 12px; color: #666;">
                            Point your phone camera at this code<br>
                            or use any QR scanner app
                        </p>
                    </div>
                    
                    <!-- Technical Info -->
                    <div style="background: #ecf0f1; padding: 10px; border-radius: 5px; margin: 10px 0;">
                        <p style="font-size: 10px; color: #7f8c8d; margin: 0 0 5px 0;">
                            Size: <?php echo $qr['qr_size']; ?>px | Error Correction: <?php echo $qr['error_correction']; ?>
                        </p>
                        <p style="font-size: 10px; color: #7f8c8d; margin: 0; word-break: break-all; font-family: monospace;">
                            <?php echo $qr['url']; ?>
                        </p>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 15px;">
                        <button onclick="printSingleQR(<?php echo $qr['table_number']; ?>)" 
                                class="btn btn-secondary" style="padding: 8px; font-size: 12px;">
                            🖨️ Print
                        </button>
                        <button onclick="downloadSingleQR('<?php echo $qr['image_url']; ?>', <?php echo $qr['table_number']; ?>)" 
                                class="btn btn-secondary" style="padding: 8px; font-size: 12px;">
                            💾 Download
                        </button>
                    </div>
                    
                    <!-- Test Button -->
                    <div style="margin-top: 10px;">
                        <a href="<?php echo $qr['url']; ?>" target="_blank" 
                           class="btn" style="padding: 8px 16px; font-size: 12px; text-decoration: none; width: 100%; display: block; box-sizing: border-box;">
                            🔗 Test QR Code
                        </a>
                    </div>
                    
                    <!-- Generation Info -->
                    <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #ecf0f1;">
                        <small style="color: #95a5a6; font-size: 10px;">
                            Generated: <?php echo date('M j, Y g:i A', strtotime($qr['generated_at'])); ?>
                        </small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Existing QR Codes -->
<?php if (!empty($existing_qr_codes)): ?>
    <div style="margin: 40px 0;">
        <h3 style="color: #2c3e50; margin-bottom: 20px;">📋 Existing QR Codes (<?php echo count($existing_qr_codes); ?>)</h3>
        
        <div style="background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); overflow: hidden;">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #f8f9fa;">
                        <tr>
                            <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Table</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Restaurant</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Scans</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Last Scanned</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Generated By</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($existing_qr_codes as $qr): ?>
                            <tr style="border-bottom: 1px solid #f0f0f0;">
                                <td style="padding: 12px; font-weight: bold; color: #e67e22;">Table <?php echo $qr['table_number']; ?></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($qr['restaurant_name']); ?></td>
                                <td style="padding: 12px;"><?php echo $qr['scan_count']; ?></td>
                                <td style="padding: 12px;">
                                    <?php echo $qr['last_scanned'] ? date('M j, g:i A', strtotime($qr['last_scanned'])) : 'Never'; ?>
                                </td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($qr['generated_by_name'] ?? 'Unknown'); ?></td>
                                <td style="padding: 12px;">
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <a href="<?php echo $qr['qr_url']; ?>" target="_blank" 
                                           class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px; text-decoration: none;">
                                            🔗 Test
                                        </a>
                                        <button onclick="regenerateQR(<?php echo $qr['table_number']; ?>)" 
                                                class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;">
                                            🔄 Regenerate
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Instructions Section -->
<div style="background: #f8f9fa; padding: 30px; border-radius: 8px; margin: 30px 0;">
    <h3 style="color: #2c3e50; margin-bottom: 20px;">📋 How to Use QR Codes</h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="font-size: 32px; margin-bottom: 10px;">🔧</div>
            <h4>1. Generate & Print</h4>
            <p style="font-size: 14px; color: #666;">Generate QR codes for your tables and print them. Place one QR code on each table.</p>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="font-size: 32px; margin-bottom: 10px;">📱</div>
            <h4>2. Customer Scans</h4>
            <p style="font-size: 14px; color: #666;">Customers scan with their phone camera or any QR scanner app to start ordering.</p>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="font-size: 32px; margin-bottom: 10px;">🛒</div>
            <h4>3. Auto-Detection</h4>
            <p style="font-size: 14px; color: #666;">Table number is automatically detected and set for dine-in orders.</p>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="font-size: 32px; margin-bottom: 10px;">🍽️</div>
            <h4>4. Order Delivery</h4>
            <p style="font-size: 14px; color: #666;">Orders are automatically assigned to the correct table for easy delivery.</p>
        </div>
    </div>
    
    <!-- Technical Details -->
    <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #3498db;">
        <h4 style="color: #3498db; margin-bottom: 15px;">🔗 QR Code URL Format:</h4>
        <code style="background: #ecf0f1; padding: 10px; border-radius: 5px; display: block; font-family: monospace; word-break: break-all;">
            <?php echo $base_domain; ?>/qr/scan.php?table=TABLE_NUMBER
        </code>
        <p style="margin: 10px 0 0 0; font-size: 14px; color: #666;">
            Each QR code contains a unique URL with the table number parameter pointing to your domain.
        </p>
    </div>
    
    <!-- Best Practices -->
    <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 20px 0;">
        <h4 style="color: #856404; margin-bottom: 10px;">💡 Best Practices:</h4>
        <ul style="margin: 0; padding-left: 20px; color: #856404;">
            <li>Print QR codes on waterproof material</li>
            <li>Place codes where they're easily visible but won't interfere with dining</li>
            <li>Test each QR code before placing on tables</li>
            <li>Keep backup printed codes in case of damage</li>
            <li>Consider laminating QR codes for durability</li>
            <li>Use higher error correction for outdoor or high-wear locations</li>
        </ul>
    </div>
</div>

<!-- Back Navigation -->
<div style="text-align: center; margin: 30px 0;">
    <a href="dashboard.php" class="btn btn-secondary">← Back to Staff Dashboard</a>
    <a href="../qr/demo.php" class="btn btn-secondary">📱 Test QR Demo</a>
    <button onclick="showQRCodeTips()" class="btn btn-secondary">💡 QR Code Tips</button>
</div>

<script>
// Form validation and helpers
function updateTableCount() {
    const startTable = parseInt(document.getElementById('start_table').value) || 0;
    const endTable = parseInt(document.getElementById('end_table').value) || 0;
    const count = Math.max(0, endTable - startTable + 1);
    
    document.getElementById('table-count').textContent = count;
    
    const info = document.getElementById('table-count-info');
    if (count > 50) {
        info.style.color = '#e74c3c';
        info.innerHTML = '⚠️ Too many tables! Maximum is 50 at once.';
    } else if (count > 0) {
        info.style.color = '#27ae60';
        info.innerHTML = `📊 Will generate <span id="table-count">${count}</span> QR codes`;
    } else {
        info.style.color = '#666';
        info.innerHTML = '📊 Enter table range to see count';
    }
}

function validateBulkGeneration() {
    const startTable = parseInt(document.getElementById('start_table').value) || 0;
    const endTable = parseInt(document.getElementById('end_table').value) || 0;
    const count = endTable - startTable + 1;
    
    if (count > 50) {
        alert('Maximum 50 tables can be generated at once. Please reduce the range.');
        return false;
    }
    
    if (startTable < 1 || endTable < 1) {
        alert('Table numbers must be 1 or greater.');
        return false;
    }
    
    if (startTable > endTable) {
        alert('Start table must be less than or equal to end table.');
        return false;
    }
    
    return confirm(`Generate ${count} QR codes for tables ${startTable} to ${endTable}?`);
}

// Print functions
function printAllQRCodes() {
    const printWindow = window.open('', '_blank');
    const qrContainer = document.getElementById('qr-codes-container');
    
    if (!qrContainer) {
        alert('No QR codes to print!');
        return;
    }
    
    const qrCards = qrContainer.querySelectorAll('.qr-code-card');
    let printContent = '';
    
    qrCards.forEach(card => {
        printContent += `<div class="print-qr-card">${card.innerHTML}</div>`;
    });
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>QR Codes - Print All</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 0; 
                    padding: 20px; 
                    background: white;
                }
                .print-qr-card { 
                    border: 2px solid #000; 
                    border-radius: 8px; 
                    padding: 20px; 
                    margin: 15px; 
                    display: inline-block; 
                    width: 280px; 
                    vertical-align: top;
                    background: white;
                    text-align: center;
                    page-break-inside: avoid;
                }
                h4, h2 { margin: 5px 0; }
                h4 { color: #2c3e50; font-size: 16px; }
                h2 { color: #e67e22; font-size: 24px; }
                img { width: 180px; height: 180px; }
                p { margin: 8px 0; font-size: 12px; }
                button, .btn { display: none; }
                small { font-size: 10px; }
                @media print {
                    .print-qr-card { 
                        page-break-inside: avoid;
                        margin: 10px;
                    }
                    body { margin: 0; }
                }
                @page { margin: 0.5in; }
            </style>
        </head>
        <body>
            <div style="text-align: center; margin-bottom: 30px;">
                <h1>Restaurant QR Codes</h1>
                <p>Generated on ${new Date().toLocaleDateString()}</p>
                <p>Domain: <?php echo $base_domain; ?></p>
            </div>
            <div style="text-align: center;">
                ${printContent}
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.onload = function() {
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    };
}

function printSingleQR(tableNumber) {
    const card = document.querySelector(`[data-table="${tableNumber}"]`);
    
    if (!card) {
        alert('QR code not found!');
        return;
    }
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Table ${tableNumber} QR Code</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 0; 
                    padding: 40px; 
                    text-align: center; 
                    background: white;
                }
                .qr-card { 
                    border: 3px solid #000; 
                    border-radius: 12px; 
                    padding: 40px; 
                    max-width: 350px; 
                    margin: 0 auto; 
                    background: white;
                }
                h4 { margin: 0 0 10px 0; color: #2c3e50; font-size: 18px; }
                h2 { margin: 0 0 20px 0; color: #e67e22; font-size: 32px; }
                img { width: 250px; height: 250px; }
                p { margin: 15px 0; font-size: 14px; }
                button, .btn { display: none; }
                small { display: none; }
            </style>
        </head>
        <body>
            <div class="qr-card">${card.innerHTML}</div>
            <script>
                window.onload = function() {
                    window.print();
                    window.close();
                }
            </script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

function downloadSingleQR(imageUrl, tableNumber) {
    // Create a temporary link element
    const link = document.createElement('a');
    link.href = imageUrl;
    link.download = `table_${tableNumber}_qr_code.png`;
    
    // Attempt to download
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function downloadAllQRCodes() {
    const qrCards = document.querySelectorAll('.qr-code-card');
    if (qrCards.length === 0) {
        alert('No QR codes to download!');
        return;
    }
    
    qrCards.forEach((card, index) => {
        const tableNumber = card.dataset.table;
        const img = card.querySelector('img');
        if (img && img.src) {
            setTimeout(() => {
                downloadSingleQR(img.src, tableNumber);
            }, index * 500); // Delay to prevent browser blocking multiple downloads
        }
    });
    
    alert(`Downloading ${qrCards.length} QR codes. Please allow multiple downloads in your browser.`);
}

function previewPrintLayout() {
    const printWindow = window.open('', '_blank');
    const qrContainer = document.getElementById('qr-codes-container');
    
    if (!qrContainer) {
        alert('No QR codes to preview!');
        return;
    }
    
    const qrCards = qrContainer.querySelectorAll('.qr-code-card');
    let printContent = '';
    
    qrCards.forEach(card => {
        printContent += `<div class="preview-qr-card">${card.innerHTML}</div>`;
    });
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Print Preview - QR Codes</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 20px; 
                    background: #f5f5f5;
                }
                .preview-qr-card { 
                    border: 2px solid #000; 
                    border-radius: 8px; 
                    padding: 20px; 
                    margin: 15px; 
                    display: inline-block; 
                    width: 280px; 
                    vertical-align: top;
                    background: white;
                    text-align: center;
                }
                .header { 
                    text-align: center; 
                    margin-bottom: 30px; 
                    padding: 20px; 
                    background: white; 
                    border-radius: 8px;
                }
                h4, h2 { margin: 5px 0; }
                h4 { color: #2c3e50; font-size: 16px; }
                h2 { color: #e67e22; font-size: 24px; }
                img { width: 180px; height: 180px; }
                p { margin: 8px 0; font-size: 12px; }
                button, .btn { display: none; }
                small { font-size: 10px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Print Preview - QR Codes</h1>
                <p>Domain: <?php echo $base_domain; ?></p>
                <p>This shows how your QR codes will look when printed</p>
                <button onclick="window.print()" style="display: inline-block; padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer;">Print Now</button>
                <button onclick="window.close()" style="display: inline-block; padding: 10px 20px; background: #95a5a6; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">Close Preview</button>
            </div>
            <div style="text-align: center;">
                ${printContent}
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}

function regenerateQR(tableNumber) {
    if (confirm(`Regenerate QR code for Table ${tableNumber}?`)) {
        // Auto-fill the single QR form
        document.getElementById('table_number').value = tableNumber;
        document.getElementById('table_number').focus();
        
        // Scroll to form
        document.getElementById('table_number').scrollIntoView({ behavior: 'smooth' });
        
        // Highlight the form briefly
        const form = document.getElementById('table_number').closest('div');
        form.style.background = '#e8f5e8';
        form.style.border = '2px solid #27ae60';
        
        setTimeout(() => {
            form.style.background = '';
            form.style.border = '';
        }, 2000);
    }
}

function showQRCodeTips() {
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    `;
    
    modal.innerHTML = `
        <div style="background: white; padding: 30px; border-radius: 12px; max-width: 600px; max-height: 80%; overflow-y: auto; margin: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: #2c3e50; margin: 0;">💡 QR Code Best Practices</h3>
                <button onclick="this.closest('div').parentElement.remove()" style="background: none; border: none; font-size: 24px; cursor: pointer;">×</button>
            </div>
            
            <div style="space-y: 20px;">
                <div style="background: #e8f5e8; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <h4 style="color: #27ae60; margin-bottom: 10px;">✅ Recommended Settings</h4>
                    <ul style="margin: 0; padding-left: 20px; color: #2c3e50;">
                        <li><strong>Size:</strong> Medium (300x300) for table display</li>
                        <li><strong>Error Correction:</strong> Medium (15%) for normal use</li>
                        <li><strong>Error Correction:</strong> High (30%) for outdoor/high-wear areas</li>
                        <li><strong>Placement:</strong> Center of table, easily visible</li>
                    </ul>
                </div>
                
                <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <h4 style="color: #856404; margin-bottom: 10px;">🔧 Technical Tips</h4>
                    <ul style="margin: 0; padding-left: 20px; color: #856404;">
                        <li>Higher error correction = more damage tolerance</li>
                        <li>Larger size = easier scanning from distance</li>
                        <li>Test scan before final placement</li>
                        <li>Domain: <?php echo $base_domain; ?></li>
                    </ul>
                </div>
                
                <div style="background: #f8d7da; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <h4 style="color: #721c24; margin-bottom: 10px;">❌ Common Mistakes</h4>
                    <ul style="margin: 0; padding-left: 20px; color: #721c24;">
                        <li>QR codes too small to scan easily</li>
                        <li>Placing under glass (can cause glare)</li>
                        <li>Not testing before deployment</li>
                        <li>Using low error correction in high-wear areas</li>
                    </ul>
                </div>
                
                <div style="background: #cce7ff; padding: 15px; border-radius: 8px;">
                    <h4 style="color: #004085; margin-bottom: 10px;">📱 Customer Experience</h4>
                    <ul style="margin: 0; padding-left: 20px; color: #004085;">
                        <li>Most phones auto-detect QR codes with camera</li>
                        <li>Customers will be redirected to your ordering system</li>
                        <li>Table number is automatically detected</li>
                        <li>Orders will show the correct table number</li>
                    </ul>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <button onclick="this.closest('div').parentElement.remove()" class="btn">Got it!</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// Add hover effects to QR cards
document.addEventListener('DOMContentLoaded', function() {
    const qrCards = document.querySelectorAll('.qr-code-card');
    qrCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 8px 15px rgba(0,0,0,0.2)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
        });
    });
    
    // Initialize table count
    updateTableCount();
    
    // Auto-focus first input
    const firstInput = document.getElementById('table_number');
    if (firstInput) {
        firstInput.focus();
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + G to focus on single table input
    if ((e.ctrlKey || e.metaKey) && e.key === 'g') {
        e.preventDefault();
        document.getElementById('table_number').focus();
    }
    
    // Ctrl/Cmd + M to focus on multiple table start input
    if ((e.ctrlKey || e.metaKey) && e.key === 'm') {
        e.preventDefault();
        document.getElementById('start_table').focus();
    }
    
    // Ctrl/Cmd + P to print (if QR codes exist)
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        const qrContainer = document.getElementById('qr-codes-container');
        if (qrContainer && qrContainer.children.length > 0) {
            e.preventDefault();
            printAllQRCodes();
        }
    }
});

// Form auto-save functionality
function saveFormData() {
    const formData = {
        restaurant_name: document.getElementById('restaurant_name').value,
        qr_size: document.getElementById('qr_size').value,
        error_correction: document.getElementById('error_correction').value
    };
    localStorage.setItem('qr_generator_settings', JSON.stringify(formData));
}

function loadFormData() {
    const saved = localStorage.getItem('qr_generator_settings');
    if (saved) {
        const data = JSON.parse(saved);
        if (data.restaurant_name) document.getElementById('restaurant_name').value = data.restaurant_name;
        if (data.qr_size) document.getElementById('qr_size').value = data.qr_size;
        if (data.error_correction) document.getElementById('error_correction').value = data.error_correction;
        
        // Apply to multiple form too
        if (data.restaurant_name) document.getElementById('restaurant_name_multi').value = data.restaurant_name;
        if (data.qr_size) document.getElementById('qr_size_multi').value = data.qr_size;
        if (data.error_correction) document.getElementById('error_correction_multi').value = data.error_correction;
    }
}

// Load saved settings on page load
document.addEventListener('DOMContentLoaded', loadFormData);

// Save settings on form input
document.addEventListener('input', function(e) {
    if (e.target.closest('form')) {
        saveFormData();
    }
});

// Sync settings between single and multiple forms
document.getElementById('restaurant_name').addEventListener('input', function() {
    document.getElementById('restaurant_name_multi').value = this.value;
});

document.getElementById('restaurant_name_multi').addEventListener('input', function() {
    document.getElementById('restaurant_name').value = this.value;
});

// Auto-update multiple form settings when single form changes
['qr_size', 'error_correction'].forEach(field => {
    document.getElementById(field).addEventListener('change', function() {
        document.getElementById(field + '_multi').value = this.value;
    });
    
    document.getElementById(field + '_multi').addEventListener('change', function() {
        document.getElementById(field).value = this.value;
    });
});
</script>

<?php include '../includes/footer.php'; ?>