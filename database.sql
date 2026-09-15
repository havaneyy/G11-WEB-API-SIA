-- Import this file in phpMyAdmin (XAMPP) to set up the database
-- Creates: paymongo_shop database, products table, orders table, order_items table

CREATE DATABASE IF NOT EXISTS paymongo_shop CHARACTER SET utf8mb4;
USE paymongo_shop;

-- PRODUCT SERVICE: product catalog (this is the "content" you edit in /admin)
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price_centavos INT NOT NULL,       -- price stored in centavos (e.g. 35000 = ₱350.00)
    image_url VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ORDER SERVICE: orders + line items
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(20) NOT NULL UNIQUE,   -- e.g. ORD-1001
    status ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
    total_centavos INT NOT NULL,
    checkout_session_id VARCHAR(100) DEFAULT NULL,  -- PayMongo checkout session id
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,        -- snapshot of product name at time of order
    price_centavos INT NOT NULL,       -- snapshot of price at time of order
    quantity INT NOT NULL DEFAULT 1,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Sample content so the storefront isn't empty on first run
INSERT INTO products (name, description, price_centavos, image_url) VALUES
('SIA1 Class T-Shirt', 'Official SIA1 cotton t-shirt, unisex fit.', 35000, 'https://via.placeholder.com/300x300?text=T-Shirt'),
('SIA1 Tote Bag', 'Canvas tote bag with class logo.', 15000, 'https://via.placeholder.com/300x300?text=Tote+Bag'),
('SIA1 Mug', 'Ceramic mug, dishwasher safe.', 12000, 'https://via.placeholder.com/300x300?text=Mug');
