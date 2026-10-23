-- Update schema for RED Commerce features in CodeCraft Shop
USE codecraft_shop;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(500) DEFAULT 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
    bio TEXT,
    location VARCHAR(100) DEFAULT 'Manila, Philippines',
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add columns to products if missing
ALTER TABLE products ADD COLUMN IF NOT EXISTS category VARCHAR(50) NOT NULL DEFAULT 'Fashion';
ALTER TABLE products ADD COLUMN IF NOT EXISTS likes_count INT NOT NULL DEFAULT 0;
ALTER TABLE products ADD COLUMN IF NOT EXISTS user_id INT DEFAULT 1;

-- Add columns to orders if missing
ALTER TABLE orders ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_name VARCHAR(100) DEFAULT NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_phone VARCHAR(30) DEFAULT NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_address TEXT DEFAULT NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT 'gcash';

-- Likes table
CREATE TABLE IF NOT EXISTS likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY user_product (user_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default demo users if not existing
INSERT IGNORE INTO users (id, username, email, display_name, password_hash, avatar_url, bio, role) VALUES
(1, 'lin_design', 'lin@codecraft.shop', 'Lin_Aesthetic', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300&auto=format&fit=crop&q=80', 'Curating minimalist aesthetic objects, vintage home accents, and modern capsule wardrobes. Local shipping available. 🌿✨', 'user'),
(2, 'admin', 'admin@codecraft.shop', 'Admin Manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=300&auto=format&fit=crop&q=80', 'CodeCraft System Administrator & Database Manager', 'admin');
-- Note: the password_hash is bcrypt for 'password' or demo login can check plain demo fallback

-- Update existing sample products with aesthetic categories and images if they have placeholder
UPDATE products SET 
  category = 'Fashion', 
  image_url = 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=600&auto=format&fit=crop&q=80',
  likes_count = 142
WHERE id = 1;

UPDATE products SET 
  category = 'Fashion', 
  image_url = 'https://images.unsplash.com/photo-1544816155-12df9643f363?w=600&auto=format&fit=crop&q=80',
  likes_count = 89
WHERE id = 2;

UPDATE products SET 
  category = 'Home & Living', 
  image_url = 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=600&auto=format&fit=crop&q=80',
  likes_count = 210
WHERE id = 3;

-- Insert additional aesthetic RED Xiaohongshu items
INSERT IGNORE INTO products (id, name, description, price_centavos, image_url, category, likes_count, user_id) VALUES
(4, 'Minimalist Japanese Ceramic Teapot & Cup Set', 'Hand-crafted ceramic tea set with matte textured glaze. Perfect for quiet morning matcha sessions or slow afternoons. Comes packaged in sustainable wooden box.', 185000, 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?w=600&auto=format&fit=crop&q=80', 'Home & Living', 342, 1),
(5, 'Oversized Cream Linen Shirt | Spring Capsule', 'Breathable 100% French linen button-up. Designed for an effortless relaxed fit. Pairs beautifully with denim or pleated trousers.', 129000, 'https://images.unsplash.com/photo-1598033129183-c4f50c736f10?w=600&auto=format&fit=crop&q=80', 'Fashion', 890, 1),
(6, 'Retro Mechanical Keyboard with Custom Botanical Caps', 'Wireless Bluetooth/Type-C mechanical keyboard. Gateron Yellow lubricating switches with custom botanical dye-sub PBT keycaps.', 495000, 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=600&auto=format&fit=crop&q=80', 'Tech', 1240, 1),
(7, 'Organic Hydrating Glow Face Serum 30ml', 'Formulated with Hyaluronic Acid & Rosehip Seed Oil for deep glass-skin hydration. Vegan, cruelty-free, no added fragrance.', 89000, 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?w=600&auto=format&fit=crop&q=80', 'Beauty', 512, 1),
(8, 'Artisanal Pour-Over Coffee Dripper + Glass Server', 'Heat-resistant borosilicate glass carafe with spiral ribbed V60 dripper. Brew clean, aromatic specialty single-origin coffees at home.', 145000, 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=600&auto=format&fit=crop&q=80', 'Food & Cafe', 678, 1),
(9, 'Vintage Aesthetic Leather Mini Crossbody Bag', 'Full-grain vegetable-tanned leather pouch. Features brass hardware and adjustable shoulder strap. Compact yet holds phone, cards, and keys.', 240000, 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=600&auto=format&fit=crop&q=80', 'Fashion', 980, 1);
