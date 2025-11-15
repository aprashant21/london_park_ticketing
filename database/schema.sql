-- London Community Park Ticketing System Database Schema

-- Create Database
CREATE DATABASE IF NOT EXISTS london_park_ticketing;
USE london_park_ticketing;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
                                     id INT AUTO_INCREMENT PRIMARY KEY,
                                     username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    photo_path VARCHAR(255),
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Events Table
CREATE TABLE IF NOT EXISTS events (
                                      id INT AUTO_INCREMENT PRIMARY KEY,
                                      event_name VARCHAR(150) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    venue VARCHAR(100),
    total_capacity INT NOT NULL,
    seats_with_tables INT NOT NULL,
    seats_without_tables INT NOT NULL,
    requires_adult BOOLEAN DEFAULT TRUE,
    max_tickets_per_sale INT DEFAULT 8,
    status ENUM('active', 'inactive', 'sold_out') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_event_date (event_date),
    INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Prices Table
CREATE TABLE IF NOT EXISTS prices (
                                      id INT AUTO_INCREMENT PRIMARY KEY,
                                      event_id INT NOT NULL,
                                      seat_type ENUM('with_table', 'without_table') NOT NULL,
    adult_price DECIMAL(10, 2) NOT NULL,
    child_price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY unique_event_seat (event_id, seat_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bookings Table
CREATE TABLE IF NOT EXISTS bookings (
                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                        user_id INT NOT NULL,
                                        event_id INT NOT NULL,
                                        booking_reference VARCHAR(20) UNIQUE NOT NULL,
    num_adults INT NOT NULL,
    num_children INT DEFAULT 0,
    total_tickets INT NOT NULL,
    seat_type ENUM('with_table', 'without_table') NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    booking_status ENUM('confirmed', 'cancelled', 'pending') DEFAULT 'confirmed',
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_event_id (event_id),
    INDEX idx_booking_reference (booking_reference)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Default Admin User (password: admin123)
-- Password hash generated using: password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO users (username, email, password_hash, full_name, role) VALUES
    ('admin', 'admin@londonpark.com', '$2y$10$YourHashWillBeHere', 'System Administrator', 'admin');

-- IMPORTANT: After importing, run this query to set the correct password:
-- UPDATE users SET password_hash = '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeFU8FXJf3Ut0f1qYzKJr1r8Gx5u5HLO6' WHERE username = 'admin';

-- Insert Sample Events
INSERT INTO events (event_name, description, event_date, event_time, venue, total_capacity, seats_with_tables, seats_without_tables, requires_adult, max_tickets_per_sale) VALUES
                                                                                                                                                                               ('Christmas Magic Show', 'A spectacular magic show for the whole family featuring world-class magicians and illusions.', '2025-12-20', '18:00:00', 'Indoor Circus Theatre', 200, 80, 120, TRUE, 8),
                                                                                                                                                                               ('Santa\'s Steam Train Adventure', 'Journey through winter wonderland on our vintage steam train with Santa and his elves.', '2025-12-21', '15:00:00', 'Sweeney Railway Station', 150, 60, 90, TRUE, 8),
('Winter Water Sports Gala', 'Thrilling water sports demonstrations and activities on our frozen lake.', '2025-12-22', '14:00:00', 'Lakeside Arena', 180, 70, 110, TRUE, 8),
('Christmas Carol Concert', 'Traditional Christmas carols performed by local choirs in our beautiful park setting.', '2025-12-23', '19:00:00', 'Main Pavilion', 250, 100, 150, FALSE, 10),
('New Year\'s Eve Fireworks Spectacular', 'Ring in the New Year with our amazing fireworks display and live entertainment.', '2025-12-31', '20:00:00', 'Central Park Ground', 500, 200, 300, FALSE, 10);

-- Insert Prices for Events
INSERT INTO prices (event_id, seat_type, adult_price, child_price) VALUES
                                                                       (1, 'with_table', 35.00, 20.00),
                                                                       (1, 'without_table', 25.00, 15.00),
                                                                       (2, 'with_table', 40.00, 25.00),
                                                                       (2, 'without_table', 30.00, 18.00),
                                                                       (3, 'with_table', 45.00, 28.00),
                                                                       (3, 'without_table', 32.00, 20.00),
                                                                       (4, 'with_table', 30.00, 18.00),
                                                                       (4, 'without_table', 22.00, 12.00),
                                                                       (5, 'with_table', 55.00, 35.00),
                                                                       (5, 'without_table', 40.00, 25.00);
