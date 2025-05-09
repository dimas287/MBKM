<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log semua request headers
$headers = getallheaders();
$method = $_SERVER['REQUEST_METHOD'];
$requestData = file_get_contents('php://input');

$debug = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $method,
    'headers' => $headers,
    'get_data' => $_GET,
    'post_data' => $_POST,
    'raw_data' => $requestData,
    'server' => [
        'REQUEST_URI' => $_SERVER['REQUEST_URI'],
        'HTTP_REFERER' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null,
        'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR']
    ]
];

// Log ke file
error_log("Debug Request: " . print_r($debug, true));

// Kirim response
echo json_encode($debug, JSON_PRETTY_PRINT); 