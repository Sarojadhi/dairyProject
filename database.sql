-- ============================================
-- SHREE TRI SHAKTI DAIRY
-- DATABASE
-- ============================================

CREATE DATABASE IF NOT EXISTS dairy_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE dairy_db;


-- ============================================
-- 1. USERS
-- Admin and Staff
-- ============================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    phone VARCHAR(15),
    email VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- ============================================
-- 2. FARMERS
-- ============================================

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


-- ============================================
-- 3. MILK COLLECTION
-- ============================================

CREATE TABLE IF NOT EXISTS milk_collection (
    id INT AUTO_INCREMENT PRIMARY KEY,

    farmer_id INT NOT NULL,
    collection_date DATE NOT NULL,
    shift ENUM('morning', 'evening') NOT NULL,

    litre DECIMAL(8,2) NOT NULL,
    fat DECIMAL(5,2) NOT NULL,
    snf DECIMAL(5,2) NOT NULL,

    rate_per_liter DECIMAL(8,2) NOT NULL,

    total_amount DECIMAL(10,2)
        GENERATED ALWAYS AS (litre * rate_per_liter) STORED,

    recorded_by INT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (farmer_id)
        REFERENCES farmers(id),

    FOREIGN KEY (recorded_by)
        REFERENCES users(id)
);


-- ============================================
-- 4. DANA / COW FEED
-- ============================================

CREATE TABLE IF NOT EXISTS dana_records (
    id INT AUTO_INCREMENT PRIMARY KEY,

    farmer_id INT NOT NULL,
    dana_date DATE NOT NULL,

    bags DECIMAL(8,2) NOT NULL,
    rate_per_bag DECIMAL(8,2) NOT NULL,

    is_paid TINYINT(1) NOT NULL DEFAULT 0,

    total_amount DECIMAL(10,2)
        GENERATED ALWAYS AS (bags * rate_per_bag) STORED,

    notes TEXT,

    recorded_by INT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (farmer_id)
        REFERENCES farmers(id),

    FOREIGN KEY (recorded_by)
        REFERENCES users(id)
);


-- ============================================
-- 5. PAYMENTS
-- ============================================

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,

    farmer_id INT NOT NULL,
    payment_month VARCHAR(7) NOT NULL,

    total_milk_amount DECIMAL(10,2) DEFAULT 0,
    total_dana_amount DECIMAL(10,2) DEFAULT 0,

    net_payable DECIMAL(10,2)
        GENERATED ALWAYS AS (
            total_milk_amount - total_dana_amount
        ) STORED,

    is_paid TINYINT(1) DEFAULT 0,

    paid_at TIMESTAMP NULL,
    paid_by INT,

    notes TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_farmer_month
        (farmer_id, payment_month),

    FOREIGN KEY (farmer_id)
        REFERENCES farmers(id),

    FOREIGN KEY (paid_by)
        REFERENCES users(id)
);


-- ============================================
-- 6. PRICING SETTINGS
-- Fat + SNF pricing formula
-- ============================================

CREATE TABLE IF NOT EXISTS pricing_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,

    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value DECIMAL(10,4) NOT NULL
);

INSERT IGNORE INTO pricing_settings
(setting_key, setting_value)
VALUES
('fat_price', 10.5),
('snf_price', 3.5);


-- ============================================
-- 7. DEFAULT ADMIN & STAFF
-- ============================================

INSERT INTO users
(name, username, password, role)
VALUES
(
    'System Admin',
    'admin',
    '$2y$10$KtX/dccws7W0ZtZ5xkXrMOA0vUw/HjSBF5mr3fjQg2cQohcrgJUVK',
    'admin'
),
(
    'Staff Member',
    'staff',
    '$2y$10$j6.3kPfDwmj467ZxQ0.iLeGi6TCw.ZoKN0rZOvefU19wd6OXys3.O',
    'staff'
);


-- ============================================
-- 8. SAMPLE FARMERS
-- ============================================

INSERT INTO farmers
(code, name, phone, address, password)
VALUES
(
    'F001',
    'Ram Bahadur Thapa',
    '9841000001',
    'Bhaktapur-5',
    '$2y$10$eNzl4jnGx9br2KRaP24WN.GojzIyuCr3ZnhdfkxdMzblKtRU12e/W'
),
(
    'F002',
    'Sita Kumari Sharma',
    '9841000002',
    'Lalitpur-10',
    '$2y$10$eNzl4jnGx9br2KRaP24WN.GojzIyuCr3ZnhdfkxdMzblKtRU12e/W'
),
(
    'F003',
    'Krishna Prasad Adhikari',
    '9841000003',
    'Kathmandu-15',
    '$2y$10$eNzl4jnGx9br2KRaP24WN.GojzIyuCr3ZnhdfkxdMzblKtRU12e/W'
);


-- ============================================
-- LOGIN DETAILS
-- These are only comments for remembering.
-- MySQL will ignore these lines.
-- ============================================

-- ADMIN
-- Username: admin
-- Password: Admin@12

-- STAFF
-- Username: staff
-- Password: Staff@12

-- FARMER
-- Username: F001
-- Password: Farm@123

-- FARMER
-- Username: F002
-- Password: Farm@123

-- FARMER
-- Username: F003
-- Password: Farm@123