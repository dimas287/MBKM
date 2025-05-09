<?php
// Database configuration
$host = 'localhost';     // Host database
$database = 'kwh_meter'; // Nama database
$username = 'root';      // Username database
$password = '';          // Password database (kosong untuk XAMPP default)

try {
    // Buat koneksi PDO
    $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    
    // Set error mode ke exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set karakter encoding
    $conn->exec("SET NAMES utf8mb4");
    
    // Cek apakah tabel kwh_measurements ada
    $stmt = $conn->query("SHOW TABLES LIKE 'kwh_measurements'");
    if ($stmt->rowCount() == 0) {
        // Jika tabel tidak ada, buat tabel
        $sql = file_get_contents(__DIR__ . '/../database/create_tables.sql');
        $conn->exec($sql);
        error_log("Table 'kwh_measurements' created successfully");
    }

    // Log successful connection
    error_log("Connected to database successfully");
    
} catch(PDOException $e) {
    // Log error
    error_log("Connection failed: " . $e->getMessage());
    
    // Return error response jika dipanggil via API
    if (strpos($_SERVER['SCRIPT_NAME'], '/api/') !== false) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Database connection failed'
        ]);
        exit;
    }
    throw $e;
}
?> 
