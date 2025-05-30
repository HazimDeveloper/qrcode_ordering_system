<?php
require_once '../config/database.php';

// No login check - this is for non-logged in users

$error = '';

if ($_POST && isset($_POST['table_number'])) {
    $table_number = (int)$_POST['table_number'];
    
    if ($table_number > 0) {
        $_SESSION['qr_table_number'] = $table_number;
        redirect('guest_menu.php');
    } else {
        $error = 'Please enter a valid table number';
    }
}

include '../includes/header.php';
?>

<div style="text-align: center; padding: 50px 0;">
    <h1>Enter Your Table Number</h1>
    <p style="margin: 20px 0; color: #666;">Please enter the table number where you are seated</p>
    
    <?php if ($error): ?>
        <div class="alert alert-error" style="max-width: 400px; margin: 0 auto 20px;"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST" style="max-width: 400px; margin: 0 auto;">
        <div style="margin-bottom: 20px;">
            <input type="number" name="table_number" min="1" max="999" 
                   placeholder="Table Number" required
                   style="width: 100%; padding: 15px; font-size: 18px; text-align: center; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <button type="submit" class="btn" style="width: 100%; padding: 15px;">
            Continue to Menu
        </button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>