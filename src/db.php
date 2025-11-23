<?php
// Database connection using PDO

$host = "localhost";
$dbname = "course";        // database name
$username = "admin";       // database username
$password = "password123"; // database password

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $username,
        $password
    );

    // Throw exceptions on database errors
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
