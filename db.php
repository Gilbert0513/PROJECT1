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
    
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'pending'"
];

foreach ($additional_tables as $query) {
    $conn->query($query);
}
?>