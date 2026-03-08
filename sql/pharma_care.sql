-- Pharma Care PMS - Complete Database Schema
-- Version: 1.0.0

CREATE DATABASE IF NOT EXISTS pharma_care CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pharma_care;

-- Users table (admin, owner, manager)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    full_name VARCHAR(150),
    role ENUM('admin','owner','manager') NOT NULL DEFAULT 'manager',
    company_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Subscriptions table
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    duration_days INT NOT NULL DEFAULT 30,
    features TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Companies table
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(30),
    address TEXT,
    logo VARCHAR(255),
    owner_id INT,
    subscription_id INT,
    marquee_message TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Company subscriptions history
CREATE TABLE IF NOT EXISTS company_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    subscription_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Alerts table
CREATE TABLE IF NOT EXISTS alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('warning','info','success','danger') NOT NULL DEFAULT 'info',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Footer settings
CREATE TABLE IF NOT EXISTS footer_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Units table
CREATE TABLE IF NOT EXISTS units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    abbreviation VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    category_id INT,
    unit_id INT,
    purchase_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    selling_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock_quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    low_stock_threshold DECIMAL(10,2) NOT NULL DEFAULT 10.00,
    expiry_date DATE NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Doctors table
CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    phone VARCHAR(30),
    specialization VARCHAR(150),
    commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    notes TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sales table
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    manager_id INT,
    doctor_id INT,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    customer_name VARCHAR(150),
    customer_phone VARCHAR(30),
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    final_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method ENUM('cash','card','mobile','credit') NOT NULL DEFAULT 'cash',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Sale items table
CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT,
    product_name VARCHAR(200),
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    purchase_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- SEED DATA
-- =============================================

-- Admin user (password: admin123)
INSERT IGNORE INTO users (username, password, email, full_name, role, company_id, is_active)
VALUES ('admin', '$2y$12$2CWGX7X2mAHoZgTUR6iXl.6ZTIUSV7UngCh.2/.ewQElwkziu9jF.', 'admin@pharmacare.com', 'System Administrator', 'admin', NULL, 1);

-- Sample subscription plans
INSERT IGNORE INTO subscriptions (name, price, duration_days, features, is_active) VALUES
('Basic Plan', 29.99, 30, 'Up to 5 Users\nUp to 500 Products\nBasic Reports\nEmail Support', 1),
('Professional Plan', 59.99, 30, 'Up to 20 Users\nUnlimited Products\nAdvanced Reports\nPriority Support\nFinancial Statements', 1),
('Enterprise Plan', 99.99, 30, 'Unlimited Users\nUnlimited Products\nFull Reports Suite\n24/7 Support\nFinancial Statements\nCustom Branding', 1);

-- Default footer content
INSERT IGNORE INTO footer_settings (id, content) VALUES
(1, '&copy; 2024 <strong>Pharma Care</strong>. All rights reserved. | Pharmacy Management System');

-- Sample alerts
INSERT IGNORE INTO alerts (title, message, type, is_active) VALUES
('Welcome to Pharma Care PMS', 'Welcome to the Pharma Care Pharmacy Management System. Please configure your company settings to get started.', 'info', 1),
('System Update', 'The system has been updated to the latest version. All features are working normally.', 'success', 1);
