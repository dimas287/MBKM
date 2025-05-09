<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../config/database.php');

try {
    // 1. Ambil data mentah dari database
    $raw_query = "SELECT * FROM kwh_measurements ORDER BY timestamp DESC LIMIT 5";
    $raw_stmt = $conn->query($raw_query);
    $raw_data = $raw_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Ambil data dari get_dashboard_data.php untuk perbandingan
    $dashboard_data = file_get_contents('get_dashboard_data.php');
    $dashboard_response = json_decode($dashboard_data, true);

    // 3. Tampilkan kedua data untuk dibandingkan
    echo json_encode([
        'status' => 'success',
        'raw_database_data' => $raw_data,
        'dashboard_processed_data' => $dashboard_response,
        'comparison' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'query_used' => $raw_query
        ]
    ], JSON_PRETTY_PRINT);

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