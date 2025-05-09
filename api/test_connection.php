<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // 1. Cek koneksi ke database
    require_once('../config/database.php');
    
    // 2. Cek struktur tabel
    $tableInfo = [];
    $stmt = $conn->query("DESCRIBE kwh_measurements");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $tableInfo[] = $row;
    }
    
    // 3. Cek data terakhir
    $stmt = $conn->query("SELECT * FROM kwh_measurements ORDER BY timestamp DESC LIMIT 1");
    $lastData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Koneksi berhasil',
        'database_info' => [
            'host' => $host,
            'database' => $database,
            'table_structure' => $tableInfo,
            'last_data' => $lastData
        ]
    ]);

} catch(PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'General error: ' . $e->getMessage()
    ]);
} 