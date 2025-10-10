<?php
// db.php — handles database connection only
$host = 'localhost';
$user = 'root';
$pass = ''; // default password in XAMPP
$dbname = 'foodhouse';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Create additional tables if they don't exist
$additional_tables = [
    "CREATE TABLE IF NOT EXISTS user_favorites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        food_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (food_id) REFERENCES food_items(id),
        UNIQUE KEY unique_favorite (user_id, food_id)
    )",
    
    "ALTER TABLE food_items ADD COLUMN IF NOT EXISTS category VARCHAR(100) DEFAULT 'Uncategorized'",
    
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'pending'",
    
    // Multi-platform tables
    "CREATE TABLE IF NOT EXISTS platform_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        platform ENUM('web', 'mobile') DEFAULT 'web',
        device_info TEXT,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS user_carts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        cart_data TEXT,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_cart (user_id)
    )",
    
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS created_via ENUM('web', 'mobile') DEFAULT 'web'",
    
    "CREATE TABLE IF NOT EXISTS system_metrics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        metric_type VARCHAR(50),
        metric_value DECIMAL(10,2),
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($additional_tables as $query) {
    if (!$conn->query($query)) {
        // Silently continue if table already exists or has errors
        error_log("Table creation warning: " . $conn->error);
    }
}

// Check if essential tables exist, create them if not
$essential_tables = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('customer', 'admin') DEFAULT 'customer',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS food_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        stock INT DEFAULT 0,
        category VARCHAR(100) DEFAULT 'Uncategorized',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_name VARCHAR(100) NOT NULL,
        order_type ENUM('Dine-in', 'Take-out', 'Delivery') DEFAULT 'Dine-in',
        payment_type VARCHAR(50) DEFAULT 'Cash',
        total DECIMAL(10,2) NOT NULL,
        order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        special_instructions TEXT,
        status VARCHAR(20) DEFAULT 'pending',
        created_via ENUM('web', 'mobile') DEFAULT 'web'
    )",
    
    "CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT,
        food_id INT,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (food_id) REFERENCES food_items(id)
    )",
    
    "CREATE TABLE IF NOT EXISTS feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        rating INT NOT NULL,
        comments TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )"
];

foreach ($essential_tables as $query) {
    if (!$conn->query($query)) {
        error_log("Essential table creation error: " . $conn->error);
    }
}
?>