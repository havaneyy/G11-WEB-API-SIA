-- Escrow & Shopee-style Guarantee Migrations for CodeCraft Shop
USE codecraft_shop;

-- 1. Ensure users has phone and program opt-in fields
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(30) DEFAULT '+63 917 123 4567';
ALTER TABLE users ADD COLUMN IF NOT EXISTS opt_in_marketing TINYINT(1) NOT NULL DEFAULT 0;

-- 2. Enhance orders table for escrow & detailed fee breakdowns
ALTER TABLE orders ADD COLUMN IF NOT EXISTS buyer_id INT DEFAULT NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS seller_id INT DEFAULT 1;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS gross_amount INT NOT NULL DEFAULT 0;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS commission_fee INT NOT NULL DEFAULT 0;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS transaction_fee INT NOT NULL DEFAULT 0;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_fee INT NOT NULL DEFAULT 0;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS growth_support_fee INT NOT NULL DEFAULT 0;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS processing_fee INT NOT NULL DEFAULT 0;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS program_fee INT NOT NULL DEFAULT 0;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS net_amount INT NOT NULL DEFAULT 0;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivered_at DATETIME DEFAULT NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS guarantee_expires_at DATETIME DEFAULT NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS released_at DATETIME DEFAULT NULL;

-- Modify status enum in orders
ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'paid', 'shipped', 'delivered', 'completed', 'refund_requested', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending';

-- Add indexes on orders if not existing
ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_orders_status (status);
ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_orders_guarantee (guarantee_expires_at);
ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_orders_seller (seller_id);
ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_orders_buyer (buyer_id);

-- 3. Escrow transactions ledger (Append-only)
CREATE TABLE IF NOT EXISTS escrow_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NULL,
    seller_id INT NOT NULL,
    type ENUM('capture', 'release_to_seller', 'refund', 'withdrawal', 'withdrawal_recredit') NOT NULL,
    amount INT NOT NULL,            -- minor units (centavos)
    balance_after INT NOT NULL,     -- minor units (centavos)
    description VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_escrow_seller (seller_id),
    INDEX idx_escrow_created (created_at),
    UNIQUE KEY unique_order_release (order_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Seller Wallets
CREATE TABLE IF NOT EXISTS seller_wallets (
    seller_id INT PRIMARY KEY,
    available_balance INT NOT NULL DEFAULT 0,  -- in centavos
    pending_balance INT NOT NULL DEFAULT 0,    -- in centavos (held in escrow)
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Bank Accounts
CREATE TABLE IF NOT EXISTS bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    bank_name VARCHAR(100) NOT NULL,
    account_number_encrypted VARCHAR(255) NOT NULL,
    account_number_masked VARCHAR(50) NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    is_partner_bank TINYINT(1) NOT NULL DEFAULT 0,  -- e.g. BDO / GCash Partner
    is_verified TINYINT(1) NOT NULL DEFAULT 1,
    verified_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bank_seller (seller_id),
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Withdrawals
CREATE TABLE IF NOT EXISTS withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    bank_account_id INT NOT NULL,
    amount INT NOT NULL,             -- minor units (centavos)
    fee INT NOT NULL DEFAULT 0,      -- minor units (centavos)
    method ENUM('auto', 'manual') NOT NULL DEFAULT 'manual',
    status ENUM('requested', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'requested',
    is_weekend_queued TINYINT(1) NOT NULL DEFAULT 0,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL,
    INDEX idx_with_seller (seller_id),
    INDEX idx_with_status (status),
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Withdrawal Settings
CREATE TABLE IF NOT EXISTS withdrawal_settings (
    seller_id INT PRIMARY KEY,
    frequency ENUM('daily', 'weekly', 'biweekly', 'monthly') NOT NULL DEFAULT 'weekly',
    day_of_week TINYINT NOT NULL DEFAULT 1, -- 1=Monday
    auto_enabled TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Order State Logs (Audit Trail)
CREATE TABLE IF NOT EXISTS order_state_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    actor_id INT NULL,
    previous_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NOT NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_logs (order_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initialize wallets and default bank accounts for demo users
INSERT IGNORE INTO seller_wallets (seller_id, available_balance, pending_balance) VALUES
(1, 452000, 185000),  -- ₱4,520.00 available, ₱1,850.00 in escrow
(2, 1250000, 350000), -- Admin / Store
(3, 150000, 75000);

INSERT IGNORE INTO bank_accounts (id, seller_id, bank_name, account_number_encrypted, account_number_masked, account_name, is_partner_bank, is_verified, verified_at) VALUES
(1, 1, 'BDO Unibank (Partner)', 'enc_09812345678', '•••• •••• 5678', 'Lin Aesthetic', 1, 1, NOW()),
(2, 1, 'GCash Partner Wallet', 'enc_09171234567', '0917 ••• 4567', 'Lin Aesthetic', 1, 1, NOW()),
(3, 2, 'Bank of the Philippine Islands (BPI)', 'enc_1234567890', '•••• •••• 7890', 'Admin Manager', 0, 1, NOW());

INSERT IGNORE INTO withdrawal_settings (seller_id, frequency, day_of_week, auto_enabled) VALUES
(1, 'weekly', 1, 1),
(2, 'weekly', 1, 0),
(3, 'weekly', 1, 0);

-- Backfill existing orders with fee calculation if gross_amount is 0
UPDATE orders SET 
  gross_amount = total_centavos,
  buyer_id = COALESCE(user_id, 1),
  seller_id = 1,
  commission_fee = ROUND(total_centavos * 0.05),
  transaction_fee = ROUND(total_centavos * 0.0224),
  shipping_fee = LEAST(10000, ROUND(total_centavos * 0.05)),
  growth_support_fee = ROUND(total_centavos * 0.0125),
  processing_fee = 500,
  program_fee = ROUND(total_centavos * 0.03),
  net_amount = total_centavos - (
    ROUND(total_centavos * 0.05) + 
    ROUND(total_centavos * 0.0224) + 
    LEAST(10000, ROUND(total_centavos * 0.05)) + 
    ROUND(total_centavos * 0.0125) + 
    500 + 
    ROUND(total_centavos * 0.03)
  )
WHERE gross_amount = 0;
