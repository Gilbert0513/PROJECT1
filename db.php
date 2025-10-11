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

// ONLY CREATE TABLES IF THEY DON'T EXIST - NO DROPPING!
$essential_tables = [
    // Create users table
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('customer', 'admin') DEFAULT 'customer',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    // Create food_items table
    "CREATE TABLE IF NOT EXISTS food_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        stock INT DEFAULT 0,
        category VARCHAR(100) DEFAULT 'Uncategorized',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    // Create customer_order table with ALL required columns
    "CREATE TABLE IF NOT EXISTS customer_order (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_name VARCHAR(100) NOT NULL,
        order_type ENUM('Dine-in', 'Take-out') DEFAULT 'Dine-in',
        payment_type ENUM('Cash', 'GCash', 'Credit Card') DEFAULT 'Cash',
        special_instructions TEXT,
        subtotal DECIMAL(10,2) NOT NULL,
        service_fee DECIMAL(10,2) NOT NULL,
        total DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'preparing', 'ready', 'completed', 'cancelled') DEFAULT 'pending',
        order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    // Create customer_order_items table with food_name column
    "CREATE TABLE IF NOT EXISTS customer_order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT,
        food_id INT,
        food_name VARCHAR(255) NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES customer_order(id) ON DELETE CASCADE,
        FOREIGN KEY (food_id) REFERENCES food_items(id)
    )",
    
    // Create tables table for dine-in functionality
    "CREATE TABLE IF NOT EXISTS tables (
        id INT AUTO_INCREMENT PRIMARY KEY,
        table_number INT NOT NULL,
        capacity INT DEFAULT 4,
        status ENUM('available', 'occupied') DEFAULT 'available',
        UNIQUE KEY unique_table (table_number)
    )",
    
    // Create other tables
    "CREATE TABLE IF NOT EXISTS feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        rating INT NOT NULL,
        comments TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )",
    
    "CREATE TABLE IF NOT EXISTS user_favorites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        food_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (food_id) REFERENCES food_items(id),
        UNIQUE KEY unique_favorite (user_id, food_id)
    )",
    
    "CREATE TABLE IF NOT EXISTS user_carts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        cart_data TEXT,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_cart (user_id)
    )"
];

// Execute table creation (only creates if they don't exist)
foreach ($essential_tables as $query) {
    if (!$conn->query($query)) {
        error_log("Table creation error: " . $conn->error);
    }
}

// Insert sample tables ONLY if table is empty
$checkTables = $conn->query("SELECT COUNT(*) as count FROM tables");
if ($checkTables) {
    $row = $checkTables->fetch_assoc();
    if ($row['count'] == 0) {
        $sampleTables = [
            "INSERT INTO tables (table_number, capacity) VALUES (1, 4)",
            "INSERT INTO tables (table_number, capacity) VALUES (2, 4)",
            "INSERT INTO tables (table_number, capacity) VALUES (3, 4)",
            "INSERT INTO tables (table_number, capacity) VALUES (4, 4)",
            "INSERT INTO tables (table_number, capacity) VALUES (5, 4)",
            "INSERT INTO tables (table_number, capacity) VALUES (6, 6)",
            "INSERT INTO tables (table_number, capacity) VALUES (7, 6)",
            "INSERT INTO tables (table_number, capacity) VALUES (8, 2)",
            "INSERT INTO tables (table_number, capacity) VALUES (9, 2)",
            "INSERT INTO tables (table_number, capacity) VALUES (10, 8)"
        ];
        
        foreach ($sampleTables as $tableQuery) {
            $conn->query($tableQuery);
        }
        
        error_log("Sample tables inserted");
    }
}

// Insert sample food items ONLY if table is empty
$checkFoodItems = $conn->query("SELECT COUNT(*) as count FROM food_items");
if ($checkFoodItems) {
    $row = $checkFoodItems->fetch_assoc();
    if ($row['count'] == 0) {
        $sampleFoods = [
            "INSERT INTO food_items (name, price, stock, category, description) VALUES ('Classic Burger', 120.00, 50, 'Main Course', 'Juicy beef burger with fresh vegetables')",
            "INSERT INTO food_items (name, price, stock, category, description) VALUES ('Pepperoni Pizza', 250.00, 30, 'Main Course', 'Classic pizza with pepperoni and cheese')",
            "INSERT INTO food_items (name, price, stock, category, description) VALUES ('French Fries', 60.00, 100, 'Side Dish', 'Crispy golden fries')",
            "INSERT INTO food_items (name, price, stock, category, description) VALUES ('Cola', 35.00, 200, 'Beverage', 'Refreshing carbonated drink')",
            "INSERT INTO food_items (name, price, stock, category, description) VALUES ('Vanilla Ice Cream', 80.00, 40, 'Dessert', 'Creamy vanilla ice cream')"
        ];
        
        foreach ($sampleFoods as $foodQuery) {
            $conn->query($foodQuery);
        }
        
        error_log("Sample food items inserted");
    }
}

// Debug: Check current orders
$order_count = $conn->query("SELECT COUNT(*) as count FROM customer_order")->fetch_assoc();
error_log("Current orders in database: " . $order_count['count']);
?>