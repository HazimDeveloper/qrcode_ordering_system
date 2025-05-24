<?php
require_once '../config/database.php';

if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';

if ($_POST) {
    $table_number = (int)$_POST['table_number'];
    $booking_date = $_POST['booking_date'];
    $booking_time = $_POST['booking_time'];
    $guests = (int)$_POST['guests'];
    
    if ($table_number && $booking_date && $booking_time && $guests) {
        // For simplicity, we'll just show a confirmation message
        // In a real system, you'd save this to a bookings table
        $message = "Table booking request submitted! Table $table_number for $guests guests on " . 
                  date('M j, Y', strtotime($booking_date)) . " at $booking_time";
    } else {
        $error = 'All fields are required';
    }
}

include '../includes/header.php';
?>

<h1>Book Table</h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
    <div style="text-align: center; margin: 30px 0;">
        <a href="../index.php" class="btn">Back to Home</a>
        <a href="menu.php" class="btn btn-secondary">Browse Menu</a>
    </div>
<?php else: ?>
    <div class="form-container" style="max-width: 600px;">
        <form method="POST">
            <div class="form-group">
                <label for="table_number">Preferred Table Number:</label>
                <select name="table_number" id="table_number" required>
                    <option value="">Select a table</option>
                    <?php for ($i = 1; $i <= 20; $i++): ?>
                        <option value="<?php echo $i; ?>">Table <?php echo $i; ?> (<?php echo rand(2,6); ?> seats)</option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="booking_date">Date:</label>
                <input type="date" name="booking_date" id="booking_date" 
                       min="<?php echo date('Y-m-d'); ?>" 
                       max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="booking_time">Time:</label>
                <select name="booking_time" id="booking_time" required>
                    <option value="">Select time</option>
                    <?php
                    $times = ['11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', 
                             '17:00', '17:30', '18:00', '18:30', '19:00', '19:30', '20:00', '20:30', '21:00'];
                    foreach ($times as $time): ?>
                        <option value="<?php echo $time; ?>"><?php echo $time; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="guests">Number of Guests:</label>
                <select name="guests" id="guests" required>
                    <option value="">Select guests</option>
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?> Guest<?php echo $i > 1 ? 's' : ''; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <button type="submit" class="btn" style="width: 100%;">Book Table</button>
        </form>
        
        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <h3>Booking Information</h3>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Bookings can be made up to 30 days in advance</li>
                <li>Lunch: 11:00 AM - 3:00 PM</li>
                <li>Dinner: 5:00 PM - 9:30 PM</li>
                <li>Please arrive within 15 minutes of your booking time</li>
                <li>For groups larger than 10, please call us directly</li>
            </ul>
        </div>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>