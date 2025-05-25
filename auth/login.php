<?php
// ==============================================
// FILE: auth/login.php (Updated with return URL support)
// ==============================================
require_once '../config/database.php';

$error = '';
$return_url = $_GET['return'] ?? '';

if ($_POST) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect to return URL if provided, otherwise to index
            if (!empty($return_url)) {
                $decoded_url = urldecode($return_url);
                // Security check - ensure it's a relative URL
                if (strpos($decoded_url, '/') === 0 && strpos($decoded_url, '//') !== 0) {
                    redirect($decoded_url);
                }
            }
            
            redirect('../index.php');
        } else {
            $error = 'Invalid username or password';
        }
    }
}

$page_title = 'Login';
include '../includes/header.php';
?>

<div class="form-container">
    <h2 style="text-align: center; margin-bottom: 30px;">Login</h2>
    
    <?php if (!empty($return_url)): ?>
        <div style="background: #e8f4f8; border: 1px solid #3498db; border-radius: 5px; padding: 15px; margin-bottom: 20px;">
            <p style="margin: 0; color: #2c3e50; font-size: 14px;">
                🔳 You'll be redirected back to your table after logging in
            </p>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label for="username">Username or Email:</label>
            <input type="text" id="username" name="username" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <button type="submit" class="btn" style="width: 100%;">Login</button>
    </form>
    
    <p style="text-align: center; margin-top: 20px;">
        Don't have an account? 
        <a href="register.php<?php echo !empty($return_url) ? '?return=' . urlencode($return_url) : ''; ?>">
            Register here
        </a>
    </p>
</div>

<?php include '../includes/footer.php'; ?>