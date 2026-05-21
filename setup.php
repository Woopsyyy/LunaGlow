<?php
/**
 * Luna Glow — Database Setup & Seeder
 * Run once: http://localhost/xampp/Project/LunaGlow/setup.php
 */

$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'lunaglow';

try {
    // Connect without DB first to create it
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbName`");
} catch (PDOException $e) {
    die("❌ Connection failed: " . $e->getMessage());
}

$log = [];

function run(PDO $pdo, string $sql, string $label): void {
    global $log;
    try {
        $pdo->exec($sql);
        $log[] = "✅ $label";
    } catch (PDOException $e) {
        $log[] = "❌ $label — " . $e->getMessage();
    }
}

// ── Drop & recreate tables ─────────────────────────────

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
foreach ([
    'newsletter_subscribers','reviews','wishlist_items','cart_items','order_items',
    'orders','coupons','banners','product_images','products','addresses','admins','users','categories'
] as $t) {
    $pdo->exec("DROP TABLE IF EXISTS `$t`");
}
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

// ── Create Tables ──────────────────────────────────────

run($pdo, "CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    image VARCHAR(255),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Table: categories");

run($pdo, "CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    avatar VARCHAR(255),
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Table: users");

run($pdo, "CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'Administrator',
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Table: admins");

run($pdo, "CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    price DECIMAL(10,2) NOT NULL,
    original_price DECIMAL(10,2),
    description TEXT,
    ingredients TEXT,
    delivery_info VARCHAR(255) DEFAULT '3-5 business days',
    stock INT DEFAULT 50,
    image VARCHAR(255),
    is_featured TINYINT DEFAULT 0,
    is_bestseller TINYINT DEFAULT 0,
    is_new TINYINT DEFAULT 1,
    rating DECIMAL(3,2) DEFAULT 4.50,
    review_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
)", "Table: products");

run($pdo, "CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)", "Table: product_images");

run($pdo, "CREATE TABLE addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    label VARCHAR(50) DEFAULT 'Home',
    full_name VARCHAR(100),
    phone VARCHAR(20),
    address_line1 VARCHAR(255),
    address_line2 VARCHAR(255),
    city VARCHAR(100),
    province VARCHAR(100),
    zip_code VARCHAR(10),
    is_default TINYINT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)", "Table: addresses");

run($pdo, "CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cart (user_id, product_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)", "Table: cart_items");

run($pdo, "CREATE TABLE wishlist_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_wishlist (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)", "Table: wishlist_items");

run($pdo, "CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    discount_type ENUM('percent','fixed') DEFAULT 'percent',
    discount_value DECIMAL(10,2) NOT NULL,
    min_order DECIMAL(10,2) DEFAULT 0,
    max_uses INT DEFAULT 100,
    used_count INT DEFAULT 0,
    expires_at DATE,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Table: coupons");

run($pdo, "CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(20) NOT NULL UNIQUE,
    user_id INT,
    shipping_name VARCHAR(100),
    shipping_email VARCHAR(150),
    shipping_phone VARCHAR(20),
    shipping_address VARCHAR(255),
    shipping_city VARCHAR(100),
    shipping_province VARCHAR(100),
    shipping_zip VARCHAR(10),
    payment_method ENUM('cod','gcash') DEFAULT 'cod',
    subtotal DECIMAL(10,2) DEFAULT 0,
    discount DECIMAL(10,2) DEFAULT 0,
    shipping_fee DECIMAL(10,2) DEFAULT 150,
    total DECIMAL(10,2) DEFAULT 0,
    coupon_code VARCHAR(50),
    status ENUM('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
    notes TEXT,
    gcash_ref VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)", "Table: orders");

run($pdo, "CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT,
    product_name VARCHAR(200),
    product_image VARCHAR(255),
    price DECIMAL(10,2),
    quantity INT,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
)", "Table: order_items");

run($pdo, "CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT,
    reviewer_name VARCHAR(100),
    rating INT DEFAULT 5,
    title VARCHAR(200),
    body TEXT,
    is_approved TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)", "Table: reviews");

run($pdo, "CREATE TABLE banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200),
    subtitle VARCHAR(255),
    cta_text VARCHAR(100),
    cta_link VARCHAR(255),
    image VARCHAR(255),
    badge VARCHAR(100),
    sort_order INT DEFAULT 0,
    is_active TINYINT DEFAULT 1
)", "Table: banners");

run($pdo, "CREATE TABLE newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Table: newsletter_subscribers");

// ── Seed Data ──────────────────────────────────────────

// Admin
$adminPass = password_hash('admin123', PASSWORD_DEFAULT);
run($pdo, "INSERT INTO admins (name, email, password, role, is_active) VALUES
    ('Luna Admin', 'admin@lunaglow.com', '$adminPass', 'Administrator', 1)", "Seed: admin user");

// Demo user
$demoPass = password_hash('demo123', PASSWORD_DEFAULT);
run($pdo, "INSERT INTO users (name, email, password, phone) VALUES
    ('Sofia Reyes', 'demo@lunaglow.com', '$demoPass', '09171234567')", "Seed: demo user");

// Categories
run($pdo, "INSERT INTO categories (name, slug, description, image, sort_order) VALUES
    ('Lipstick',     'lipstick',     'Velvety, long-lasting lip color',       'https://images.unsplash.com/photo-1586495777744-4413f21062fa?w=400', 1),
    ('Foundation',   'foundation',   'Flawless skin-like coverage',           'https://images.unsplash.com/photo-1596462502278-27bfdc403348?w=400', 2),
    ('Blush',        'blush',        'Rosy cheek perfection',                 'https://images.unsplash.com/photo-1526045478516-99145907023c?w=400', 3),
    ('Mascara',      'mascara',      'Bold, dramatic lashes',                 'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?w=400', 4),
    ('Powder',       'powder',       'Weightless matte finish',               'https://images.unsplash.com/photo-1631214500004-9cf868bdc2c2?w=400', 5),
    ('Skincare',     'skincare',     'Glow-boosting skincare essentials',     'https://images.unsplash.com/photo-1556228453-efd6c1ff04f6?w=400', 6),
    ('Makeup Sets',  'makeup-sets',  'Complete beauty collections',           'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?w=400', 7)
", "Seed: categories");

// Products
run($pdo, "INSERT INTO products (category_id, name, slug, price, original_price, description, ingredients, stock, image, is_featured, is_bestseller, is_new, rating, review_count) VALUES
    (1, 'Velvet Rose Lipstick',     'velvet-rose-lipstick',     499,  699,  'A rich, creamy lipstick with long-lasting velvet finish. Stays put for 12 hours without drying. Infused with vitamin E for soft, nourished lips.', 'Dimethicone, Vitamin E, Castor Oil, Rose Extract, Beeswax', 80, 'https://images.unsplash.com/photo-1586495777744-4413f21062fa?w=600', 1, 1, 0, 4.8, 124),

    (1, 'Berry Glam Lipstick',      'berry-glam-lipstick',      549,  799,  'Deep berry tones with a satin finish. A bold statement lip color that lasts all day.', 'Shea Butter, Beeswax, Carmine, Vitamin C', 60, 'https://images.unsplash.com/photo-1599733594230-6b823276d0b5?w=600', 0, 1, 1, 4.7, 89),
    (1, 'Nude Matte Lipstick',      'nude-matte-lipstick',      449,  0,    'The perfect everyday nude. Ultra-matte formula that stays comfortable all day long.', 'Silica, Castor Oil, Candelilla Wax, Jojoba Oil', 100, 'https://images.unsplash.com/photo-1607748851687-ba9a10438621?w=600', 0, 0, 1, 4.6, 67),
    (2, 'Glow Serum Foundation',    'glow-serum-foundation',    899,  1299, 'Skin-perfecting serum foundation with buildable coverage. Infused with hyaluronic acid for all-day hydration.', 'Hyaluronic Acid, Niacinamide, SPF 30, Glycerin', 55, 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?w=600', 1, 1, 0, 4.9, 201),
    (2, 'Satin Skin Foundation',    'satin-skin-foundation',    799,  999,  'Medium-to-full coverage with a natural satin finish. 24-hour wear formula.', 'Titanium Dioxide, Zinc Oxide, Glycerin, Aloe Vera', 70, 'https://images.unsplash.com/photo-1631214500004-9cf868bdc2c2?w=600', 0, 0, 1, 4.5, 88),
    (3, 'Peach Blossom Blush',      'peach-blossom-blush',      399,  599,  'A soft, peachy blush that gives you the perfect sun-kissed flush. Buildable color payoff.', 'Mica, Silica, Rose Hip Extract, Vitamin E', 90, 'https://images.unsplash.com/photo-1526045478516-99145907023c?w=600', 1, 1, 0, 4.7, 156),
    (3, 'Rose Gold Highlighter',    'rose-gold-highlighter',    499,  699,  'Finely milled rose gold highlighter for a blinding, editorial glow.', 'Mica, Gold Pearl, Silica, Vitamin C', 75, 'https://images.unsplash.com/photo-1583241799307-b3ef9b60e5da?w=600', 0, 1, 1, 4.8, 112),
    (4, 'Volume Lash Mascara',      'volume-lash-mascara',      599,  849,  'Dramatically volumizing mascara with a curved brush for maximum lift. Smudge-proof, 24-hour wear.', 'Beeswax, Carnauba Wax, Iron Oxide, Panthenol', 85, 'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?w=600', 0, 1, 0, 4.6, 178),
    (5, 'Silky Setting Powder',     'silky-setting-powder',     649,  899,  'Ultra-fine translucent powder that sets makeup and controls shine for 12 hours. Blurs pores and fine lines.', 'Silica, Mica, Corn Starch, Vitamin E', 65, 'https://images.unsplash.com/photo-1631214524020-3c69b4b0ffb7?w=600', 0, 0, 1, 4.5, 95),
    (6, 'Rose Glow Serum',          'rose-glow-serum',          1299, 1799, 'Concentrated rose extract serum with Vitamin C and Niacinamide. Brightens, hydrates, and plumps skin overnight.', 'Rose Hip Oil, Vitamin C, Niacinamide, Hyaluronic Acid, Retinol', 40, 'https://images.unsplash.com/photo-1556228453-efd6c1ff04f6?w=600', 1, 0, 1, 4.9, 245),
    (6, 'Hydra-Glow Moisturizer',   'hydra-glow-moisturizer',   999,  1399, 'Rich, nourishing moisturizer that delivers 72-hour hydration with a beautiful natural glow finish.', 'Ceramides, Squalane, Peptides, Hyaluronic Acid, Shea Butter', 50, 'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?w=600', 0, 1, 0, 4.7, 132),
    (7, 'The Glow Edit Set',        'the-glow-edit-set',        2499, 3499, 'The ultimate glow kit! Includes Velvet Rose Lipstick, Peach Blossom Blush, Rose Gold Highlighter, and Silky Setting Powder in a luxe gift box.', 'See individual products', 30, 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?w=600', 1, 1, 0, 4.9, 310)
", "Seed: products");

// Banners
run($pdo, "INSERT INTO banners (title, subtitle, cta_text, cta_link, image, badge, sort_order, is_active) VALUES
    ('Glow Beyond Beauty',        'Discover premium makeup essentials crafted for confidence, elegance, and your most radiant self.',  'Shop Now',          '/xampp/Project/LunaGlow/shop.php',                          'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?w=1200', 'New Collection',  1, 1),
    ('The Rose Glow Edit',        'Our best-selling rose collection is back — limited edition. Soft, feminine, and utterly luminous.',  'Explore Collection','https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?w=1200', 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?w=1200', 'Limited Edition', 2, 1),
    ('Skincare Meets Makeup',     'Glow-boosting formulas that care for your skin while you slay. Beauty that is good for you.',        'Discover Skincare', '/xampp/Project/LunaGlow/shop.php?category=skincare',         'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?w=1200', 'Best Sellers',    3, 1)
", "Seed: banners");

// Coupons
run($pdo, "INSERT INTO coupons (code, discount_type, discount_value, min_order, max_uses, expires_at) VALUES
    ('GLOW20',    'percent', 20.00,  500.00, 200, DATE_ADD(NOW(), INTERVAL 90 DAY)),
    ('WELCOME50', 'fixed',   50.00,  300.00, 500, DATE_ADD(NOW(), INTERVAL 180 DAY)),
    ('LUNA10',    'percent', 10.00,  0.00,   999, DATE_ADD(NOW(), INTERVAL 365 DAY))
", "Seed: coupons");

// Reviews
run($pdo, "INSERT INTO reviews (product_id, reviewer_name, rating, title, body, is_approved) VALUES
    (1, 'Isabella M.',   5, 'Absolutely Love This!',         'The color is gorgeous and it lasts all day. I have gotten so many compliments!', 1),
    (1, 'Camille R.',    5, 'My HG Lipstick',                'Been searching for the perfect red for years. This is it. Rich pigment, stays on through coffee and lunch!', 1),
    (4, 'Andrea P.',     5, 'Best Foundation I Own',         'Full coverage that looks like skin. The serum formula is so hydrating — no dry patches at all.', 1),
    (4, 'Jasmine T.',    4, 'Great but oxidizes slightly',   'Love the coverage and finish. It does oxidize about half a shade after wear but still beautiful.', 1),
    (6, 'Maria Sofia K.',5, 'Instant Glow!',                 'One pump and my cheeks look so natural and healthy. The peach tone is perfect for my NC30 skin.', 1),
    (10,'Diana C.',      5, 'Transformed My Skin',           'After 3 weeks my hyperpigmentation has visibly faded. Worth every peso. Will repurchase forever!', 1),
    (12,'Reina A.',      5, 'Perfect Gift Set!',             'Bought this as a birthday gift and my friend was obsessed. The packaging is so luxurious.', 1)
", "Seed: reviews");

// Product images (extra gallery images)
run($pdo, "INSERT INTO product_images (product_id, image, sort_order) VALUES
    (1, 'https://images.unsplash.com/photo-1599733594230-6b823276d0b5?w=600', 1),
    (1, 'https://images.unsplash.com/photo-1607748851687-ba9a10438621?w=600', 2),
    (4, 'https://images.unsplash.com/photo-1631214500004-9cf868bdc2c2?w=600', 1),
    (6, 'https://images.unsplash.com/photo-1583241799307-b3ef9b60e5da?w=600', 1),
    (10,'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?w=600', 1)
", "Seed: product_images");

// Sample order for demo user
run($pdo, "INSERT INTO orders (order_number, user_id, shipping_name, shipping_email, shipping_phone, shipping_address, shipping_city, shipping_province, shipping_zip, payment_method, subtotal, shipping_fee, total, status)
VALUES ('LG-DEMO01-2026', 1, 'Sofia Reyes', 'demo@lunaglow.com', '09171234567', '123 Glow Street, Malate', 'Manila', 'Metro Manila', '1004', 'cod', 1398.00, 0.00, 1398.00, 'delivered')", "Seed: sample order");

run($pdo, "INSERT INTO order_items (order_id, product_id, product_name, product_image, price, quantity) VALUES
    (1, 1, 'Velvet Rose Lipstick', 'https://images.unsplash.com/photo-1586495777744-4413f21062fa?w=600', 499.00, 1),
    (1, 4, 'Glow Serum Foundation', 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?w=600', 899.00, 1)
", "Seed: order items");

// Update review count on products
run($pdo, "UPDATE products p SET review_count = (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id AND r.is_approved = 1)", "Update: review counts");

// ── Output ─────────────────────────────────────────────

$success = count(array_filter($log, fn($l) => str_starts_with($l, '✅')));
$errors  = count(array_filter($log, fn($l) => str_starts_with($l, '❌')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Luna Glow — Database Setup</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Poppins', sans-serif; background: #fdf8f5; color: #2a1f28; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
  .card { background: white; border-radius: 24px; box-shadow: 0 20px 60px rgba(180,100,130,.12); max-width: 640px; width: 100%; padding: 48px; }
  .logo { font-size: 2rem; font-weight: 700; color: #d4789a; margin-bottom: 4px; }
  .subtitle { color: #9a8a96; font-size: .9rem; margin-bottom: 32px; }
  .stats { display: flex; gap: 16px; margin-bottom: 32px; }
  .stat { flex: 1; border-radius: 16px; padding: 20px; text-align: center; }
  .stat-success { background: #f0fdf4; border: 1px solid #86efac; }
  .stat-error   { background: #fef2f2; border: 1px solid #fca5a5; }
  .stat-num { font-size: 2rem; font-weight: 700; }
  .stat-success .stat-num { color: #16a34a; }
  .stat-error   .stat-num { color: #dc2626; }
  .stat-label { font-size: .8rem; color: #666; }
  .log { background: #fdf8f5; border-radius: 14px; padding: 20px; max-height: 300px; overflow-y: auto; font-size: .82rem; line-height: 2; }
  .creds { background: linear-gradient(135deg, #fce8ed, #fdf8f5); border-radius: 14px; padding: 20px; margin-top: 24px; }
  .creds h3 { font-size: .9rem; font-weight: 600; color: #d4789a; margin-bottom: 12px; }
  .cred-row { display: flex; gap: 16px; justify-content: space-between; font-size: .85rem; margin-bottom: 8px; }
  .cred-row strong { color: #5a4a56; }
  .actions { margin-top: 28px; display: flex; gap: 12px; }
  .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 50px; font-family: inherit; font-size: .9rem; font-weight: 500; text-decoration: none; border: none; cursor: pointer; transition: .3s; }
  .btn-primary { background: #d4789a; color: white; box-shadow: 0 8px 20px rgba(212,120,154,.3); }
  .btn-primary:hover { background: #c0607f; transform: translateY(-2px); }
  .btn-secondary { background: #f7ede8; color: #5a4a56; }
  .btn-secondary:hover { background: #f0d8d0; }
  .warning { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 12px; padding: 14px 18px; font-size: .82rem; color: #92400e; margin-top: 20px; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">✨ Luna Glow</div>
  <div class="subtitle">Database Setup Complete</div>

  <div class="stats">
    <div class="stat stat-success">
      <div class="stat-num"><?= $success ?></div>
      <div class="stat-label">Successful</div>
    </div>
    <div class="stat stat-error">
      <div class="stat-num"><?= $errors ?></div>
      <div class="stat-label">Errors</div>
    </div>
  </div>

  <div class="log"><?= implode('<br>', $log) ?></div>

  <div class="creds">
    <h3>🔑 Login Credentials</h3>
    <div class="cred-row"><span>👤 Admin</span><strong>admin@lunaglow.com</strong><span>/ admin123</span></div>
    <div class="cred-row"><span>👤 Demo User</span><strong>demo@lunaglow.com</strong><span>/ demo123</span></div>
    <div class="cred-row"><span>🎟️ Coupons</span><strong>GLOW20</strong><span>, WELCOME50, LUNA10</span></div>
  </div>

  <div class="actions">
    <a href="/xampp/Project/LunaGlow/index.php" class="btn btn-primary">Visit Luna Glow</a>
    <a href="/xampp/Project/LunaGlow/admin/index.php" class="btn btn-secondary">Admin Panel</a>
    <a href="/phpmyadmin" class="btn btn-secondary">phpMyAdmin</a>
  </div>

  <div class="warning">⚠️ <strong>Security:</strong> Delete or rename this file after setup to prevent it from being re-run.</div>
</div>
</body>
</html>
