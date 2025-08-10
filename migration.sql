
CREATE DATABASE IF NOT EXISTS ecommerce_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ecommerce_db;

-- ตาราง products (สินค้า)
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(500) COMMENT 'URL รูปภาพจาก Discord',
    category VARCHAR(100),
    stock INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ตาราง users (ผู้ใช้)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    line_id VARCHAR(100),
    building VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ตาราง orders (คำสั่งซื้อ)
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT,
    customer_name VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_line_id VARCHAR(100),
    customer_building VARCHAR(100),
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'paid', 'confirmed', 'preparing', 'ready', 'delivered', 'cancelled') DEFAULT 'pending',
    payment_method VARCHAR(50) DEFAULT 'promptpay',
    qr_code_url VARCHAR(500) COMMENT 'URL QR Code PromptPay',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ตาราง order_items (รายการสินค้าในคำสั่งซื้อ)
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ตาราง receipts (สลิปการโอน)
CREATE TABLE receipts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    receipt_url VARCHAR(500) COMMENT 'URL สลิปจาก Discord',
    filename VARCHAR(255),
    file_size INT,
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at TIMESTAMP NULL,
    verified_by VARCHAR(100),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- ตาราง admin_logs (บันทึกการทำงานของแอดมิน)
CREATE TABLE admin_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_name VARCHAR(100) NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ใส่ข้อมูลตัวอย่าง
INSERT INTO products (name, description, price, category, stock, image_url) VALUES
('ข้าวผัดกุ้ง', 'ข้าวผัดกุ้งสดใหม่ รสชาติอร่อย', 60.00, 'อาหารจานหลัก', 50, NULL),
('ต้มยำกุ้ง', 'ต้มยำกุ้งต้นตำรับไทย เปรื้อยจี๊ด', 80.00, 'ต้ม/แกง', 30, NULL),
('ส้มตำไทย', 'ส้มตำไทยแท้ รสชาติเข้มข้น', 45.00, 'ยำ/สลัด', 40, NULL),
('น้ำส้ม', 'น้ำส้มสดใหม่ หวานซ่า', 25.00, 'เครื่องดื่ม', 100, NULL),
('ไอติม', 'ไอติมหลากรส เย็นฉ่ำ', 30.00, 'ของหวาน', 60, NULL);

-- สร้าง Index เพื่อประสิทธิภาพ
CREATE INDEX idx_products_status ON products(status);
CREATE INDEX idx_products_category ON products(category);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_user_id ON orders(user_id);
CREATE INDEX idx_orders_created_at ON orders(created_at);
CREATE INDEX idx_order_items_order_id ON order_items(order_id);
CREATE INDEX idx_receipts_order_id ON receipts(order_id);
CREATE INDEX idx_users_phone ON users(phone);