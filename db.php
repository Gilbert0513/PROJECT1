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
?>
