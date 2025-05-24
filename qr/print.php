<?php
// ==============================================
// FILE: qr/print.php - QR Code Print Layout
// ==============================================
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

// Get parameters from URL
$tables = $_GET['tables'] ?? '';
$restaurant_name = trim($_GET['name'] ?? 'QR Food Ordering');
$layout = $_GET['layout'] ?? 'cards'; // cards, labels, minimal
$size = $_GET['size'] ?? 'medium'; // small, medium, large
$include_instructions = $_GET['instructions'] ?? '1';

// Validate and parse table numbers
if (empty($tables)) {
    redirect('generate.php?error=no_tables');
}

// Parse table numbers from comma-separated string
$table_numbers = array_filter(array_map('intval', explode(',', $tables)));

if (empty($table_numbers) || count($table_numbers) > 50) {
    redirect('generate.php?error=invalid_tables');
}

// Sort table numbers
sort($table_numbers);

// Generate base URL
$base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/qr-food-ordering';

// Size configurations
$size_configs = [
    'small' => ['width' => 180, 'height' => 180, 'font_size' => '14px', 'title_size' => '20px'],
    'medium' => ['width' => 220, 'height' => 220, 'font_size' => '16px', 'title_size' => '24px'],
    'large' => ['width' => 280, 'height' => 280, 'font_size' => '18px', 'title_size' => '28px']
];

$config = $size_configs[$size] ?? $size_configs['medium'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print QR Codes - <?php echo htmlspecialchars($restaurant_name); ?></title>
    
    <style>
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            line-height: 1.4;
            color: #333;
            background: white;
        }
        
        /* Print Styles */
        @media print {
            body { 
                margin: 0; 
                padding: 0;
                background: white !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            
            .no-print { 
                display: none !important; 
            }
            
            .qr-card { 
                page-break-inside: avoid;
                break-inside: avoid;
            }
            
            .page-break {
                page-break-before: always;
            }
            
            @page {
                margin: 0.5in;
                size: auto;
            }
            
            /* Force background colors and borders to print */
            .qr-card {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
        }
        
        /* Screen Styles */
        @media screen {
            body {
                padding: 20px;
                background: #f5f5f5;
            }
        }
        
        /* Header Styles */
        .print-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .print-header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 8px;
        }
        
        .print-header h2 {
            color: #e67e22;
            font-size: 20px;
            margin-bottom: 15px;
        }
        
        .print-header .generation-info {
            color: #666;
            font-size: 14px;
        }
        
        /* Control Panel */
        .print-controls {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .print-controls h3 {
            margin-bottom: 15px;
            font-size: 22px;
        }
        
        .print-controls p {
            margin-bottom: 20px;
            font-size: 16px;
            opacity: 0.9;
        }
        
        .control-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin: 20px 0;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #27ae60;
            color: white;
        }
        
        .btn-primary:hover {
            background: #229954;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
        }
        
        .btn-secondary:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-info {
            background: #3498db;
            color: white;
        }
        
        /* Layout Options */
        .layout-options {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .layout-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        
        .layout-option {
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .layout-option.active {
            border-color: #3498db;
            background: #e8f4f8;
        }
        
        .layout-option:hover {
            border-color: #3498db;
        }
        
        /* QR Grid Layouts */
        .qr-grid {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Cards Layout */
        .qr-grid.cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        /* Labels Layout */
        .qr-grid.labels {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        
        /* Minimal Layout */
        .qr-grid.minimal {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
        
        /* QR Card Styles */
        .qr-card {
            background: white;
            border: 3px solid #2c3e50;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .qr-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .qr-card.small {
            padding: 15px;
            border-width: 2px;
        }
        
        .qr-card.large {
            padding: 35px;
            border-width: 4px;
        }
        
        /* Restaurant Header */
        .restaurant-header {
            border-bottom: 3px solid #e67e22;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .restaurant-name {
            font-size: <?php echo $config['font_size']; ?>;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .table-number {
            font-size: <?php echo $config['title_size']; ?>;
            font-weight: bold;
            color: #e67e22;
            margin: 15px 0;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        
        /* QR Code Image */
        .qr-image {
            width: <?php echo $config['width']; ?>px;
            height: <?php echo $config['height']; ?>px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            margin: 15px auto;
            display: block;
            background: white;
        }
        
        /* Instructions */
        .qr-instructions {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #3498db;
        }
        
        .scan-text {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .help-text {
            color: #666;
            font-size: 11px;
            line-height: 1.3;
        }
        
        /* URL Display */
        .qr-url {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 8px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 9px;
            color: #6c757d;
            word-break: break-all;
            line-height: 1.2;
        }
        
        /* Label Layout Specific */
        .qr-grid.labels .qr-card {
            padding: 15px;
            border-width: 2px;
        }
        
        .qr-grid.labels .qr-image {
            width: 120px;
            height: 120px;
        }
        
        .qr-grid.labels .restaurant-name {
            font-size: 12px;
        }
        
        .qr-grid.labels .table-number {
            font-size: 16px;
        }
        
        /* Minimal Layout Specific */
        .qr-grid.minimal .qr-card {
            padding: 10px;
            border-width: 1px;
        }
        
        .qr-grid.minimal .qr-image {
            width: 100px;
            height: 100px;
        }
        
        .qr-grid.minimal .restaurant-name {
            font-size: 10px;
        }
        
        .qr-grid.minimal .table-number {
            font-size: 14px;
        }
        
        .qr-grid.minimal .qr-instructions,
        .qr-grid.minimal .qr-url {
            display: none;
        }
        
        /* Setup Instructions */
        .setup-instructions {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin: 40px auto;
            max-width: 800px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .setup-instructions h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
            font-size: 24px;
        }
        
        .instruction-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin: 25px 0;
        }
        
        .instruction-item {
            text-align: center;
            padding: 20px;
            border-radius: 8px;
            background: #f8f9fa;
            border-left: 4px solid #3498db;
        }
        
        .instruction-item .icon {
            font-size: 32px;
            margin-bottom: 15px;
        }
        
        .instruction-item h4 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .instruction-item p {
            color: #666;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .tips-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        
        .tips-box h4 {
            color: #856404;
            margin-bottom: 15px;
        }
        
        .tips-list {
            color: #856404;
            font-size: 14px;
        }
        
        .tips-list li {
            margin: 8px 0;
        }
        
        /* Progress Indicator */
        .print-progress {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #2c3e50;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 1000;
            display: none;
        }
        
        /* Footer */
        .print-footer {
            text-align: center;
            margin: 40px 0 20px 0;
            padding: 20px;
            color: #666;
            font-size: 12px;
            border-top: 1px solid #dee2e6;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .qr-grid.cards {
                grid-template-columns: 1fr;
            }
            
            .qr-grid.labels {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .qr-grid.minimal {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .control-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 200px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Print Header -->
    <div class="print-header">
        <h1><?php echo htmlspecialchars($restaurant_name); ?></h1>
        <h2>QR Code Menu Access</h2>
        <div class="generation-info">
            <p><strong>Generated:</strong> <?php echo date('F j, Y \a\t g:i A'); ?></p>
            <p><strong>Tables:</strong> <?php echo implode(', ', $table_numbers); ?> (<?php echo count($table_numbers); ?> total)</p>
            <p><strong>Layout:</strong> <?php echo ucfirst($layout); ?> | <strong>Size:</strong> <?php echo ucfirst($size); ?></p>
        </div>
    </div>
    
    <!-- Print Controls (Hidden when printing) -->
    <div class="print-controls no-print">
        <h3>🖨️ Ready to Print <?php echo count($table_numbers); ?> QR Codes</h3>
        <p>
            Your QR codes are ready! Make sure your printer is connected and loaded with paper.
            <br>For best results, use high-quality paper or cardstock.
        </p>
        
        <div class="control-buttons">
            <button onclick="printQRCodes()" class="btn btn-primary">
                🖨️ Print QR Codes
            </button>
            <button onclick="showPrintPreview()" class="btn btn-info">
                👁️ Print Preview
            </button>
            <a href="generate.php" class="btn btn-secondary">
                ← Back to Generator
            </a>
            <button onclick="downloadPDF()" class="btn btn-secondary">
                📄 Save as PDF
            </button>
        </div>
        
        <!-- Layout Options -->
        <div class="layout-options">
            <h4>📐 Layout Options (Click to change):</h4>
            <div class="layout-grid">
                <div class="layout-option <?php echo $layout === 'cards' ? 'active' : ''; ?>" 
                     onclick="changeLayout('cards')">
                    <strong>Cards Layout</strong><br>
                    <small>Full details with instructions</small>
                </div>
                <div class="layout-option <?php echo $layout === 'labels' ? 'active' : ''; ?>" 
                     onclick="changeLayout('labels')">
                    <strong>Labels Layout</strong><br>
                    <small>Compact for label sheets</small>
                </div>
                <div class="layout-option <?php echo $layout === 'minimal' ? 'active' : ''; ?>" 
                     onclick="changeLayout('minimal')">
                    <strong>Minimal Layout</strong><br>
                    <small>QR code only, space-saving</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Progress Indicator -->
    <div id="printProgress" class="print-progress">
        <div id="progressText">Preparing to print...</div>
    </div>
    
    <!-- QR Codes Grid -->
    <div class="qr-grid <?php echo $layout; ?>" id="qrGrid">
        <?php foreach ($table_numbers as $index => $table_number): 
            $qr_url = $base_url . '/qr/scan.php?table=' . $table_number;
            $qr_image_url = 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . urlencode($qr_url) . '&choe=UTF-8&chld=M|0';
            
            // Add page break every 6 cards for better printing
            $page_break_class = ($index > 0 && $index % 6 === 0) ? ' page-break' : '';
        ?>
            <div class="qr-card <?php echo $size . $page_break_class; ?>" data-table="<?php echo $table_number; ?>">
                
                <?php if ($layout !== 'minimal'): ?>
                <!-- Restaurant Header -->
                <div class="restaurant-header">
                    <div class="restaurant-name"><?php echo htmlspecialchars($restaurant_name); ?></div>
                </div>
                <?php endif; ?>
                
                <!-- Table Number -->
                <div class="table-number">Table <?php echo $table_number; ?></div>
                
                <!-- QR Code Image -->
                <img src="<?php echo $qr_image_url; ?>" 
                     alt="QR Code for Table <?php echo $table_number; ?>" 
                     class="qr-image"
                     loading="lazy"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                
                <!-- Fallback for failed QR images -->
                <div style="display: none; width: <?php echo $config['width']; ?>px; height: <?php echo $config['height']; ?>px; 
                            border: 2px dashed #bdc3c7; border-radius: 8px; margin: 15px auto;
                            display: flex; align-items: center; justify-content: center; color: #7f8c8d; flex-direction: column;">
                    <div style="font-size: 24px;">📱</div>
                    <div>QR Code</div>
                    <div style="font-size: 12px;">Table <?php echo $table_number; ?></div>
                </div>
                
                <?php if ($layout !== 'minimal'): ?>
                <!-- Instructions -->
                <div class="qr-instructions">
                    <div class="scan-text">📱 Scan to Order</div>
                    <div class="help-text">
                        Point your phone camera at this code<br>
                        No app needed - works automatically<br>
                        Your table number will be detected
                    </div>
                </div>
                
                <?php if ($layout === 'cards'): ?>
                <!-- URL Display -->
                <div class="qr-url"><?php echo $qr_url; ?></div>
                <?php endif; ?>
                
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Setup Instructions (Hidden when printing small layouts) -->
    <?php if ($include_instructions === '1'): ?>
    <div class="setup-instructions no-print">
        <h3>📋 Setup Instructions</h3>
        
        <div class="instruction-grid">
            <div class="instruction-item">
                <div class="icon">✂️</div>
                <h4>1. Cut & Prepare</h4>
                <p>Cut out each QR code leaving a small border. For durability, consider laminating the codes.</p>
            </div>
            
            <div class="instruction-item">
                <div class="icon">📱</div>
                <h4>2. Test Scanning</h4>
                <p>Test each QR code with your phone camera to ensure they work properly before placing on tables.</p>
            </div>
            
            <div class="instruction-item">
                <div class="icon">🍽️</div>
                <h4>3. Place on Tables</h4>
                <p>Position QR codes where customers can easily see and scan them - center of table works best.</p>
            </div>
            
            <div class="instruction-item">
                <div class="icon">👨‍🍳</div>
                <h4>4. Train Staff</h4>
                <p>Ensure all staff understand how the QR system works and can help customers if needed.</p>
            </div>
        </div>
        
        <div class="tips-box">
            <h4>💡 Pro Tips for Best Results:</h4>
            <ul class="tips-list">
                <li><strong>Paper Quality:</strong> Use thick paper or cardstock for durability</li>
                <li><strong>Lamination:</strong> Protects from spills and extends lifespan</li>
                <li><strong>Size:</strong> Minimum 2x2 inches for reliable scanning</li>
                <li><strong>Placement:</strong> Eye-level, good lighting, not under glass if possible</li>
                <li><strong>Backup:</strong> Keep extra printed codes for replacements</li>
                <li><strong>Testing:</strong> Try scanning in different lighting conditions</li>
                <li><strong>Instructions:</strong> Consider adding simple text like "Scan with phone camera"</li>
            </ul>
        </div>
        
        <div style="background: #e8f5e8; border: 1px solid #c3e6cb; border-radius: 8px; padding: 20px; margin: 20px 0;">
            <h4 style="color: #155724; margin-bottom: 10px;">✅ Customer Instructions to Share:</h4>
            <p style="color: #155724; font-style: italic; text-align: center; font-size: 16px; margin: 0;">
                "Point your phone camera at the QR code on your table. A notification will appear - tap it to start ordering. 
                No app download needed!"
            </p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Print Footer -->
    <div class="print-footer">
        <p>Generated by QR Food Ordering System | <?php echo date('Y'); ?> | For support, contact your system administrator</p>
    </div>
    
    <script>
        // Print Functions
        function printQRCodes() {
            showProgress('Preparing to print...');
            
            // Small delay to show progress
            setTimeout(() => {
                hideProgress();
                window.print();
            }, 500);
        }
        
        function showPrintPreview() {
            showProgress('Loading print preview...');
            
            setTimeout(() => {
                hideProgress();
                // Create print preview window
                const printWindow = window.open('', '_blank', 'width=1024,height=768');
                const currentContent = document.documentElement.outerHTML;
                
                printWindow.document.write(currentContent);
                printWindow.document.close();
                printWindow.focus();
            }, 300);
        }
        
        function downloadPDF() {
            showProgress('Preparing PDF download...');
            
            // Show instructions for PDF download
            setTimeout(() => {
                hideProgress();
                alert('To save as PDF:\n\n1. Click "Print QR Codes"\n2. Choose "Save as PDF" as printer\n3. Click Save\n\nThis will create a PDF file you can store or email.');
            }, 500);
        }
        
        function changeLayout(newLayout) {
            showProgress('Changing layout...');
            
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('layout', newLayout);
            
            setTimeout(() => {
                window.location.href = currentUrl.toString();
            }, 300);
        }
        
        // Progress Functions
        function showProgress(text) {
            const progress = document.getElementById('printProgress');
            const progressText = document.getElementById('progressText');
            progressText.textContent = text;
            progress.style.display = 'block';
        }
        
        function hideProgress() {
            document.getElementById('printProgress').style.display = 'none';
        }
        
        // Print Event Handlers
        window.addEventListener('beforeprint', function() {
            console.log('Printing QR codes for tables: <?php echo implode(", ", $table_numbers); ?>');
            showProgress('Printing...');
        });
        
        window.addEventListener('afterprint', function() {
            hideProgress();
            
            // Show completion message
            const completionMsg = document.createElement('div');
            completionMsg.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #27ae60;
                color: white;
                padding: 20px 30px;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                z-index: 1001;
                text-align: center;
                font-weight: bold;
            `;
            completionMsg.innerHTML = `
                <div style="font-size: 24px; margin-bottom: 10px;">✅</div>
                <div>Print job sent successfully!</div>
                <div style="font-size: 14px; margin-top: 10px; opacity: 0.9;">
                    Remember to test each QR code before placing on tables
                </div>
            `;
            
            document.body.appendChild(completionMsg);
            
            setTimeout(() => {
                completionMsg.remove();
            }, 3000);
        });
        
        // Keyboard Shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + P for print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                printQRCodes();
            }
            
            // Escape to go back
            if (e.key === 'Escape') {
                if (confirm('Go back to QR generator?')) {
                    window.location.href = 'generate.php';
                }
            }
        });
        
        // Auto-focus and page initialization
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to QR cards
            const qrCards = document.querySelectorAll('.qr-card');
            qrCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    if (window.matchMedia && window.matchMedia('(hover: hover)').matches) {
                        this.style.transform = 'translateY(-5px) scale(1.02)';
                    }
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
            
            // Check if all QR images loaded
            const qrImages = document.querySelectorAll('.qr-image');
            let loadedImages = 0;
            
            qrImages.forEach(img => {
                if (img.complete) {
                    loadedImages++;
                } else {
                    img.addEventListener('load', () => {
                        loadedImages++;
                        if (loadedImages === qrImages.length) {
                            console.log('All QR codes loaded successfully');
                        }
                    });
                    
                    img.addEventListener('error', () => {
                        console.warn('Failed to load QR code for table:', img.alt);
                    });
                }
            });
            
            // Auto-print option (uncomment if desired)
            // const urlParams = new URLSearchParams(window.location.search);
            // if (urlParams.get('autoprint') === '1') {
            //     setTimeout(printQRCodes, 1000);
            // }
            
            // Performance optimization - lazy load QR images
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            if (img.dataset.src) {
                                img.src = img.dataset.src;
                                img.removeAttribute('data-src');
                                observer.unobserve(img);
                            }
                        }
                    });
                });
                
                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            }
        });
        
        // Prevent accidental navigation
        let isPrinting = false;
        
        window.addEventListener('beforeunload', function(e) {
            if (!isPrinting) {
                e.preventDefault();
                e.returnValue = 'Are you sure you want to leave? Make sure you\'ve printed your QR codes first.';
                return e.returnValue;
            }
        });
        
        // Mark as printing when print starts
        window.addEventListener('beforeprint', function() {
            isPrinting = true;
        });
        
        window.addEventListener('afterprint', function() {
            setTimeout(() => {
                isPrinting = false;
            }, 1000);
        });
        
        // Quality check function
        function runQualityCheck() {
            const qrCards = document.querySelectorAll('.qr-card');
            let issues = [];
            
            qrCards.forEach((card, index) => {
                const img = card.querySelector('.qr-image');
                const tableNumber = card.dataset.table;
                
                // Check if image loaded
                if (!img.complete || img.naturalHeight === 0) {
                    issues.push(`Table ${tableNumber}: QR image failed to load`);
                }
                
                // Check if image is too small
                if (img.offsetWidth < 100) {
                    issues.push(`Table ${tableNumber}: QR code might be too small for reliable scanning`);
                }
            });
            
            if (issues.length > 0) {
                alert('Quality Check Issues Found:\n\n' + issues.join('\n') + '\n\nPlease review before printing.');
            } else {
                alert('✅ Quality Check Passed!\n\nAll QR codes are ready for printing.');
            }
        }
        
        // Add quality check button
        document.addEventListener('DOMContentLoaded', function() {
            const controlButtons = document.querySelector('.control-buttons');
            if (controlButtons) {
                const qualityBtn = document.createElement('button');
                qualityBtn.className = 'btn btn-info';
                qualityBtn.innerHTML = '🔍 Quality Check';
                qualityBtn.onclick = runQualityCheck;
                controlButtons.appendChild(qualityBtn);
            }
        });
        
        // Print statistics tracking
        let printAttempts = 0;
        
        function trackPrintUsage() {
            printAttempts++;
            
            // Store in sessionStorage for this session
            sessionStorage.setItem('qr_print_attempts', printAttempts);
            sessionStorage.setItem('qr_tables_printed', JSON.stringify(<?php echo json_encode($table_numbers); ?>));
            sessionStorage.setItem('qr_restaurant_name', '<?php echo addslashes($restaurant_name); ?>');
            
            console.log('Print tracking:', {
                attempts: printAttempts,
                tables: <?php echo json_encode($table_numbers); ?>,
                timestamp: new Date().toISOString()
            });
        }
        
        // Enhanced print function with tracking
        const originalPrint = printQRCodes;
        printQRCodes = function() {
            trackPrintUsage();
            originalPrint();
        };
        
        // Accessibility improvements
        document.addEventListener('DOMContentLoaded', function() {
            // Add ARIA labels
            document.querySelectorAll('.qr-image').forEach(img => {
                const tableNumber = img.closest('.qr-card').dataset.table;
                img.setAttribute('aria-label', `QR code for table ${tableNumber}`);
            });
            
            // Add keyboard navigation for layout options
            document.querySelectorAll('.layout-option').forEach(option => {
                option.setAttribute('tabindex', '0');
                option.setAttribute('role', 'button');
                
                option.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.click();
                    }
                });
            });
        });
        
        // Advanced print options
        function showAdvancedPrintOptions() {
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
                z-index: 1002;
            `;
            
            modal.innerHTML = `
                <div style="background: white; padding: 30px; border-radius: 12px; max-width: 500px; width: 90%;">
                    <h3 style="margin-bottom: 20px; color: #2c3e50;">🖨️ Advanced Print Options</h3>
                    
                    <div style="margin: 15px 0;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Print Quality:</label>
                        <select id="printQuality" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="normal">Normal (Faster)</option>
                            <option value="high" selected>High Quality (Recommended)</option>
                            <option value="best">Best Quality (Slower)</option>
                        </select>
                    </div>
                    
                    <div style="margin: 15px 0;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Paper Size:</label>
                        <select id="paperSize" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="a4">A4 (210 x 297 mm)</option>
                            <option value="letter" selected>Letter (8.5 x 11 in)</option>
                            <option value="legal">Legal (8.5 x 14 in)</option>
                        </select>
                    </div>
                    
                    <div style="margin: 15px 0;">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" id="printBorders" checked>
                            <span>Print borders around QR codes</span>
                        </label>
                    </div>
                    
                    <div style="margin: 15px 0;">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" id="printInstructions" checked>
                            <span>Include scanning instructions</span>
                        </label>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 25px;">
                        <button onclick="applyAdvancedPrint()" class="btn btn-primary" style="flex: 1;">
                            🖨️ Print with Options
                        </button>
                        <button onclick="closeAdvancedOptions()" class="btn btn-secondary">
                            Cancel
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            window.currentModal = modal;
        }
        
        function closeAdvancedOptions() {
            if (window.currentModal) {
                window.currentModal.remove();
                window.currentModal = null;
            }
        }
        
        function applyAdvancedPrint() {
            const quality = document.getElementById('printQuality').value;
            const paperSize = document.getElementById('paperSize').value;
            const printBorders = document.getElementById('printBorders').checked;
            const printInstructions = document.getElementById('printInstructions').checked;
            
            // Apply settings
            if (!printBorders) {
                document.querySelectorAll('.qr-card').forEach(card => {
                    card.style.border = 'none';
                });
            }
            
            if (!printInstructions) {
                document.querySelectorAll('.qr-instructions').forEach(inst => {
                    inst.style.display = 'none';
                });
            }
            
            // Close modal and print
            closeAdvancedOptions();
            
            showProgress(`Printing with ${quality} quality on ${paperSize.toUpperCase()} paper...`);
            setTimeout(() => {
                hideProgress();
                window.print();
                
                // Restore settings after print
                setTimeout(() => {
                    if (!printBorders) {
                        document.querySelectorAll('.qr-card').forEach(card => {
                            card.style.border = '';
                        });
                    }
                    
                    if (!printInstructions) {
                        document.querySelectorAll('.qr-instructions').forEach(inst => {
                            inst.style.display = '';
                        });
                    }
                }, 1000);
            }, 500);
        }
        
        // Add advanced print button
        document.addEventListener('DOMContentLoaded', function() {
            const controlButtons = document.querySelector('.control-buttons');
            if (controlButtons) {
                const advancedBtn = document.createElement('button');
                advancedBtn.className = 'btn btn-secondary';
                advancedBtn.innerHTML = '⚙️ Advanced Print';
                advancedBtn.onclick = showAdvancedPrintOptions;
                controlButtons.appendChild(advancedBtn);
            }
        });
    </script>
</body>
</html>