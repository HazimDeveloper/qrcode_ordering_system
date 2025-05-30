<?php
require_once '../config/database.php';

if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';
$booking_id = null;

if ($_POST) {
    $table_number = (int)$_POST['table_number'];
    $booking_date = $_POST['booking_date'];
    $booking_time = $_POST['booking_time'];
    $guests = (int)$_POST['guests'];
    $event_type = $_POST['event_type'] ?? '';
    $package = $_POST['package'] ?? '';
    $special_requests = $_POST['special_requests'] ?? '';
    
    if ($table_number && $booking_date && $booking_time && $guests) {
        // Generate a unique booking ID
        $booking_id = 'BK' . date('Ymd') . rand(1000, 9999);
        
        // Calculate package price
        $package_price = 0;
        if ($event_type && $package) {
            if ($package === 'package_a') {
                $package_price = 60;
            } elseif ($package === 'package_b') {
                $package_price = 75;
            }
        }
        
        // In a real system, you'd save this to a bookings table
        $message = "Table booking confirmed! Your booking ID is <strong>$booking_id</strong>.<br>" . 
                  "Table $table_number for $guests guests on " . 
                  date('M j, Y', strtotime($booking_date)) . " at $booking_time";
                  
        if ($event_type) {
            $message .= "<br>Event Type: " . ucfirst($event_type);
            if ($package) {
                $package_name = ($package === 'package_a') ? 'Package A (Basic Decoration)' : 'Package B (Premium Decoration)';
                $message .= "<br>Selected Package: $package_name - RM $package_price";
            }
        }
    } else {
        $error = 'All fields are required';
    }
}

include '../includes/header.php';
?>

<h1>Book Table & Events</h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
    
    <?php if ($booking_id): ?>
        <div style="background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px; margin: 30px 0;">
            <h2 style="text-align: center; margin-bottom: 20px;">Booking Confirmation</h2>
            
            <div style="border: 1px dashed #ccc; padding: 20px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <strong>Booking ID:</strong>
                    <span><?php echo $booking_id; ?></span>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <strong>Customer:</strong>
                    <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <strong>Table:</strong>
                    <span>Table <?php echo $_POST['table_number']; ?></span>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <strong>Date & Time:</strong>
                    <span><?php echo date('M j, Y', strtotime($_POST['booking_date'])); ?> at <?php echo $_POST['booking_time']; ?></span>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <strong>Number of Guests:</strong>
                    <span><?php echo $_POST['guests']; ?> person(s)</span>
                </div>
                
                <?php if (!empty($_POST['event_type'])): ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <strong>Event Type:</strong>
                        <span><?php echo ucfirst($_POST['event_type']); ?></span>
                    </div>
                    
                    <?php if (!empty($_POST['package'])): ?>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <strong>Package:</strong>
                            <span>
                                <?php 
                                if ($_POST['package'] === 'package_a') {
                                    echo 'Package A (Basic Decoration) - RM 60';
                                } else {
                                    echo 'Package B (Premium Decoration) - RM 75';
                                }
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if (!empty($_POST['special_requests'])): ?>
                    <div style="margin-top: 15px;">
                        <strong>Special Requests:</strong>
                        <p style="margin-top: 5px;"><?php echo nl2br(htmlspecialchars($_POST['special_requests'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center;">
                <button onclick="window.print()" class="btn">Print Confirmation</button>
            </div>
        </div>
    <?php endif; ?>
    
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
                    <?php for ($i = 1; $i <= 20; $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?> Guest<?php echo $i > 1 ? 's' : ''; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="event_type">Event Type (Optional):</label>
                <select name="event_type" id="event_type">
                    <option value="">No Special Event</option>
                    <option value="birthday">Birthday Celebration</option>
                    <option value="anniversary">Anniversary</option>
                    <option value="business">Business Meeting</option>
                    <option value="other">Other Special Occasion</option>
                </select>
            </div>
            
            <div class="form-group" id="package_selection" style="display: none;">
                <label for="package">Event Package:</label>
                <select name="package" id="package">
                    <option value="package_a">Package A (RM60) - Basic Decoration (Up to 10 persons)</option>
                    <option value="package_b">Package B (RM75) - Premium Decoration (More than 10 persons)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="special_requests">Special Requests (Optional):</label>
                <textarea name="special_requests" id="special_requests" rows="4"></textarea>
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
                <li>For groups larger than 20, please call us directly</li>
                <li>Event packages require at least 3 days advance booking</li>
            </ul>
        </div>
    </div>
    
    <script>
    document.getElementById('event_type').addEventListener('change', function() {
        document.getElementById('package_selection').style.display = 
            this.value ? 'block' : 'none';
    });
    
    document.getElementById('guests').addEventListener('change', function() {
        const packageSelect = document.getElementById('package');
        if (parseInt(this.value) > 10) {
            packageSelect.value = 'package_b';
            packageSelect.options[0].disabled = true;
        } else {
            packageSelect.options[0].disabled = false;
        }
    });
    </script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>