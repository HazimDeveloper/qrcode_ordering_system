<?php
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';

// Handle image upload
function handleImageUpload($file) {
    $upload_dir = '../images/';
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Check if directory exists, create if not
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload failed with error code: ' . $file['error']);
    }
    
    if ($file['size'] > $max_size) {
        throw new Exception('File size too large. Maximum 5MB allowed.');
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.');
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'menu_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to move uploaded file.');
    }
    
    return $filename;
}

// Add new item
if ($_POST && isset($_POST['add_item'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $category = trim($_POST['category']);
    $image_filename = '';
    
    if (empty($name) || empty($description) || $price <= 0) {
        $error = 'Name, description are required and price must be greater than 0';
    } else {
        try {
            // Handle image upload
            if (isset($_FILES['image_upload']) && $_FILES['image_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
                $image_filename = handleImageUpload($_FILES['image_upload']);
            } elseif (!empty($_POST['image_url'])) {
                // If URL provided instead of upload, validate it
                $image_url = trim($_POST['image_url']);
                if (filter_var($image_url, FILTER_VALIDATE_URL)) {
                    // For URL, we'll store the full URL in the database
                    $image_filename = $image_url;
                } else {
                    $error = 'Invalid image URL provided';
                }
            }
            
            if (!$error) {
                // Check if item name already exists
                $stmt = $pdo->prepare("SELECT id FROM menu_items WHERE name = ?");
                $stmt->execute([$name]);
                if ($stmt->fetch()) {
                    $error = 'An item with this name already exists';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO menu_items (name, description, price, category, image, available) VALUES (?, ?, ?, ?, ?, 1)");
                    if ($stmt->execute([$name, $description, $price, $category, $image_filename])) {
                        $message = 'Menu item added successfully!';
                    } else {
                        $error = 'Failed to add menu item';
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Update item
if ($_POST && isset($_POST['update_item'])) {
    $id = (int)$_POST['item_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $category = trim($_POST['category']);
    $current_image = $_POST['current_image'] ?? '';
    $image_filename = $current_image; // Keep current image by default
    
    if (empty($name) || empty($description) || $price <= 0) {
        $error = 'Name, description are required and price must be greater than 0';
    } else {
        try {
            // Handle image upload for update
            if (isset($_FILES['edit_image_upload']) && $_FILES['edit_image_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Delete old image if it's a local file (not URL)
                if ($current_image && !filter_var($current_image, FILTER_VALIDATE_URL) && file_exists('../images/' . $current_image)) {
                    unlink('../images/' . $current_image);
                }
                $image_filename = handleImageUpload($_FILES['edit_image_upload']);
            } elseif (!empty($_POST['image_url']) && $_POST['image_url'] !== $current_image) {
                // New URL provided
                $image_url = trim($_POST['image_url']);
                if (filter_var($image_url, FILTER_VALIDATE_URL)) {
                    // Delete old local image if exists
                    if ($current_image && !filter_var($current_image, FILTER_VALIDATE_URL) && file_exists('../images/' . $current_image)) {
                        unlink('../images/' . $current_image);
                    }
                    $image_filename = $image_url;
                } else {
                    $error = 'Invalid image URL provided';
                }
            }
            
            if (!$error) {
                // Check if another item has the same name
                $stmt = $pdo->prepare("SELECT id FROM menu_items WHERE name = ? AND id != ?");
                $stmt->execute([$name, $id]);
                if ($stmt->fetch()) {
                    $error = 'Another item with this name already exists';
                } else {
                    $stmt = $pdo->prepare("UPDATE menu_items SET name = ?, description = ?, price = ?, category = ?, image = ? WHERE id = ?");
                    if ($stmt->execute([$name, $description, $price, $category, $image_filename, $id])) {
                        $message = 'Menu item updated successfully!';
                    } else {
                        $error = 'Failed to update menu item';
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Delete item
if ($_GET['delete'] ?? false) {
    $id = (int)$_GET['delete'];
    
    // Check if item is in any pending orders
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM order_items oi 
        JOIN orders o ON oi.order_id = o.id 
        WHERE oi.menu_item_id = ? AND o.status IN ('pending', 'confirmed', 'preparing')
    ");
    $stmt->execute([$id]);
    $pending_orders = $stmt->fetchColumn();
    
    if ($pending_orders > 0) {
        $error = "Cannot delete this item as it's part of $pending_orders pending order(s)";
    } else {
        // Get the image filename before deleting
        $stmt = $pdo->prepare("SELECT image FROM menu_items WHERE id = ?");
        $stmt->execute([$id]);
        $item_image = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
        if ($stmt->execute([$id])) {
            // Delete the image file if it's a local file
            if ($item_image && !filter_var($item_image, FILTER_VALIDATE_URL) && file_exists('../images/' . $item_image)) {
                unlink('../images/' . $item_image);
            }
            $message = 'Menu item deleted successfully!';
        } else {
            $error = 'Failed to delete menu item';
        }
    }
}

// Toggle item availability - FIXED
if ($_GET['toggle'] ?? false) {
    $id = (int)$_GET['toggle'];
    
    try {
        // First get current availability status
        $stmt = $pdo->prepare("SELECT available FROM menu_items WHERE id = ?");
        $stmt->execute([$id]);
        $current_status = $stmt->fetchColumn();
        
        if ($current_status !== false) {
            // Toggle the status (convert to boolean first, then flip)
            $new_status = $current_status ? 0 : 1;
            
            $stmt = $pdo->prepare("UPDATE menu_items SET available = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $id])) {
                $status_text = $new_status ? 'available' : 'unavailable';
                $message = "Item is now $status_text!";
            } else {
                $error = 'Failed to update item availability';
            }
        } else {
            $error = 'Item not found';
        }
    } catch (Exception $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Get search and filter parameters
$search = trim($_GET['search'] ?? '');
$category_filter = $_GET['category'] ?? '';
$availability_filter = $_GET['availability'] ?? '';
$sort_by = $_GET['sort'] ?? 'name';
$sort_order = $_GET['order'] ?? 'ASC';

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
}

if ($availability_filter !== '') {
    $where_conditions[] = "available = ?";
    $params[] = (int)$availability_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Validate sort parameters
$valid_sorts = ['name', 'price', 'category', 'created_at', 'available'];
$sort_by = in_array($sort_by, $valid_sorts) ? $sort_by : 'name';
$sort_order = ($sort_order === 'DESC') ? 'DESC' : 'ASC';

// Get items with pagination
$page = (int)($_GET['page'] ?? 1);
$limit = 12;
$offset = ($page - 1) * $limit;

// Count total items
$count_sql = "SELECT COUNT(*) FROM menu_items $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_items = $stmt->fetchColumn();
$total_pages = ceil($total_items / $limit);

// Get items
$sql = "SELECT * FROM menu_items $where_clause ORDER BY $sort_by $sort_order LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Get categories for filter dropdown
$stmt = $pdo->query("SELECT DISTINCT category FROM menu_items WHERE category IS NOT NULL AND category != '' ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total_items,
        COUNT(CASE WHEN available = 1 THEN 1 END) as available_items,
        COUNT(CASE WHEN available = 0 THEN 1 END) as unavailable_items,
        AVG(price) as avg_price,
        MAX(price) as max_price,
        MIN(price) as min_price
    FROM menu_items
";
$stmt = $pdo->query($stats_sql);
$stats = $stmt->fetch();

include '../includes/header.php';
?>

<h1>Manage Menu Items</h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- Statistics Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0;">
    <div style="background: #3498db; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo $stats['total_items']; ?></h3>
        <p>Total Items</p>
    </div>
    <div style="background: #27ae60; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo $stats['available_items']; ?></h3>
        <p>Available</p>
    </div>
    <div style="background: #e74c3c; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo $stats['unavailable_items']; ?></h3>
        <p>Unavailable</p>
    </div>
    <div style="background: #f39c12; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3>RM <?php echo number_format($stats['avg_price'], 2); ?></h3>
        <p>Avg Price</p>
    </div>
    <div style="background: #9b59b6; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3>RM <?php echo number_format($stats['max_price'], 2); ?></h3>
        <p>Highest Price</p>
    </div>
    <div style="background: #95a5a6; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3>RM <?php echo number_format($stats['min_price'], 2); ?></h3>
        <p>Lowest Price</p>
    </div>
</div>

<!-- Search and Filter Form -->
<div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin: 20px 0;">
    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: end;">
        <div>
            <label for="search" style="display: block; margin-bottom: 5px; font-weight: bold;">Search Items:</label>
            <input type="text" name="search" id="search" placeholder="Search by name or description" 
                   value="<?php echo htmlspecialchars($search); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <div>
            <label for="category" style="display: block; margin-bottom: 5px; font-weight: bold;">Category:</label>
            <select name="category" id="category" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter === $cat ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label for="availability" style="display: block; margin-bottom: 5px; font-weight: bold;">Availability:</label>
            <select name="availability" id="availability" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="">All Items</option>
                <option value="1" <?php echo $availability_filter === '1' ? 'selected' : ''; ?>>Available Only</option>
                <option value="0" <?php echo $availability_filter === '0' ? 'selected' : ''; ?>>Unavailable Only</option>
            </select>
        </div>
        
        <div>
            <label for="sort" style="display: block; margin-bottom: 5px; font-weight: bold;">Sort By:</label>
            <select name="sort" id="sort" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name</option>
                <option value="price" <?php echo $sort_by === 'price' ? 'selected' : ''; ?>>Price</option>
                <option value="category" <?php echo $sort_by === 'category' ? 'selected' : ''; ?>>Category</option>
                <option value="available" <?php echo $sort_by === 'available' ? 'selected' : ''; ?>>Availability</option>
                <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date Added</option>
            </select>
        </div>
        
        <div>
            <label for="order" style="display: block; margin-bottom: 5px; font-weight: bold;">Order:</label>
            <select name="order" id="order" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Descending</option>
            </select>
        </div>
        
        <div>
            <button type="submit" class="btn">Search & Filter</button>
        </div>
        
        <div>
            <a href="manage_items.php" class="btn btn-secondary">Clear Filters</a>
        </div>
    </form>
</div>

<!-- Quick Actions -->
<div style="display: flex; justify-content: space-between; align-items: center; margin: 20px 0;">
    <div>
        <button onclick="showAddForm()" class="btn">+ Add New Item</button>
        <button onclick="exportItems()" class="btn btn-secondary">📄 Export Items</button>
    </div>
    <div style="font-size: 14px; color: #666;">
        Showing <?php echo count($items); ?> of <?php echo $total_items; ?> items
    </div>
</div>

<!-- Add New Item Form (Hidden by default) -->
<div id="addItemForm" style="display: none; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin: 20px 0;">
    <h3 style="margin-bottom: 20px;">Add New Menu Item</h3>
    <form method="POST" enctype="multipart/form-data" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        <div class="form-group">
            <label for="add_name">Item Name: *</label>
            <input type="text" name="name" id="add_name" required maxlength="100">
        </div>
        
        <div class="form-group">
            <label for="add_category">Category:</label>
            <input type="text" name="category" id="add_category" placeholder="e.g., Main Course, Beverages" maxlength="50">
        </div>
        
        <div class="form-group">
            <label for="add_price">Price (RM): *</label>
            <input type="number" name="price" id="add_price" step="0.01" min="0.01" required>
        </div>
        
        <div class="form-group">
            <label for="add_image_upload">Upload Image:</label>
            <input type="file" name="image_upload" id="add_image_upload" accept="image/*" onchange="previewImage(this, 'add_preview')">
            <small style="color: #666; display: block; margin-top: 5px;">Max 5MB. JPEG, PNG, GIF, WebP allowed.</small>
            <div id="add_preview" style="margin-top: 10px;"></div>
        </div>
        
        <div class="form-group">
            <label for="add_image_url">OR Image URL:</label>
            <input type="url" name="image_url" id="add_image_url" placeholder="https://example.com/image.jpg">
            <small style="color: #666; display: block; margin-top: 5px;">Use either upload or URL, not both.</small>
        </div>
        
        <div class="form-group" style="grid-column: 1 / -1;">
            <label for="add_description">Description: *</label>
            <textarea name="description" id="add_description" rows="3" required maxlength="500" placeholder="Describe the item..."></textarea>
        </div>
        
        <div style="grid-column: 1 / -1; display: flex; gap: 10px;">
            <button type="submit" name="add_item" class="btn">Add Item</button>
            <button type="button" onclick="hideAddForm()" class="btn btn-secondary">Cancel</button>
        </div>
    </form>
</div>

<!-- Items Grid -->
<?php if (empty($items)): ?>
    <div style="text-align: center; padding: 50px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h3>No items found</h3>
        <p>No items match your search criteria.</p>
        <?php if (!empty($search) || !empty($category_filter) || $availability_filter !== ''): ?>
            <a href="manage_items.php" class="btn">Clear Filters</a>
        <?php else: ?>
            <button onclick="showAddForm()" class="btn">Add Your First Item</button>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="menu-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <?php foreach ($items as $item): ?>
            <div class="menu-item" style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); position: relative; opacity: <?php echo $item['available'] ? '1' : '0.7'; ?>;">
                <!-- Availability Badge -->
                <?php if (!$item['available']): ?>
                    <div style="position: absolute; top: 10px; right: 10px; background: #e74c3c; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px; z-index: 1; font-weight: bold;">
                        Unavailable
                    </div>
                <?php else: ?>
                    <div style="position: absolute; top: 10px; right: 10px; background: #27ae60; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px; z-index: 1; font-weight: bold;">
                        Available
                    </div>
                <?php endif; ?>
                
                <!-- Item Image -->
                <div style="height: 200px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                    <?php if (!empty($item['image'])): ?>
                        <?php
                        // Determine image source (URL or local file)
                        $image_src = filter_var($item['image'], FILTER_VALIDATE_URL) 
                            ? $item['image'] 
                            : '../images/' . $item['image'];
                        ?>
                        <img src="<?php echo htmlspecialchars($image_src); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                             style="width: 100%; height: 100%; object-fit: cover;"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div style="display: none; width: 100%; height: 100%; background: #ecf0f1; align-items: center; justify-content: center; color: #7f8c8d;">
                            📷 Image Not Found
                        </div>
                    <?php else: ?>
                        <div style="width: 100%; height: 100%; background: #ecf0f1; display: flex; align-items: center; justify-content: center; color: #7f8c8d;">
                            📷 No Image
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Item Content -->
                <div style="padding: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                        <h4 style="margin: 0; color: #2c3e50;"><?php echo htmlspecialchars($item['name']); ?></h4>
                        <?php if (!empty($item['category'])): ?>
                            <span style="background: #3498db; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px;">
                                <?php echo htmlspecialchars($item['category']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <p style="color: #666; margin: 10px 0; font-size: 14px; line-height: 1.4;">
                        <?php echo htmlspecialchars($item['description']); ?>
                    </p>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin: 15px 0;">
                        <div class="price" style="font-size: 18px; font-weight: bold; color: #e67e22;">
                            RM <?php echo number_format($item['price'], 2); ?>
                        </div>
                        <div style="font-size: 12px; color: #95a5a6;">
                            Added: <?php echo date('M j, Y', strtotime($item['created_at'])); ?>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-top: 15px;">
                        <button onclick="editItem(
                            <?php echo $item['id']; ?>, 
                            '<?php echo addslashes($item['name']); ?>', 
                            '<?php echo addslashes($item['description']); ?>', 
                            <?php echo $item['price']; ?>,
                            '<?php echo addslashes($item['category'] ?? ''); ?>',
                            '<?php echo addslashes($item['image'] ?? ''); ?>'
                        )" class="btn btn-secondary" style="padding: 8px; font-size: 12px;">
                            ✏️ Edit
                        </button>
                        
                        <a href="?toggle=<?php echo $item['id']; ?>" 
                           class="btn" style="padding: 8px; font-size: 12px; text-decoration: none; text-align: center; 
                           background: <?php echo $item['available'] ? '#f39c12' : '#27ae60'; ?>;">
                            <?php echo $item['available'] ? '⏸️ Disable' : '✅ Enable'; ?>
                        </a>
                        
                        <a href="?delete=<?php echo $item['id']; ?>" 
                           onclick="return confirm('Are you sure you want to delete this item? This action cannot be undone.')" 
                           class="btn" style="padding: 8px; font-size: 12px; background: #e74c3c; text-decoration: none; text-align: center;">
                            🗑️ Delete
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div style="display: flex; justify-content: center; align-items: center; margin: 30px 0; gap: 10px;">
            <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn btn-secondary">← Previous</a>
            <?php endif; ?>
            
            <span style="margin: 0 15px;">
                Page <?php echo $page; ?> of <?php echo $total_pages; ?> 
                (<?php echo $total_items; ?> total items)
            </span>
            
            <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn btn-secondary">Next →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Edit Item Modal -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; width: 90%; max-width: 700px; max-height: 80%; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Edit Menu Item</h3>
            <button onclick="closeEditModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">×</button>
        </div>
        
        <form method="POST" enctype="multipart/form-data" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <input type="hidden" name="item_id" id="edit_id">
            <input type="hidden" name="current_image" id="edit_current_image">
            
            <div class="form-group">
                <label for="edit_name">Item Name: *</label>
                <input type="text" name="name" id="edit_name" required maxlength="100">
            </div>
            
            <div class="form-group">
                <label for="edit_category">Category:</label>
                <input type="text" name="category" id="edit_category" maxlength="50">
            </div>
            
            <div class="form-group">
                <label for="edit_price">Price (RM): *</label>
                <input type="number" name="price" id="edit_price" step="0.01" min="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="edit_image_upload">Upload New Image:</label>
                <input type="file" name="edit_image_upload" id="edit_image_upload" accept="image/*" onchange="previewImage(this, 'edit_preview')">
                <small style="color: #666; display: block; margin-top: 5px;">Max 5MB. JPEG, PNG, GIF, WebP allowed.</small>
            </div>
            
            <div class="form-group">
                <label for="edit_image_url">OR Image URL:</label>
                <input type="url" name="image_url" id="edit_image_url" placeholder="https://example.com/image.jpg">
                <small style="color: #666; display: block; margin-top: 5px;">Use either upload or URL, not both.</small>
            </div>
            
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Current Image:</label>
                <div id="current_image_display" style="margin: 10px 0;"></div>
                <div id="edit_preview" style="margin: 10px 0;"></div>
            </div>
            
            <div class="form-group" style="grid-column: 1 / -1;">
                <label for="edit_description">Description: *</label>
                <textarea name="description" id="edit_description" rows="3" required maxlength="500"></textarea>
            </div>
            
            <div style="grid-column: 1 / -1; display: flex; gap: 10px;">
                <button type="submit" name="update_item" class="btn">Update Item</button>
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
                <button type="button" onclick="removeCurrentImage()" class="btn" style="background: #e74c3c;">Remove Image</button>
            </div>
        </form>
    </div>
</div>

<div style="text-align: center; margin: 30px 0;">
    <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    <button onclick="window.print()" class="btn">🖨️ Print Items</button>
</div>

<script>
function showAddForm() {
    document.getElementById('addItemForm').style.display = 'block';
    document.getElementById('add_name').focus();
}

function hideAddForm() {
    document.getElementById('addItemForm').style.display = 'none';
    // Clear form
    const form = document.getElementById('addItemForm').querySelector('form');
    form.reset();
    document.getElementById('add_preview').innerHTML = '';
}

function editItem(id, name, description, price, category, image) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_category').value = category || '';
    document.getElementById('edit_current_image').value = image || '';
    
    // Clear previous previews
    document.getElementById('edit_preview').innerHTML = '';
    document.getElementById('edit_image_url').value = '';
    document.getElementById('edit_image_upload').value = '';
    
    // Display current image
    const currentImageDisplay = document.getElementById('current_image_display');
    if (image) {
        const isUrl = image.startsWith('http');
        const imageSrc = isUrl ? image : '../images/' + image;
        
        currentImageDisplay.innerHTML = `
            <div style="border: 1px solid #ddd; padding: 10px; border-radius: 5px; background: #f9f9f9;">
                <img src="${imageSrc}" alt="Current image" style="max-width: 200px; max-height: 150px; object-fit: cover; border-radius: 4px;">
                <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">Current: ${image}</p>
            </div>
        `;
        
        // If it's a URL, populate the URL field
        if (isUrl) {
            document.getElementById('edit_image_url').value = image;
        }
    } else {
        currentImageDisplay.innerHTML = '<p style="color: #999; font-style: italic;">No current image</p>';
    }
    
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function removeCurrentImage() {
    if (confirm('Are you sure you want to remove the current image?')) {
        document.getElementById('edit_current_image').value = '';
        document.getElementById('edit_image_url').value = '';
        document.getElementById('current_image_display').innerHTML = '<p style="color: #999; font-style: italic;">Image will be removed</p>';
    }
}

function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Check file size (5MB limit)
        if (file.size > 5 * 1024 * 1024) {
            alert('File size too large. Maximum 5MB allowed.');
            input.value = '';
            preview.innerHTML = '';
            return;
        }
        
        // Check file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.');
            input.value = '';
            preview.innerHTML = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `
                <div style="border: 1px solid #ddd; padding: 10px; border-radius: 5px; background: #f0fff0;">
                    <img src="${e.target.result}" alt="Preview" style="max-width: 200px; max-height: 150px; object-fit: cover; border-radius: 4px;">
                    <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">Preview: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)</p>
                </div>
            `;
        };
        reader.readAsDataURL(file);
        
        // Clear URL input if file is selected
        const urlInput = previewId === 'add_preview' ? 
            document.getElementById('add_image_url') : 
            document.getElementById('edit_image_url');
        if (urlInput) urlInput.value = '';
    } else {
        preview.innerHTML = '';
    }
}

function exportItems() {
    // Create CSV content
    let csv = 'Name,Description,Price,Category,Available,Image,Date Added\n';
    
    <?php foreach ($items as $item): ?>
    csv += '"<?php echo addslashes($item['name']); ?>","<?php echo addslashes($item['description']); ?>",<?php echo $item['price']; ?>,"<?php echo addslashes($item['category'] ?? ''); ?>","<?php echo $item['available'] ? 'Yes' : 'No'; ?>","<?php echo addslashes($item['image'] ?? ''); ?>","<?php echo $item['created_at']; ?>"\n';
    <?php endforeach; ?>
    
    // Download CSV
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'menu_items_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Close modal when clicking outside
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Escape key to close modal/form
    if (e.key === 'Escape') {
        closeEditModal();
        hideAddForm();
    }
    
    // Ctrl/Cmd + N to add new item
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        showAddForm();
    }
});

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    // Add real-time validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        const nameInput = form.querySelector('input[name="name"]');
        const priceInput = form.querySelector('input[name="price"]');
        
        if (nameInput) {
            nameInput.addEventListener('input', function() {
                if (this.value.length > 100) {
                    this.setCustomValidity('Name must be 100 characters or less');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
        
        if (priceInput) {
            priceInput.addEventListener('input', function() {
                if (this.value <= 0) {
                    this.setCustomValidity('Price must be greater than 0');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    });
    
    // Add hover effects to item cards
    const itemCards = document.querySelectorAll('.menu-item');
    itemCards.forEach(card => {
        card.style.transition = 'transform 0.2s, box-shadow 0.2s';
        card.addEventListener('mouseenter', function() {
            if (window.matchMedia && window.matchMedia('(hover: hover)').matches) {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = '0 5px 20px rgba(0,0,0,0.15)';
            }
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 2px 5px rgba(0,0,0,0.1)';
        });
    });
    
    // Handle image URL input changes
    document.getElementById('add_image_url').addEventListener('input', function() {
        if (this.value.trim()) {
            document.getElementById('add_image_upload').value = '';
            document.getElementById('add_preview').innerHTML = '';
        }
    });
    
    document.getElementById('edit_image_url').addEventListener('input', function() {
        if (this.value.trim()) {
            document.getElementById('edit_image_upload').value = '';
            document.getElementById('edit_preview').innerHTML = '';
        }
    });
});

// Auto-save form data in localStorage (for add form)
function saveFormData() {
    const form = document.getElementById('addItemForm').querySelector('form');
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        if (key !== 'image_upload') { // Don't save file input
            data[key] = value;
        }
    }
    localStorage.setItem('addItemFormData', JSON.stringify(data));
}

function loadFormData() {
    const saved = localStorage.getItem('addItemFormData');
    if (saved) {
        const data = JSON.parse(saved);
        Object.keys(data).forEach(key => {
            const input = document.querySelector(`#addItemForm input[name="${key}"], #addItemForm textarea[name="${key}"]`);
            if (input && key !== 'image_upload') {
                input.value = data[key];
            }
        });
    }
}

// Clear saved data when form is submitted successfully
<?php if ($message && strpos($message, 'added successfully') !== false): ?>
localStorage.removeItem('addItemFormData');
<?php endif; ?>

// Load saved data on page load
document.addEventListener('DOMContentLoaded', loadFormData);

// Save data on form input
document.addEventListener('input', function(e) {
    if (e.target.closest('#addItemForm')) {
        saveFormData();
    }
});

// Drag and drop functionality for image upload
function setupDragAndDrop() {
    const uploadAreas = [
        { input: 'add_image_upload', preview: 'add_preview' },
        { input: 'edit_image_upload', preview: 'edit_preview' }
    ];
    
    uploadAreas.forEach(area => {
        const input = document.getElementById(area.input);
        if (input) {
            const container = input.parentElement;
            
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                container.addEventListener(eventName, preventDefaults, false);
            });
            
            ['dragenter', 'dragover'].forEach(eventName => {
                container.addEventListener(eventName, () => {
                    container.style.background = '#e8f5e8';
                    container.style.border = '2px dashed #27ae60';
                }, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                container.addEventListener(eventName, () => {
                    container.style.background = '';
                    container.style.border = '';
                }, false);
            });
            
            container.addEventListener('drop', (e) => {
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    input.files = files;
                    previewImage(input, area.preview);
                }
            }, false);
        }
    });
}

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

// Initialize drag and drop
document.addEventListener('DOMContentLoaded', setupDragAndDrop);

// Bulk operations (future enhancement)
function selectAllItems() {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => cb.checked = true);
    updateBulkActions();
}

function updateBulkActions() {
    const selected = document.querySelectorAll('.item-checkbox:checked');
    const bulkActions = document.getElementById('bulk-actions');
    if (bulkActions) {
        bulkActions.style.display = selected.length > 0 ? 'block' : 'none';
    }
}

// Image optimization suggestions
function showImageTips() {
    alert(`📸 Image Tips for Best Results:

✅ Recommended:
• Size: 800x600 pixels or larger
• Format: JPEG for photos, PNG for graphics
• File size: Under 2MB for faster loading
• Good lighting and clear focus
• Show the food clearly

❌ Avoid:
• Very large files (over 5MB)
• Blurry or dark images
• Images with watermarks
• Screenshots of other websites`);
}

// Add image tips button
document.addEventListener('DOMContentLoaded', function() {
    const addForm = document.getElementById('addItemForm');
    if (addForm) {
        const tipsButton = document.createElement('button');
        tipsButton.type = 'button';
        tipsButton.textContent = '💡 Image Tips';
        tipsButton.className = 'btn btn-secondary';
        tipsButton.style.fontSize = '12px';
        tipsButton.onclick = showImageTips;
        
        const uploadLabel = addForm.querySelector('label[for="add_image_upload"]');
        if (uploadLabel) {
            uploadLabel.appendChild(tipsButton);
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>