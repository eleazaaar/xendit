<?php
require_once 'Database.class.php';

$conn = Database::connection();

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Database connection successful!";

$conn->close();
?>