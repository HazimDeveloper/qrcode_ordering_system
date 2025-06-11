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
        // Generate unique booking ID
        $booking_id = 'BK' . date('Ymd') . rand(1000, 9999);
        
        // Calculate package price based on selection
        $package_price = 0;
        $package_details = '';
        
        if ($event_type && $package) {
            if ($package === 'package_a') {
                $package_price = 60;
                $package_details = 'Package A - Basic Decoration (Balloons, Simple Table Setup)';
            } elseif ($package === 'package_b') {
                $package_price = 75;
                $package_details = 'Package B - Premium Decoration (Enhanced Setup, Premium Elements)';
            }
        }
        
        try {
            // Insert booking into database
            $stmt = $pdo->prepare("
                INSERT INTO table_bookings (
                    booking_id, user_id, table_number, booking_date, booking_time, 
                    guests, event_type, package, package_price, special_requests, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')
            ");
            
            $result = $stmt->execute([
                $booking_id, $_SESSION['user_id'], $table_number, $booking_date, 
                $booking_time, $guests, $event_type, $package, $package_price, $special_requests
            ]);
            
            if ($result) {
                $message = "Table booking confirmed! Your booking ID is <strong>$booking_id</strong>";
            } else {
                $error = 'Failed to save booking. Please try again.';
            }
            
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    } else {
        $error = 'All required fields must be filled';
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
        <!-- Booking Confirmation Receipt -->
        <div style="background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); padding: 30px; margin: 30px 0; max-width: 600px; margin-left: auto; margin-right: auto;">
            <h2 style="text-align: center; margin-bottom: 25px; color: #27ae60;">✅ Booking Confirmed</h2>
            
            <div style="border: 2px dashed #27ae60; padding: 25px; margin-bottom: 25px; border-radius: 8px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div><strong>Booking ID:</strong></div>
                    <div style="color: #27ae60; font-weight: bold;"><?php echo $booking_id; ?></div>
                    
                    <div><strong>Customer:</strong></div>
                    <div><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                    
                    <div><strong>Table Number:</strong></div>
                    <div>Table <?php echo $_POST['table_number']; ?></div>
                    
                    <div><strong>Date & Time:</strong></div>
                    <div><?php echo date('F j, Y', strtotime($_POST['booking_date'])); ?> at <?php echo $_POST['booking_time']; ?></div>
                    
                    <div><strong>Number of Guests:</strong></div>
                    <div><?php echo $_POST['guests']; ?> person(s)</div>
                </div>
                
                <?php if (!empty($_POST['event_type'])): ?>
                    <div style="border-top: 1px solid #ecf0f1; padding-top: 15px; margin-top: 15px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div><strong>Event Type:</strong></div>
                            <div style="text-transform: capitalize;"><?php echo str_replace('_', ' ', $_POST['event_type']); ?></div>
                            
                            <?php if (!empty($_POST['package'])): ?>
                                <div><strong>Package:</strong></div>
                                <div>
                                    <?php 
                                    if ($_POST['package'] === 'package_a') {
                                        echo 'Package A - Basic Decoration';
                                        $price = 60;
                                    } else {
                                        echo 'Package B - Premium Decoration';
                                        $price = 75;
                                    }
                                    ?>
                                </div>
                                
                                <div><strong>Package Price:</strong></div>
                                <div style="color: #e67e22; font-weight: bold;">RM <?php echo $price; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($_POST['special_requests'])): ?>
                    <div style="border-top: 1px solid #ecf0f1; padding-top: 15px; margin-top: 15px;">
                        <div><strong>Special Requests:</strong></div>
                        <div style="margin-top: 8px; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                            <?php echo nl2br(htmlspecialchars($_POST['special_requests'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center;">
                <button onclick="window.print()" class="btn">🖨️ Print Confirmation</button>
                <a href="../index.php" class="btn btn-secondary" style="margin-left: 10px;">🏠 Back to Home</a>
            </div>
        </div>
    <?php endif; ?>
    
<?php else: ?>
    <!-- Booking Form -->
    <div style="max-width: 800px; margin: 0 auto;">
        <!-- Package Information Display -->
        <div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 25px; border-radius: 12px; margin-bottom: 30px;">
            <h3 style="text-align: center; margin-bottom: 20px; color: #2c3e50;">🎉 Event Packages Available</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Package A -->
                <div style="background: white; padding: 20px; border-radius: 8px; border: 2px solid #3498db; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Sample Image -->
                    <div style="height: 120px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; justify-content: center; font-size: 48px;">
                        🎈🎂
                    </div>
                    
                    <h4 style="color: #3498db; margin-bottom: 15px;">📦 Package A - <span style="font-size: 24px; font-weight: bold;">RM 60</span></h4>
                    <div style="color: #666; font-size: 14px; margin-bottom: 10px;">Perfect for intimate gatherings</div>
                    <ul style="margin: 10px 0; padding-left: 20px; color: #333;">
                        <li>🎈 Basic balloon decoration</li>
                        <li>🍽️ Simple table setup</li>
                        <li>🎂 Birthday cake stand (if applicable)</li>
                        <li>📸 Basic photo corner</li>
                        <li>🎵 Complimentary birthday song</li>
                    </ul>
                    <div style="background: #e8f4f8; padding: 12px; border-radius: 4px; font-size: 13px; color: #2c3e50; text-align: center; margin-top: 15px;">
                        <strong>💰 RM 60 | Up to 10 guests</strong>
                    </div>
                </div>
                
                <!-- Package B -->
                <div style="background: white; padding: 20px; border-radius: 8px; border: 2px solid #e67e22; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Sample Image -->
                    <div style="height: 120px; background: linear-gradient(135deg, #fff3e0 0%, #ffcc02 100%); border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; justify-content: center; font-size: 48px;">
                        ✨🎉
                    </div>
                    
                    <h4 style="color: #e67e22; margin-bottom: 15px;">🎁 Package B - <span style="font-size: 24px; font-weight: bold;">RM 75</span></h4>
                    <div style="color: #666; font-size: 14px; margin-bottom: 10px;">Enhanced experience for larger groups</div>
                    <ul style="margin: 10px 0; padding-left: 20px; color: #333;">
                        <li>🎈 Premium balloon arrangements</li>
                        <li>✨ Enhanced table decorations</li>
                        <li>🎂 Decorated cake stand with lighting</li>
                        <li>📸 Professional photo backdrop</li>
                        <li>🎵 Background music setup</li>
                        <li>🎉 Party favor table</li>
                        <li>🌟 Surprise welcome banner</li>
                    </ul>
                    <div style="background: #fef9e7; padding: 12px; border-radius: 4px; font-size: 13px; color: #2c3e50; text-align: center; margin-top: 15px;">
                        <strong>💰 RM 75 | More than 10 guests</strong>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Booking Form -->
        <form method="POST" style="background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); padding: 30px;">
            <h3 style="margin-bottom: 25px; text-align: center; color: #2c3e50;">📅 Reservation Details</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <!-- Table Selection -->
                <div class="form-group">
                    <label for="table_number">Preferred Table Number: *</label>
                    <select name="table_number" id="table_number" required>
                        <option value="">Select a table</option>
                        <?php for ($i = 1; $i <= 20; $i++): ?>
                            <option value="<?php echo $i; ?>">Table <?php echo $i; ?> (<?php echo rand(2,8); ?> seats)</option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <!-- Number of Guests -->
                <div class="form-group">
                    <label for="guests">Number of Guests: *</label>
                    <select name="guests" id="guests" required>
                        <option value="">Select guests</option>
                        <?php for ($i = 1; $i <= 25; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?> Guest<?php echo $i > 1 ? 's' : ''; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <!-- Booking Date -->
                <div class="form-group">
                    <label for="booking_date">Date: *</label>
                    <input type="date" name="booking_date" id="booking_date" 
                           min="<?php echo date('Y-m-d'); ?>" 
                           max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                </div>
                
                <!-- Booking Time -->
                <div class="form-group">
                    <label for="booking_time">Time: *</label>
                    <select name="booking_time" id="booking_time" required>
                        <option value="">Select time</option>
                        <optgroup label="Lunch Hours">
                            <?php
                            $lunch_times = ['11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30'];
                            foreach ($lunch_times as $time): ?>
                                <option value="<?php echo $time; ?>"><?php echo $time; ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Dinner Hours">
                            <?php
                            $dinner_times = ['17:00', '17:30', '18:00', '18:30', '19:00', '19:30', '20:00', '20:30', '21:00'];
                            foreach ($dinner_times as $time): ?>
                                <option value="<?php echo $time; ?>"><?php echo $time; ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
            </div>
            
            <!-- Event Type Selection -->
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="event_type">Event Type (Optional):</label>
                <select name="event_type" id="event_type">
                    <option value="">🍽️ Regular Dining (No Special Event)</option>
                    <option value="birthday">🎂 Birthday Celebration</option>
                    <option value="anniversary">💕 Anniversary</option>
                    <option value="graduation">🎓 Graduation Party</option>
                    <option value="business">💼 Business Meeting</option>
                    <option value="proposal">💍 Marriage Proposal</option>
                    <option value="baby_shower">👶 Baby Shower</option>
                    <option value="farewell">👋 Farewell Party</option>
                    <option value="other">🎉 Other Special Occasion</option>
                </select>
            </div>
            
            <!-- Package Selection (Hidden by default) -->
            <div class="form-group" id="package_selection" style="display: none; margin-bottom: 20px;">
                <label for="package">Select Event Package: *</label>
                <select name="package" id="package">
                    <option value="">Choose a package</option>
                    <option value="package_a" data-price="60">
                        Package A (RM 60) - Basic Decoration - Up to 10 guests
                    </option>
                    <option value="package_b" data-price="75">
                        Package B (RM 75) - Premium Decoration - More than 10 guests
                    </option>
                </select>
                
                <!-- Package Price Display -->
                <div id="package_price_display" style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px; display: none;">
                    <strong>Package Price: <span id="price_amount" style="color: #e67e22;"></span></strong>
                </div>
                
                <!-- Auto-selection Notice -->
                <div id="auto_selection_notice" style="margin-top: 10px; padding: 8px; background: #e8f5e8; border-left: 4px solid #27ae60; border-radius: 0 4px 4px 0; display: none;">
                    <small style="color: #27ae60; font-weight: 500;">
                        ✅ Package automatically selected based on guest count
                    </small>
                </div>
            </div>
            
            <!-- Special Requests -->
            <div class="form-group" style="margin-bottom: 25px;">
                <label for="special_requests">Special Requests (Optional):</label>
                <textarea name="special_requests" id="special_requests" rows="4" 
                          placeholder="Any special requirements, dietary restrictions, or additional requests..."></textarea>
            </div>
            
            <!-- Submit Button -->
            <div style="text-align: center;">
                <button type="submit" class="btn" style="padding: 15px 40px; font-size: 16px;">
                    ✅ Confirm Booking
                </button>
            </div>
        </form>
        
        <!-- Booking Information -->
        <div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 25px; border-radius: 12px; margin-top: 30px;">
            <h3 style="margin-bottom: 15px; color: #2c3e50;">📋 Booking Information</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; font-size: 14px;">
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <li style="margin-bottom: 8px;">📅 Bookings: Up to 30 days in advance</li>
                    <li style="margin-bottom: 8px;">🍽️ Lunch: 11:00 AM - 3:00 PM</li>
                    <li style="margin-bottom: 8px;">🌃 Dinner: 5:00 PM - 9:30 PM</li>
                    <li style="margin-bottom: 8px;">⏰ Arrival: Within 15 minutes of booking time</li>
                </ul>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <li style="margin-bottom: 8px;">👥 Large groups (25+): Call directly</li>
                    <li style="margin-bottom: 8px;">🎉 Event packages: 3 days advance booking</li>
                    <li style="margin-bottom: 8px;">💰 Package payment: At the restaurant</li>
                    <li style="margin-bottom: 8px;">📞 Questions: Contact our staff</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
    // Show/hide package selection based on event type
    document.getElementById('event_type').addEventListener('change', function() {
        const packageSelection = document.getElementById('package_selection');
        const packageSelect = document.getElementById('package');
        
        if (this.value && this.value !== '') {
            packageSelection.style.display = 'block';
            packageSelect.required = true;
            
            // Auto-select package based on current guest count
            autoSelectPackage();
        } else {
            packageSelection.style.display = 'none';
            packageSelect.required = false;
            hidePackagePrice();
        }
    });
    
    // Auto-select package based on guest count
    document.getElementById('guests').addEventListener('change', function() {
        autoSelectPackage();
    });
    
    // Show package price when package is selected
    document.getElementById('package').addEventListener('change', function() {
        showPackagePrice();
    });
    
    function autoSelectPackage() {
        const eventType = document.getElementById('event_type').value;
        const guestCount = parseInt(document.getElementById('guests').value);
        const packageSelect = document.getElementById('package');
        const autoNotice = document.getElementById('auto_selection_notice');
        
        if (eventType && guestCount) {
            if (guestCount > 10) {
                packageSelect.value = 'package_b';
                packageSelect.options[1].disabled = true; // Disable Package A
                packageSelect.options[2].disabled = false;
                autoNotice.style.display = 'block';
                autoNotice.innerHTML = '<small style="color: #e67e22; font-weight: 500;">⚠️ Package B automatically selected for groups over 10 guests</small>';
            } else {
                packageSelect.options[1].disabled = false; // Enable Package A
                packageSelect.options[2].disabled = false;
                if (packageSelect.value === '') {
                    packageSelect.value = 'package_a'; // Default to Package A for smaller groups
                }
                autoNotice.style.display = 'block';
                autoNotice.innerHTML = '<small style="color: #27ae60; font-weight: 500;">✅ Package A recommended for groups up to 10 guests</small>';
            }
            showPackagePrice();
        } else {
            autoNotice.style.display = 'none';
            hidePackagePrice();
        }
    }
    
    function showPackagePrice() {
        const packageSelect = document.getElementById('package');
        const priceDisplay = document.getElementById('package_price_display');
        const priceAmount = document.getElementById('price_amount');
        
        if (packageSelect.value) {
            const selectedOption = packageSelect.options[packageSelect.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            
            if (price) {
                priceAmount.textContent = 'RM ' + price;
                priceDisplay.style.display = 'block';
            }
        } else {
            hidePackagePrice();
        }
    }
    
    function hidePackagePrice() {
        document.getElementById('package_price_display').style.display = 'none';
        document.getElementById('auto_selection_notice').style.display = 'none';
    }
    
    // Form validation
    document.querySelector('form').addEventListener('submit', function(e) {
        const eventType = document.getElementById('event_type').value;
        const packageSelect = document.getElementById('package');
        
        if (eventType && !packageSelect.value) {
            e.preventDefault();
            alert('Please select an event package for your special occasion.');
            packageSelect.focus();
            return false;
        }
    });
    
    // Date validation - prevent past dates
    document.getElementById('booking_date').addEventListener('change', function() {
        const selectedDate = new Date(this.value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (selectedDate < today) {
            alert('Please select a future date for your booking.');
            this.value = '';
        }
    });
    
    // Add visual feedback for form completion
    const requiredFields = ['table_number', 'guests', 'booking_date', 'booking_time'];
    
    requiredFields.forEach(fieldId => {
        document.getElementById(fieldId).addEventListener('change', function() {
            if (this.value) {
                this.style.borderColor = '#27ae60';
                this.style.backgroundColor = '#f8fff8';
            } else {
                this.style.borderColor = '';
                this.style.backgroundColor = '';
            }
        });
    });
    
    console.log('Enhanced booking system loaded successfully');
    </script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>