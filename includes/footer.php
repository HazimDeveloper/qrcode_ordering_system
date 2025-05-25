<?php
// ==============================================
// FILE: includes/footer.php
// ==============================================
?>
    </main>
    <footer style="background: #34495e; color: white; text-align: center; padding: 20px; margin-top: 50px;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <p>&copy; <?php echo date('Y'); ?> QR Food Ordering Platform. All rights reserved.</p>
            <p style="font-size: 14px; opacity: 0.8; margin-top: 10px;">
                Powered by QR Technology
                <?php if (isLoggedIn() && isStaff()): ?>
                    <a href="/qr-food-ordering/qr/generate.php" style="color: #ecf0f1;">Generate QR Codes</a>
                <?php endif; ?>
            </p>
        </div>
    </footer>
</body>
</html>