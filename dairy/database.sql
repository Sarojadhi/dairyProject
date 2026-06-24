-- Shree Tri Shakti Dairy Milk Collection System
-- Database Schema

CREATE DATABASE IF NOT EXISTS dairy_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dairy_db;

-- Users table (admin, staff)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    phone VARCHAR(15),
    email VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Farmers table
CREATE TABLE IF NOT EXISTS farmers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    address VARCHAR(255),
    photo VARCHAR(255) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Milk rate table (admin sets rate per fat %)
CREATE TABLE IF NOT EXISTS milk_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    min_fat DECIMAL(4,2) NOT NULL,
    max_fat DECIMAL(4,2) NOT NULL,
    min_snf DECIMAL(5,2) NOT NULL DEFAULT 0,
    max_snf DECIMAL(5,2) NOT NULL DEFAULT 99.99,
    rate_per_liter DECIMAL(8,2) NOT NULL,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Milk collection records
CREATE TABLE IF NOT EXISTS milk_collection (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id INT NOT NULL,
    collection_date DATE NOT NULL,
    shift ENUM('morning','evening') NOT NULL,
    litre DECIMAL(8,2) NOT NULL,
    fat DECIMAL(5,2) NOT NULL,
    snf DECIMAL(5,2) NOT NULL,
    rate_per_liter DECIMAL(8,2) NOT NULL,
    total_amount DECIMAL(10,2) GENERATED ALWAYS AS (litre * rate_per_liter) STORED,
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES farmers(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

-- Dana (cow feed) records
CREATE TABLE IF NOT EXISTS dana_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id INT NOT NULL,
    dana_date DATE NOT NULL,
    bags DECIMAL(8,2) NOT NULL,
    rate_per_bag DECIMAL(8,2) NOT NULL,
    is_paid TINYINT(1) NOT NULL DEFAULT 0,
    total_amount DECIMAL(10,2) GENERATED ALWAYS AS (bags * rate_per_bag) STORED,
    notes TEXT,
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES farmers(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

-- Payments
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id INT NOT NULL,
    payment_month VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    total_milk_amount DECIMAL(10,2) DEFAULT 0,
    total_dana_amount DECIMAL(10,2) DEFAULT 0,
    net_payable DECIMAL(10,2) GENERATED ALWAYS AS (total_milk_amount - total_dana_amount) STORED,
    is_paid TINYINT(1) DEFAULT 0,
    paid_at TIMESTAMP NULL,
    paid_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES farmers(id),
    FOREIGN KEY (paid_by) REFERENCES users(id)
);

-- Pricing formula settings
CREATE TABLE IF NOT EXISTS pricing_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value DECIMAL(10,4) NOT NULL
);
INSERT IGNORE INTO pricing_settings (setting_key, setting_value) VALUES ('fat_price', 10.5), ('snf_price', 3.5);

-- Insert default admin
INSERT INTO users (name, username, password, role) VALUES
('System Admin', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Staff Member', 'staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff');
-- Default password: password

-- Insert sample milk rates
INSERT INTO milk_rates (min_fat, max_fat, rate_per_liter) VALUES
(0.00, 3.00, 35.00),
(3.01, 3.50, 38.00),
(3.51, 4.00, 42.00),
(4.01, 4.50, 46.00),
(4.51, 5.00, 50.00),
(5.01, 6.00, 55.00),
(6.01, 99.99, 60.00);

-- Insert sample farmers
INSERT INTO farmers (code, name, phone, address, password) VALUES
('F001', 'Ram Bahadur Thapa', '9841000001', 'Bhaktapur-5', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('F002', 'Sita Kumari Sharma', '9841000002', 'Lalitpur-10', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('F003', 'Krishna Prasad Adhikari', '9841000003', 'Kathmandu-15', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
-- Farmer login: code as username, password: password 