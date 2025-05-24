<?php
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';

// Add new item
if ($_POST && isset($_POST['add_item'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $category = trim($_POST['category']);
    $image_url = trim($_POST['image_url']);
    
    if (empty($name) || empty($description) || $price <= 0) {
        $error = 'Name, description are required and price must be greater than 0';
    } else {
        // Check if item name already exists
        $stmt = $pdo->prepare("SELECT id FROM menu_items WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            $error = 'An item with this name already exists';
        } else {
            $stmt = $pdo->prepare("INSERT INTO menu_items (name, description, price, category, image) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $description, $price, $category, $image_url])) {
                $message = 'Menu item added successfully!';
            } else {
                $error = 'Failed to add menu item';
            }
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
    $image_url = trim($_POST['image_url']);
    
    if (empty($name) || empty($description) || $price <= 0) {
        $error = 'Name, description are required and price must be greater than 0';
    } else {
        // Check if another item has the same name
        $stmt = $pdo->prepare("SELECT id FROM menu_items WHERE name = ? AND id != ?");
        $stmt->execute([$name, $id]);
        if ($stmt->fetch()) {
            $error = 'Another item with this name already exists';
        } else {
            $stmt = $pdo->prepare("UPDATE menu_items SET name = ?, description = ?, price = ?, category = ?, image = ? WHERE id = ?");
            if ($stmt->execute([$name, $description, $price, $category, $image_url, $id])) {
                $message = 'Menu item updated successfully!';
            } else {
                $error = 'Failed to update menu item';
            }
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
        $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
        if ($stmt->execute([$id])) {
            $message = 'Menu item deleted successfully!';
        } else {
            $error = 'Failed to delete menu item';
        }
    }
}

// Toggle item availability
if ($_GET['toggle'] ?? false) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE menu_items SET available = NOT available WHERE id = ?");
    if ($stmt->execute([$id])) {
        $message = 'Item availability updated!';
    } else {
        $error = 'Failed to update item availability';
    }
}

// Get search and filter parameters
$search = trim($_GET['search'] ?? '');
$category_filter = $_GET['category'] ?? '';
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

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Validate sort parameters
$valid_sorts = ['name', 'price', 'category', 'created_at'];
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
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
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
    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
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
            <label for="sort" style="display: block; margin-bottom: 5px; font-weight: bold;">Sort By:</label>
            <select name="sort" id="sort" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name</option>
                <option value="price" <?php echo $sort_by === 'price' ? 'selected' : ''; ?>>Price</option>
                <option value="category" <?php echo $sort_by === 'category' ? 'selected' : ''; ?>>Category</option>
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
    <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
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
            <label for="add_image">Image URL:</label>
            <input type="url" name="image_url" id="add_image" placeholder="https://example.com/image.jpg">
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
        <?php if (!empty($search) || !empty($category_filter)): ?>
            <a href="manage_items.php" class="btn">Clear Filters</a>
        <?php else: ?>
            <button onclick="showAddForm()" class="btn">Add Your First Item</button>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="menu-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <?php foreach ($items as $item): ?>
            <div class="menu-item" style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); position: relative;">
                <!-- Availability Badge -->
                <?php if (isset($item['available']) && !$item['available']): ?>
                    <div style="position: absolute; top: 10px; right: 10px; background: #e74c3c; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px; z-index: 1;">
                        Unavailable
                    </div>
                <?php endif; ?>
                
                <!-- Item Image -->
                <div style="height: 200px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                    <?php if (!empty($item['image'])): ?>
                        <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                             style="width: 100%; height: 100%; object-fit: cover;"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div style="display: none; width: 100%; height: 100%; background: #ecf0f1; align-items: center; justify-content: center; color: #7f8c8d;">
                            📷 No Image
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
                           background: <?php echo (isset($item['available']) && !$item['available']) ? '#27ae60' : '#f39c12'; ?>;">
                            <?php echo (isset($item['available']) && !$item['available']) ? '✅ Enable' : '⏸️ Disable'; ?>
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
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; width: 90%; max-width: 600px; max-height: 80%; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Edit Menu Item</h3>
            <button onclick="closeEditModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">×</button>
        </div>
        
        <form method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <input type="hidden" name="item_id" id="edit_id">
            
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
                <label for="edit_image">Image URL:</label>
                <input type="url" name="image_url" id="edit_image">
            </div>
            
            <div class="form-group" style="grid-column: 1 / -1;">
                <label for="edit_description">Description: *</label>
                <textarea name="description" id="edit_description" rows="3" required maxlength="500"></textarea>
            </div>
            
            <div style="grid-column: 1 / -1; display: flex; gap: 10px;">
                <button type="submit" name="update_item" class="btn">Update Item</button>
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
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
    document.getElementById('addItemForm').querySelector('form').reset();
}

function editItem(id, name, description, price, category, image) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_category').value = category || '';
    document.getElementById('edit_image').value = image || '';
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function exportItems() {
    // Create CSV content
    let csv = 'Name,Description,Price,Category,Available,Date Added\n';
    
    <?php foreach ($items as $item): ?>
    csv += '"<?php echo addslashes($item['name']); ?>","<?php echo addslashes($item['description']); ?>",<?php echo $item['price']; ?>,"<?php echo addslashes($item['category'] ?? ''); ?>","<?php echo isset($item['available']) && $item['available'] ? 'Yes' : 'No'; ?>","<?php echo $item['created_at']; ?>"\n';
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
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 5px 20px rgba(0,0,0,0.15)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 2px 5px rgba(0,0,0,0.1)';
        });
    });
});

// Auto-save form data in localStorage (for add form)
function saveFormData() {
    const form = document.getElementById('addItemForm').querySelector('form');
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    localStorage.setItem('addItemFormData', JSON.stringify(data));
}

function loadFormData() {
    const saved = localStorage.getItem('addItemFormData');
    if (saved) {
        const data = JSON.parse(saved);
        Object.keys(data).forEach(key => {
            const input = document.querySelector(`#addItemForm input[name="${key}"], #addItemForm textarea[name="${key}"]`);
            if (input) {
                input.value = data[key];
            }
        });
    }
}

// Clear saved data when form is submitted successfully
<?php if ($message && strpos($message, 'added successfully') !== false): ?>
localStorage.removeItem('addItemFormData');
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>