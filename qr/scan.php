<?php
require_once '../config/database.php';

// Table number is optional now
$table_number = isset($_GET['table']) ? (int)$_GET['table'] : null;

if ($table_number) {
    $_SESSION['qr_table_number'] = $table_number;
}

// Redirect to appropriate page
if (isLoggedIn()) {
    redirect('../customer/account_options.php');
} else {
    redirect('../customer/guest_order_type.php');
}
?>