<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('../config/database.php');

try {
    // Debug: Log attempt to connect
    error_log("[KWH Monitor] Memulai fetch data dashboard");
    
    // Koneksi ke database
    $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Debug: Log successful connection
    error_log("[KWH Monitor] Koneksi database berhasil");

    // 1. Data Pengukuran Terbaru
    $latest_query = "SELECT * FROM kwh_measurements ORDER BY timestamp DESC LIMIT 1";
    error_log("[KWH Monitor] Executing query: " . $latest_query);
    
    $latest_stmt = $conn->query($latest_query);
    $latest_data = $latest_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($latest_data) {
        error_log("[KWH Monitor] Data pengukuran ditemukan: " . json_encode($latest_data));
        
        // Validasi dan format data
        $measurements = [
            'voltage' => [
                'R' => floatval($latest_data['voltage_r'] ?? 0),
                'S' => floatval($latest_data['voltage_s'] ?? 0),
                'T' => floatval($latest_data['voltage_t'] ?? 0)
            ],
            'current' => [
                'R' => floatval($latest_data['current_r'] ?? 0),
                'S' => floatval($latest_data['current_s'] ?? 0),
                'T' => floatval($latest_data['current_t'] ?? 0)
            ],
            'power' => [
                'R' => floatval($latest_data['power_r'] ?? 0),
                'S' => floatval($latest_data['power_s'] ?? 0),
                'T' => floatval($latest_data['power_t'] ?? 0),
                'total' => floatval($latest_data['power_total'] ?? 0)
            ],
            'energy_total' => floatval($latest_data['energy_total'] ?? 0),
            'power_factor' => floatval($latest_data['power_factor'] ?? 0)
        ];
        
        // Validasi nilai numerik
        foreach ($measurements as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    if (!is_numeric($subValue)) {
                        error_log("[KWH Monitor] Nilai tidak valid untuk $key.$subKey: $subValue");
                        $measurements[$key][$subKey] = 0;
                    }
                }
            } else if (!is_numeric($value)) {
                error_log("[KWH Monitor] Nilai tidak valid untuk $key: $value");
                $measurements[$key] = 0;
            }
        }

        // Format response
        $response = [
            'status' => 'success',
            'data' => [
                'current' => [
                    'timestamp' => $latest_data['timestamp'],
                    'meter_id' => $latest_data['meter_id'],
                    'measurements' => $measurements
                ]
            ]
        ];

        // Hitung persentase penggunaan daya (batas maksimum 4000W)
        $maxPower = 4000;
        $response['data']['current']['power_percentage'] = min(100, ($measurements['power']['total'] / $maxPower) * 100);

        // Tambahkan warning jika ada
        $warnings = [];
        
        // Cek tegangan (±10% dari 220V)
        foreach (['R', 'S', 'T'] as $phase) {
            $voltage = $measurements['voltage'][$phase];
            if ($voltage < 198 || $voltage > 242) {
                $warnings[] = [
                    'type' => 'danger',
                    'title' => 'Peringatan Tegangan',
                    'message' => "Fase $phase: $voltage V (Di luar batas normal 198-242V)"
                ];
                error_log("[KWH Monitor] Warning: Tegangan fase $phase tidak normal: $voltage V");
            }
        }

        // Cek power factor
        if ($measurements['power_factor'] < 0.85) {
            $warnings[] = [
                'type' => 'warning',
                'title' => 'Power Factor Rendah',
                'message' => "PF: " . number_format($measurements['power_factor'], 2) . " (Di bawah 0.85)"
            ];
            error_log("[KWH Monitor] Warning: Power factor rendah: " . $measurements['power_factor']);
        }

        $response['data']['warnings'] = $warnings;

        // Tambahkan data historis (24 jam terakhir)
        $history_query = "SELECT * FROM kwh_measurements 
                         WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                         ORDER BY timestamp ASC";
        error_log("[KWH Monitor] Executing history query: " . $history_query);
        
        $history_stmt = $conn->query($history_query);
        $history_data = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("[KWH Monitor] Found " . count($history_data) . " historical records");
        
        $response['data']['history'] = array_map(function($row) {
            return [
                'timestamp' => $row['timestamp'],
                'meter_id' => $row['meter_id'],
                'measurements' => [
                    'voltage' => [
                        'R' => (float)$row['voltage_r'],
                        'S' => (float)$row['voltage_s'],
                        'T' => (float)$row['voltage_t']
                    ],
                    'current' => [
                        'R' => (float)$row['current_r'],
                        'S' => (float)$row['current_s'],
                        'T' => (float)$row['current_t']
                    ],
                    'power' => [
                        'R' => (float)$row['power_r'],
                        'S' => (float)$row['power_s'],
                        'T' => (float)$row['power_t'],
                        'total' => (float)$row['power_total']
                    ],
                    'energy_total' => (float)$row['energy_total'],
                    'power_factor' => (float)$row['power_factor']
                ]
            ];
        }, $history_data);

        // Tambahkan data untuk tabel
        $table_query = "SELECT * FROM kwh_measurements ORDER BY timestamp DESC LIMIT 10";
        error_log("[KWH Monitor] Executing table query: " . $table_query);
        
        $table_stmt = $conn->query($table_query);
        $table_data = $table_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Hitung total records untuk pagination
        $count_stmt = $conn->query("SELECT COUNT(*) as total FROM kwh_measurements");
        $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $response['data']['table'] = [
            'data' => array_map(function($row) {
                return [
                    'meter_id' => $row['meter_id'],
                    'timestamp' => $row['timestamp'],
                    'voltage_r' => (float)$row['voltage_r'],
                    'voltage_s' => (float)$row['voltage_s'],
                    'voltage_t' => (float)$row['voltage_t'],
                    'current_r' => (float)$row['current_r'],
                    'current_s' => (float)$row['current_s'],
                    'current_t' => (float)$row['current_t'],
                    'power_r' => (float)$row['power_r'],
                    'power_s' => (float)$row['power_s'],
                    'power_t' => (float)$row['power_t'],
                    'power_total' => (float)$row['power_total'],
                    'energy_total' => (float)$row['energy_total'],
                    'power_factor' => (float)$row['power_factor']
                ];
            }, $table_data),
            'recordsTotal' => $total_records,
            'recordsFiltered' => $total_records
        ];

        error_log("[KWH Monitor] Response prepared successfully");
        echo json_encode($response, JSON_PRETTY_PRINT);

    } else {
        error_log("[KWH Monitor] No data found in kwh_measurements table");
        
        $response = [
            'status' => 'error',
            'message' => 'Tidak ada data pengukuran'
        ];
        echo json_encode($response, JSON_PRETTY_PRINT);
    }

} catch(PDOException $e) {
    error_log("[KWH Monitor] Database error: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
} catch(Exception $e) {
    error_log("[KWH Monitor] General error: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'General error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
} 