<?php
// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Include database connection
require_once '../config/database.php';

// Get date range from request parameters
$startDate = isset($_GET['start']) ? $_GET['start'] : null;
$endDate = isset($_GET['end']) ? $_GET['end'] : null;

// Validate date parameters
if (!$startDate || !$endDate) {
    // Default to last 30 days if no dates provided
    $endDate = date('Y-m-d H:i:s');
    $startDate = date('Y-m-d H:i:s', strtotime('-30 days'));
}

try {
    $pdo = Database::getConnection();
    
    // Format date strings for database query
    $formattedStartDate = date('Y-m-d H:i:s', strtotime($startDate));
    $formattedEndDate = date('Y-m-d H:i:s', strtotime($endDate));
    
    // Get daily consumption data
    $dailyStmt = $pdo->prepare("
        SELECT 
            DATE(timestamp) as date,
            MAX(energy_total) as energy_value
        FROM 
            kwh_measurements
        WHERE 
            timestamp BETWEEN :start_date AND :end_date
        GROUP BY 
            DATE(timestamp)
        ORDER BY 
            date ASC
    ");
    
    $dailyStmt->bindParam(':start_date', $formattedStartDate);
    $dailyStmt->bindParam(':end_date', $formattedEndDate);
    $dailyStmt->execute();
    
    $dailyData = [];
    $prevEnergy = 0;
    
    // Process daily consumption data
    while ($row = $dailyStmt->fetch(PDO::FETCH_ASSOC)) {
        // Calculate daily consumption (difference from previous day)
        $consumption = $row['energy_value'] - $prevEnergy;
        if ($consumption < 0) {
            // If negative, assume it's a reset or new reading
            $consumption = $row['energy_value'];
        }
        
        $dailyData[] = [
            'date' => date('d/m/Y', strtotime($row['date'])),
            'value' => round($consumption, 2)
        ];
        
        $prevEnergy = $row['energy_value'];
    }
    
    // Get summary statistics
    $summaryStmt = $pdo->prepare("
        SELECT 
            MAX(energy_total) as total_energy,
            AVG(power_total) as avg_power,
            AVG(power_factor) as avg_pf,
            (
                SELECT COUNT(*) FROM kwh_measurements 
                WHERE 
                    timestamp BETWEEN :start_date2 AND :end_date2
                    AND (
                        (voltage_r < 198 OR voltage_r > 242)
                        OR (voltage_s < 198 OR voltage_s > 242)
                        OR (voltage_t < 198 OR voltage_t > 242)
                        OR (power_factor < 0.85)
                    )
            ) as warning_count,
            AVG(power_r) as avg_power_r,
            AVG(power_s) as avg_power_s,
            AVG(power_t) as avg_power_t
        FROM 
            kwh_measurements
        WHERE 
            timestamp BETWEEN :start_date AND :end_date
    ");
    
    $summaryStmt->bindParam(':start_date', $formattedStartDate);
    $summaryStmt->bindParam(':end_date', $formattedEndDate);
    $summaryStmt->bindParam(':start_date2', $formattedStartDate);
    $summaryStmt->bindParam(':end_date2', $formattedEndDate);
    $summaryStmt->execute();
    
    $summaryData = $summaryStmt->fetch(PDO::FETCH_ASSOC);
    
    // Prepare phase distribution data
    $phaseDistribution = [
        'phase_r' => floatval($summaryData['avg_power_r'] ?? 0),
        'phase_s' => floatval($summaryData['avg_power_s'] ?? 0),
        'phase_t' => floatval($summaryData['avg_power_t'] ?? 0)
    ];
    
    // Prepare response data
    $responseData = [
        'total_energy' => floatval($summaryData['total_energy'] ?? 0),
        'avg_power' => floatval($summaryData['avg_power'] ?? 0),
        'avg_pf' => floatval($summaryData['avg_pf'] ?? 0),
        'warning_count' => intval($summaryData['warning_count'] ?? 0),
        'daily_consumption' => $dailyData,
        'phase_distribution' => $phaseDistribution
    ];
    
    // Calculate cost based on energy value (using rate of 1699.53 per kWh)
    $rate = 1699.53;
    $responseData['total_cost'] = floatval($summaryData['total_energy'] ?? 0) * $rate;
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'data' => $responseData,
        'message' => 'Data retrieved successfully'
    ]);
    
} catch (PDOException $e) {
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'General error: ' . $e->getMessage()
    ]);
} 