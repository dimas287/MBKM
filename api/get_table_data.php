<?php
header('Content-Type: application/json');
require_once('../config/database.php');

try {
    // Koneksi ke database
    $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Parameter untuk paginasi dan pencarian
    $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
    $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
    $search = isset($_GET['search']['value']) ? $_GET['search']['value'] : '';
    
    // Query untuk total records
    $totalQuery = "SELECT COUNT(*) as total FROM ";
    $totalStmt = $conn->query($totalQuery);
    $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Query dasar
    $baseQuery = "SELECT id, meter_id, timestamp, 
                         voltage_r, voltage_s, voltage_t,
                         current_r, current_s, current_t,
                         power_r, power_s, power_t,
                         power_total, energy_total, power_factor
                  FROM kwh_measurements";
    
    // Tambahkan kondisi pencarian jika ada
    $searchCondition = "";
    if (!empty($search)) {
        $searchCondition = " WHERE meter_id LIKE :search 
                           OR timestamp LIKE :search
                           OR power_total LIKE :search
                           OR energy_total LIKE :search";
    }
    
    // Query untuk data yang difilter
    $filteredQuery = $baseQuery . $searchCondition;
    if (!empty($search)) {
        $filteredStmt = $conn->prepare($filteredQuery);
        $searchParam = "%$search%";
        $filteredStmt->bindParam(':search', $searchParam);
        $filteredStmt->execute();
    } else {
        $filteredStmt = $conn->query($filteredQuery);
    }
    
    $filtered = $filteredStmt->rowCount();
    
    // Query final dengan limit
    $finalQuery = $filteredQuery . " ORDER BY timestamp DESC LIMIT :start, :length";
    $stmt = $conn->prepare($finalQuery);
    $stmt->bindParam(':start', $start, PDO::PARAM_INT);
    $stmt->bindParam(':length', $length, PDO::PARAM_INT);
    if (!empty($search)) {
        $stmt->bindParam(':search', $searchParam);
    }
    $stmt->execute();
    
    // Format data untuk DataTables
    $data = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data[] = [
            'id' => $row['id'],
            'meter_id' => $row['meter_id'],
            'timestamp' => $row['timestamp'],
            'voltage' => [
                'R' => number_format($row['voltage_r'], 1),
                'S' => number_format($row['voltage_s'], 1),
                'T' => number_format($row['voltage_t'], 1)
            ],
            'current' => [
                'R' => number_format($row['current_r'], 2),
                'S' => number_format($row['current_s'], 2),
                'T' => number_format($row['current_t'], 2)
            ],
            'power' => [
                'R' => number_format($row['power_r'], 0),
                'S' => number_format($row['power_s'], 0),
                'T' => number_format($row['power_t'], 0),
                'total' => number_format($row['power_total'], 0)
            ],
            'energy_total' => number_format($row['energy_total'], 2),
            'power_factor' => number_format($row['power_factor'], 2)
        ];
    }
    
    echo json_encode([
        'draw' => isset($_GET['draw']) ? intval($_GET['draw']) : 1,
        'recordsTotal' => $total,
        'recordsFiltered' => $filtered,
        'data' => $data
    ]);

} catch(PDOException $e) {
    echo json_encode([
        'error' => true,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} 