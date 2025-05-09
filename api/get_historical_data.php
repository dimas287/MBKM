<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$meter_id = isset($_GET['meter_id']) ? (int)$_GET['meter_id'] : 1;

// Query untuk mengambil data terbaru saja
$query = "SELECT 
            DATE_FORMAT(timestamp, '%H:%i:%s') as time,
            voltage_r,
            voltage_s,
            voltage_t,
            current_r,
            current_s,
            current_t,
            power_r,
            power_s,
            power_t,
            power_total,
            energy_total,
            power_factor,
            timestamp
          FROM kwh_measurements 
          WHERE meter_id = ?
          ORDER BY timestamp DESC
          LIMIT 1";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $meter_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    $data = array(
        'time' => $row['time'],
        'power' => array(
            'R' => round($row['power_r'], 1),
            'S' => round($row['power_s'], 1),
            'T' => round($row['power_t'], 1),
            'total' => round($row['power_total'], 1)
        ),
        'voltage' => array(
            'R' => round($row['voltage_r'], 1),
            'S' => round($row['voltage_s'], 1),
            'T' => round($row['voltage_t'], 1)
        ),
        'current' => array(
            'R' => round($row['current_r'], 1),
            'S' => round($row['current_s'], 1),
            'T' => round($row['current_t'], 1)
        ),
        'energy_total' => round($row['energy_total'], 1),
        'power_factor' => round($row['power_factor'], 2),
        'timestamp' => $row['timestamp']
    );
    
    echo json_encode([
        'status' => 'success',
        'data' => $data
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Tidak ada data tersedia'
    ]);
}

$stmt->close();
$conn->close();
?> 