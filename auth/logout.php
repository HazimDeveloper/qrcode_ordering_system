<?php
require_once '../config/database.php';

// Clear all session data
session_unset();
session_destroy();

// Redirect to home page
redirect('../index.php');
?>