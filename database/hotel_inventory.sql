-- =====================================================
-- DATABASE: hotel_inventory_system
-- =====================================================

CREATE DATABASE IF NOT EXISTS hotel_inventory_system;
USE hotel_inventory_system;

-- =====================================================
-- TABLE: roles
-- =====================================================
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- TABLE: users
-- =====================================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fullname VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    role_id INT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- =====================================================
-- TABLE: inventory_items
-- =====================================================
CREATE TABLE inventory_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_name VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    unit VARCHAR(20),
    current_stock INT DEFAULT 0,
    minimum_stock INT DEFAULT 10,
    maximum_stock INT DEFAULT 500,
    unit_price DECIMAL(10,2),
    supplier_id INT,
    location VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- TABLE: stock_movements
-- =====================================================
CREATE TABLE stock_movements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    movement_type ENUM('IN', 'OUT') NOT NULL,
    quantity INT NOT NULL,
    reference_no VARCHAR(100),
    notes TEXT,
    performed_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    FOREIGN KEY (performed_by) REFERENCES users(id)
);

-- =====================================================
-- TABLE: suppliers
-- =====================================================
CREATE TABLE suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- TABLE: purchase_orders
-- =====================================================
CREATE TABLE purchase_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    po_number VARCHAR(50) UNIQUE NOT NULL,
    supplier_id INT NOT NULL,
    order_date DATE NOT NULL,
    expected_delivery DATE,
    total_amount DECIMAL(12,2) DEFAULT 0,
    status ENUM('pending', 'approved', 'rejected', 'delivered', 'confirmed') DEFAULT 'pending',
    created_by INT NOT NULL,
    approved_by INT DEFAULT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- =====================================================
-- TABLE: po_items
-- =====================================================
CREATE TABLE po_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    po_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    received_quantity INT DEFAULT 0,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id)
);

-- =====================================================
-- TABLE: deliveries
-- =====================================================
CREATE TABLE deliveries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    po_id INT NOT NULL,
    delivery_date DATE NOT NULL,
    received_by INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (received_by) REFERENCES users(id)
);

-- =====================================================
-- TABLE: alerts
-- =====================================================
CREATE TABLE alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    alert_type ENUM('low_stock', 'expiry', 'reorder') DEFAULT 'low_stock',
    message TEXT NOT NULL,
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id)
);

-- =====================================================
-- TABLE: system_logs
-- =====================================================
CREATE TABLE system_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(255),
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- =====================================================
-- INSERT ROLES
-- =====================================================
INSERT INTO roles (role_name) VALUES 
('Admin'),
('Hotel Manager'),
('Storekeeper'),
('Procurement Officer'),
('Supplier');

-- =====================================================
-- INSERT DEFAULT USERS (password = password123)
-- =====================================================
INSERT INTO users (fullname, username, password, email, phone, role_id) VALUES
('System Admin', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@hotel.com', '0712345678', 1),
('John Manager', 'manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager@hotel.com', '0712345679', 2),
('James Store', 'storekeeper', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'storekeeper@hotel.com', '0712345680', 3),
('Peter Procurement', 'procurement', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'procurement@hotel.com', '0712345681', 4),
('Sarah Supplier', 'supplier', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'supplier@hotel.com', '0712345682', 5);

-- =====================================================
-- INSERT SAMPLE SUPPLIERS
-- =====================================================
INSERT INTO suppliers (company_name, contact_person, email, phone, address) VALUES
('Fresh Food Supplies Ltd', 'Joseph Mwangi', 'info@freshfood.co.tz', '0712345683', 'Dar es Salaam'),
('Beverages Wholesale', 'Maria John', 'sales@beverages.co.tz', '0712345684', 'Arusha'),
('Hotel Equipments', 'Robert Kimathi', 'robert@hotelequip.co.tz', '0712345685', 'Moshi');

-- =====================================================
-- INSERT SAMPLE INVENTORY ITEMS
-- =====================================================
INSERT INTO inventory_items (item_name, category, unit, current_stock, minimum_stock, unit_price, supplier_id) VALUES
('White Rice', 'Food', 'kg', 150, 30, 2500, 1),
('Cooking Oil', 'Food', 'liters', 80, 20, 3500, 1),
('Beef', 'Food', 'kg', 45, 15, 12000, 1),
('Bottled Water', 'Beverages', 'bottles', 200, 50, 800, 2),
('Soda Mix', 'Beverages', 'cartons', 60, 15, 12000, 2),
('Laundry Soap', 'Cleaning', 'bars', 90, 20, 1800, 3),
('Dishwasher Liquid', 'Cleaning', 'liters', 40, 10, 8500, 3);

-- =====================================================
-- INSERT SAMPLE STOCK MOVEMENTS
-- =====================================================
INSERT INTO stock_movements (item_id, movement_type, quantity, performed_by) VALUES
(1, 'IN', 100, 3),
(1, 'OUT', 25, 3),
(2, 'IN', 50, 3),
(3, 'IN', 30, 3);

-- =====================================================
-- INSERT SAMPLE ALERTS (Low stock)
-- =====================================================
INSERT INTO alerts (item_id, alert_type, message) VALUES
(3, 'low_stock', 'Beef iko chini ya minimum stock (15kg)'),
(4, 'low_stock', 'Bottled Water inakaribia kuisha'),
(7, 'low_stock', 'Dishwasher Liquid inahitaji kununuliwa');


-- Add columns for password reset
ALTER TABLE users ADD COLUMN phone_verified TINYINT DEFAULT 0;
ALTER TABLE users ADD COLUMN reset_token VARCHAR(100) NULL;
ALTER TABLE users ADD COLUMN reset_expires DATETIME NULL;

-- Update existing users
UPDATE users SET phone_verified = 1 WHERE phone IS NOT NULL AND phone != '';

-- Add index for faster lookups
ALTER TABLE users ADD INDEX idx_reset_token (reset_token);

-- Add profile picture column to users table
ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;