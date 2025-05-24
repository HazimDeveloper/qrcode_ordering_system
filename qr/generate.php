<?php
// ==============================================
// FILE: qr/generate.php - QR Code Generator
// ==============================================
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';
$qr_codes = [];

// Generate QR Code for single table
if ($_POST && isset($_POST['generate_qr'])) {
    $table_number = (int)$_POST['table_number'];
    $restaurant_name = trim($_POST['restaurant_name']) ?: 'QR Food Ordering';
    
    if ($table_number > 0 && $table_number <= 999) {
        // Create QR code URL - points to the ordering page with table number
        $base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/qr-food-ordering';
        $qr_url = $base_url . '/qr/scan.php?table=' . $table_number;
        
        // Generate QR code using Google Charts API
        $qr_image_url = 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . urlencode($qr_url) . '&choe=UTF-8';
        
        $qr_codes[] = [
            'table_number' => $table_number,
            'url' => $qr_url,
            'image_url' => $qr_image_url,
            'restaurant_name' => $restaurant_name,
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
        // Save to database (optional)
        try {
            $stmt = $pdo->prepare("INSERT INTO qr_codes (table_number, qr_url, generated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE qr_url = VALUES(qr_url), generated_by = VALUES(generated_by), generated_at = CURRENT_TIMESTAMP");
            $stmt->execute([$table_number, $qr_url, $_SESSION['user_id']]);
        } catch (Exception $e) {
            // Continue without database logging if table doesn't exist
        }
        
        $message = "QR Code generated successfully for Table $table_number!";
    } else {
        $error = 'Please enter a valid table number (1-999)';
    }
}

// Generate multiple QR codes
if ($_POST && isset($_POST['generate_multiple'])) {
    $start_table = (int)$_POST['start_table'];
    $end_table = (int)$_POST['end_table'];
    $restaurant_name = trim($_POST['restaurant_name']) ?: 'QR Food Ordering';
    
    if ($start_table > 0 && $end_table >= $start_table && $start_table <= 999 && $end_table <= 999) {
        $table_count = $end_table - $start_table + 1;
        
        if ($table_count <= 50) {
            $base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/qr-food-ordering';
            
            for ($i = $start_table; $i <= $end_table; $i++) {
                $qr_url = $base_url . '/qr/scan.php?table=' . $i;
                $qr_image_url = 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . urlencode($qr_url) . '&choe=UTF-8';
                
                $qr_codes[] = [
                    'table_number' => $i,
                    'url' => $qr_url,
                    'image_url' => $qr_image_url,
                    'restaurant_name' => $restaurant_name,
                    'generated_at' => date('Y-m-d H:i:s')
                ];
                
                // Save to database (optional)
                try {
                    $stmt = $pdo->prepare("INSERT INTO qr_codes (table_number, qr_url, generated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE qr_url = VALUES(qr_url), generated_by = VALUES(generated_by), generated_at = CURRENT_TIMESTAMP");
                    $stmt->execute([$i, $qr_url, $_SESSION['user_id']]);
                } catch (Exception $e) {
                    // Continue without database logging
                }
            }
            
            $message = "Generated $table_count QR codes for tables $start_table to $end_table!";
        } else {
            $error = 'Maximum 50 tables can be generated at once';
        }
    } else {
        $error = 'Please enter valid table numbers (1-999, start must be less than or equal to end)';
    }
}

$page_title = 'QR Code Generator';
include '../includes/header.php';
?>

<h1>🔳 QR Code Generator</h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<!-- Generator Forms -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 20px 0;">
    <!-- Single QR Code Generator -->
    <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h3 style="color: #2c3e50; margin-bottom: 20px;">📱 Generate Single QR Code</h3>
        <form method="POST">
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

<!-- Generated QR Codes Display -->
<?php if (!empty($qr_codes)): ?>
    <div style="margin: 30px 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
            <h3 style="color: #2c3e50;">Generated QR Codes (<?php echo count($qr_codes); ?>)</h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button onclick="printAllQRCodes()" class="btn">🖨️ Print All</button>
                <button onclick="downloadAllQRCodes()" class="btn btn-secondary">📥 Download Instructions</button>
                <button onclick="previewPrintLayout()" class="btn btn-secondary">👁️ Preview Print</button>
            </div>
        </div>
        
        <div id="qr-codes-container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            <?php foreach ($qr_codes as $index => $qr): ?>
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
                             style="width: 200px; height: 200px; border: 2px solid #ecf0f1; border-radius: 8px;"
                             loading="lazy"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        
                        <!-- Fallback if image fails to load -->
                        <div style="display: none; width: 200px; height: 200px; border: 2px dashed #bdc3c7; border-radius: 8px; 
                                    display: flex; align-items: center; justify-content: center; color: #7f8c8d; flex-direction: column;">
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
                    
                    <!-- URL Display -->
                    <div style="background: #ecf0f1; padding: 10px; border-radius: 5px; margin: 10px 0;">
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
            <?php 
            $base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/qr-food-ordering';
            echo $base_url . '/qr/scan.php?table=TABLE_NUMBER'; 
            ?>
        </code>
        <p style="margin: 10px 0 0 0; font-size: 14px; color: #666;">
            Each QR code contains a unique URL with the table number parameter.
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
        </ul>
    </div>
</div>

<!-- Back Navigation -->
<div style="text-align: center; margin: 30px 0;">
    <a href="../staff/dashboard.php" class="btn btn-secondary">← Back to Staff Dashboard</a>
    <a href="demo.php" class="btn btn-secondary">📱 Test QR Demo</a>
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
    alert(`To download all QR codes:\n\n1. Use the individual "Download" buttons below each QR code\n2. Or right-click on each QR code image and select "Save image as..."\n3. For bulk download, consider using the print function and print to PDF\n\nEach QR code will be saved as table_X_qr_code.png`);
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
});
</script>

<?php include '../includes/footer.php'; ?>