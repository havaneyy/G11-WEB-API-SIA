-- CodeCraft Shop — Complete Database Schema & Seeds
-- Integrated with Escrow & Shopee-style Guarantee System
-- Minor units (centavos) used for all monetary values (integers, no floats)

CREATE DATABASE IF NOT EXISTS `codecraft_shop` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `codecraft_shop`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `display_name` VARCHAR(100) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `avatar_url` VARCHAR(500) DEFAULT 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
    `bio` TEXT,
    `phone` VARCHAR(30) DEFAULT '+63 917 123 4567',
    `location` VARCHAR(100) DEFAULT 'Manila, Philippines',
    `role` ENUM('user', 'admin') DEFAULT 'user',
    `opt_in_marketing` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Products / Crafts Table
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT,
    `price_centavos` INT NOT NULL,       -- stored in centavos (e.g. 185000 = ₱1,850.00)
    `image_url` VARCHAR(500) DEFAULT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'Fashion',
    `likes_count` INT NOT NULL DEFAULT 0,
    `user_id` INT DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_prod_category` (`category`),
    INDEX `idx_prod_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Likes Table
CREATE TABLE IF NOT EXISTS `likes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `user_product` (`user_id`, `product_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Orders Table (With Escrow & Detailed Platform Fee Breakdown)
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_code` VARCHAR(20) NOT NULL UNIQUE,
    `buyer_id` INT DEFAULT NULL,
    `seller_id` INT NOT NULL DEFAULT 1,
    `gross_amount` INT NOT NULL DEFAULT 0,          -- Centavos
    `commission_fee` INT NOT NULL DEFAULT 0,        -- 1% - 6% (standard 5%)
    `transaction_fee` INT NOT NULL DEFAULT 0,       -- 2.24% processing fee
    `shipping_fee` INT NOT NULL DEFAULT 0,          -- 4.48% - 5.6% (capped at ₱100 = 10,000 cents)
    `growth_support_fee` INT NOT NULL DEFAULT 0,    -- 1.00% - 1.50% (standard 1.25%)
    `processing_fee` INT NOT NULL DEFAULT 0,        -- Flat ₱5.00 (500 cents)
    `program_fee` INT NOT NULL DEFAULT 0,           -- Optional 3% - 4% if opted into campaigns
    `net_amount` INT NOT NULL DEFAULT 0,            -- gross_amount - total deductions (computed at order time)
    `status` ENUM('pending', 'paid', 'shipped', 'delivered', 'completed', 'refund_requested', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending',
    `total_centavos` INT NOT NULL,                  -- Total paid by buyer
    `checkout_session_id` VARCHAR(100) DEFAULT NULL,
    `shipping_name` VARCHAR(100) DEFAULT NULL,
    `shipping_phone` VARCHAR(30) DEFAULT NULL,
    `shipping_address` TEXT DEFAULT NULL,
    `payment_method` VARCHAR(50) DEFAULT 'gcash',
    `delivered_at` DATETIME DEFAULT NULL,
    `guarantee_expires_at` DATETIME DEFAULT NULL,   -- delivered_at + 7 days
    `released_at` DATETIME DEFAULT NULL,            -- timestamp when funds released to seller wallet
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_orders_status` (`status`),
    INDEX `idx_orders_guarantee` (`guarantee_expires_at`),
    INDEX `idx_orders_seller` (`seller_id`),
    INDEX `idx_orders_buyer` (`buyer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Order Items Table
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `price_centavos` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Escrow Transactions Ledger (Append-Only)
CREATE TABLE IF NOT EXISTS `escrow_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NULL,
    `seller_id` INT NOT NULL,
    `type` ENUM('capture', 'release_to_seller', 'refund', 'withdrawal', 'withdrawal_recredit') NOT NULL,
    `amount` INT NOT NULL,            -- minor units (centavos)
    `balance_after` INT NOT NULL,     -- minor units (centavos)
    `description` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_escrow_seller` (`seller_id`),
    INDEX `idx_escrow_created` (`created_at`),
    UNIQUE KEY `unique_order_release` (`order_id`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Seller Wallets
CREATE TABLE IF NOT EXISTS `seller_wallets` (
    `seller_id` INT PRIMARY KEY,
    `available_balance` INT NOT NULL DEFAULT 0,  -- in centavos
    `pending_balance` INT NOT NULL DEFAULT 0,    -- held in escrow
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Bank Accounts
CREATE TABLE IF NOT EXISTS `bank_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `seller_id` INT NOT NULL,
    `bank_name` VARCHAR(100) NOT NULL,
    `account_number_encrypted` VARCHAR(255) NOT NULL,
    `account_number_masked` VARCHAR(50) NOT NULL,
    `account_name` VARCHAR(100) NOT NULL,
    `is_partner_bank` TINYINT(1) NOT NULL DEFAULT 0,
    `is_verified` TINYINT(1) NOT NULL DEFAULT 1,
    `verified_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_bank_seller` (`seller_id`),
    FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Withdrawals
CREATE TABLE IF NOT EXISTS `withdrawals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `seller_id` INT NOT NULL,
    `bank_account_id` INT NOT NULL,
    `amount` INT NOT NULL,             -- minor units (centavos)
    `fee` INT NOT NULL DEFAULT 0,      -- minor units (centavos)
    `method` ENUM('auto', 'manual') NOT NULL DEFAULT 'manual',
    `status` ENUM('requested', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'requested',
    `is_weekend_queued` TINYINT(1) NOT NULL DEFAULT 0,
    `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `processed_at` DATETIME DEFAULT NULL,
    INDEX `idx_with_seller` (`seller_id`),
    INDEX `idx_with_status` (`status`),
    FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Withdrawal Settings
CREATE TABLE IF NOT EXISTS `withdrawal_settings` (
    `seller_id` INT PRIMARY KEY,
    `frequency` ENUM('daily', 'weekly', 'biweekly', 'monthly') NOT NULL DEFAULT 'weekly',
    `day_of_week` TINYINT NOT NULL DEFAULT 1,
    `auto_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Order State Logs (Audit Trail)
CREATE TABLE IF NOT EXISTS `order_state_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `actor_id` INT NULL,
    `previous_status` VARCHAR(50) NULL,
    `new_status` VARCHAR(50) NOT NULL,
    `note` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_order_logs` (`order_id`),
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Demo Data
INSERT IGNORE INTO `users` (`id`, `username`, `email`, `display_name`, `password_hash`, `avatar_url`, `bio`, `phone`, `role`, `opt_in_marketing`) VALUES
(1, 'lin_design', 'lin@codecraft.shop', 'Lin_Aesthetic', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300&auto=format&fit=crop&q=80', 'Curating minimalist aesthetic objects, vintage home accents, and modern capsule wardrobes. Local shipping available. 🌿✨', '+63 917 123 4567', 'user', 1),
(2, 'admin', 'admin@codecraft.shop', 'Admin Manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=300&auto=format&fit=crop&q=80', 'CodeCraft System Administrator & Database Manager', '+63 917 000 0000', 'admin', 0);

INSERT IGNORE INTO `seller_wallets` (`seller_id`, `available_balance`, `pending_balance`) VALUES
(1, 452000, 185000),
(2, 1250000, 350000);

INSERT IGNORE INTO `bank_accounts` (`id`, `seller_id`, `bank_name`, `account_number_encrypted`, `account_number_masked`, `account_name`, `is_partner_bank`, `is_verified`, `verified_at`) VALUES
(1, 1, 'BDO Unibank (Partner)', 'enc_09812345678', '•••• •••• 5678', 'Lin Aesthetic', 1, 1, NOW()),
(2, 1, 'GCash Partner Wallet', 'enc_09171234567', '0917 ••• 4567', 'Lin Aesthetic', 1, 1, NOW()),
(3, 2, 'Bank of the Philippine Islands (BPI)', 'enc_1234567890', '•••• •••• 7890', 'Admin Manager', 0, 1, NOW());

INSERT IGNORE INTO `withdrawal_settings` (`seller_id`, `frequency`, `day_of_week`, `auto_enabled`) VALUES
(1, 'weekly', 1, 1),
(2, 'weekly', 1, 0);

-- Seed Products
INSERT IGNORE INTO `products` (`id`, `name`, `description`, `price_centavos`, `image_url`, `category`, `likes_count`, `user_id`) VALUES
(1, 'SIA1 Class T-Shirt', 'Official SIA1 cotton t-shirt, unisex fit.', 35000, 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=600&auto=format&fit=crop&q=80', 'Fashion', 142, 1),
(2, 'SIA1 Tote Bag', 'Canvas tote bag with class logo.', 15000, 'https://images.unsplash.com/photo-1544816155-12df9643f363?w=600&auto=format&fit=crop&q=80', 'Fashion', 89, 1),
(3, 'SIA1 Mug', 'Ceramic mug, dishwasher safe.', 12000, 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=600&auto=format&fit=crop&q=80', 'Home & Living', 210, 1),
(4, 'Minimalist Japanese Ceramic Teapot & Cup Set', 'Hand-crafted ceramic tea set with matte textured glaze. Perfect for quiet morning matcha sessions or slow afternoons. Comes packaged in sustainable wooden box.', 185000, 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?w=600&auto=format&fit=crop&q=80', 'Home & Living', 342, 1),
(5, 'Oversized Cream Linen Shirt | Spring Capsule', 'Breathable 100% French linen button-up. Designed for an effortless relaxed fit. Pairs beautifully with denim or pleated trousers.', 129000, 'https://images.unsplash.com/photo-1598033129183-c4f50c736f10?w=600&auto=format&fit=crop&q=80', 'Fashion', 890, 1),
(6, 'Retro Mechanical Keyboard with Custom Botanical Caps', 'Wireless Bluetooth/Type-C mechanical keyboard. Gateron Yellow lubricating switches with custom botanical dye-sub PBT keycaps.', 495000, 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=600&auto=format&fit=crop&q=80', 'Tech', 1240, 1),
(7, 'Organic Hydrating Glow Face Serum 30ml', 'Formulated with Hyaluronic Acid & Rosehip Seed Oil for deep glass-skin hydration. Vegan, cruelty-free, no added fragrance.', 89000, 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?w=600&auto=format&fit=crop&q=80', 'Beauty', 512, 1),
(8, 'Artisanal Pour-Over Coffee Dripper + Glass Server', 'Heat-resistant borosilicate glass carafe with spiral ribbed V60 dripper. Brew clean, aromatic specialty single-origin coffees at home.', 145000, 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=600&auto=format&fit=crop&q=80', 'Food & Cafe', 678, 1),
(9, 'Vintage Aesthetic Leather Mini Crossbody Bag', 'Full-grain vegetable-tanned leather pouch. Features brass hardware and adjustable shoulder strap. Compact yet holds phone, cards, and keys.', 240000, 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=600&auto=format&fit=crop&q=80', 'Fashion', 980, 1);
