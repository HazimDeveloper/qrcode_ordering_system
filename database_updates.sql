-- Allow NULL user_id in orders table for guest orders
ALTER TABLE orders MODIFY user_id INT NULL;

-- Add customer_name and customer_phone columns to orders table
ALTER TABLE orders ADD COLUMN customer_name VARCHAR(100) NULL AFTER user_id;
ALTER TABLE orders ADD COLUMN customer_phone VARCHAR(20) NULL AFTER customer_name;

-- Create table_bookings table for event bookings
CREATE TABLE table_bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id VARCHAR(20) NOT NULL,
  user_id INT NOT NULL,
  table_number INT NOT NULL,
  booking_date DATE NOT NULL,
  booking_time TIME NOT NULL,
  guests INT NOT NULL,
  event_type VARCHAR(50) NULL,
  package VARCHAR(20) NULL,
  package_price DECIMAL(10,2) NULL,
  special_requests TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);