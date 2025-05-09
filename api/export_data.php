<?php
require_once('../config/database.php');

// Validasi parameter
$format = $_GET['format'] ?? '';
$dateStart = $_GET['dateStart'] ?? '';
$dateEnd = $_GET['dateEnd'] ?? '';
$filterType = $_GET['filterType'] ?? 'all';

try {
    // Koneksi ke database
    $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Query dasar
    $query = "SELECT * FROM kwh_measurements WHERE 1=1";
    $params = [];

    // Tambahkan filter tanggal jika ada
    if (!empty($dateStart)) {
        $query .= " AND DATE(timestamp) >= :dateStart";
        $params[':dateStart'] = $dateStart;
    }
    if (!empty($dateEnd)) {
        $query .= " AND DATE(timestamp) <= :dateEnd";
        $params[':dateEnd'] = $dateEnd;
    }

    // Tambahkan filter berdasarkan tipe
    switch ($filterType) {
        case 'voltage_warning':
            $query .= " AND (voltage_r NOT BETWEEN 198 AND 242 OR voltage_s NOT BETWEEN 198 AND 242 OR voltage_t NOT BETWEEN 198 AND 242)";
            break;
        case 'current_warning':
            $query .= " AND (current_r > 20 OR current_s > 20 OR current_t > 20)";
            break;
        case 'power_warning':
            $query .= " AND power_total > 3600";
            break;
    }

    $query .= " ORDER BY timestamp DESC";
    
    // Eksekusi query
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($format === 'excel') {
        // Set header untuk CSV (Excel)
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename="data_kwh_meter.csv"');
        
        // Buka output stream
        $output = fopen('php://output', 'w');
        
        // Header kolom
        $headers = [
            'ID Meter', 'Waktu', 
            'Tegangan R (V)', 'Tegangan S (V)', 'Tegangan T (V)',
            'Arus R (A)', 'Arus S (A)', 'Arus T (A)',
            'Daya R (W)', 'Daya S (W)', 'Daya T (W)',
            'Total Daya (W)', 'Total Energi (kWh)', 'Power Factor'
        ];
        fputcsv($output, $headers);
        
        // Tulis data
        foreach ($data as $row) {
            $rowData = [
                $row['meter_id'],
                $row['timestamp'],
                number_format($row['voltage_r'], 1),
                number_format($row['voltage_s'], 1),
                number_format($row['voltage_t'], 1),
                number_format($row['current_r'], 2),
                number_format($row['current_s'], 2),
                number_format($row['current_t'], 2),
                number_format($row['power_r'], 0),
                number_format($row['power_s'], 0),
                number_format($row['power_t'], 0),
                number_format($row['power_total'], 0),
                number_format($row['energy_total'], 2),
                number_format($row['power_factor'], 2)
            ];
            fputcsv($output, $rowData);
        }
        
        fclose($output);

    } elseif ($format === 'pdf') {
        // Set header untuk HTML (yang bisa di-print ke PDF oleh browser)
        header('Content-Type: text/html');
        header('Content-Disposition: inline;filename="data_kwh_meter.html"');
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Data KWH Meter</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
                th { background-color: #f5f5f5; }
                h2 { text-align: center; }
                .info { margin-bottom: 20px; }
                @media print {
                    body { margin: 0; }
                    button { display: none; }
                }
            </style>
        </head>
        <body>
            <button onclick="window.print()" style="padding: 10px; margin-bottom: 20px;">Print PDF</button>
            <h2>Laporan Data KWH Meter</h2>
            <div class="info">
                <p>Periode: ' . ($dateStart ? $dateStart : 'Semua') . ' s/d ' . ($dateEnd ? $dateEnd : 'Sekarang') . '</p>
                <p>Filter: ' . ucfirst(str_replace('_', ' ', $filterType)) . '</p>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>V-R</th>
                        <th>V-S</th>
                        <th>V-T</th>
                        <th>I-R</th>
                        <th>I-S</th>
                        <th>I-T</th>
                        <th>P-Tot</th>
                        <th>E-Tot</th>
                        <th>PF</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($data as $row) {
            echo '<tr>';
            echo '<td>' . $row['timestamp'] . '</td>';
            echo '<td>' . number_format($row['voltage_r'], 1) . '</td>';
            echo '<td>' . number_format($row['voltage_s'], 1) . '</td>';
            echo '<td>' . number_format($row['voltage_t'], 1) . '</td>';
            echo '<td>' . number_format($row['current_r'], 2) . '</td>';
            echo '<td>' . number_format($row['current_s'], 2) . '</td>';
            echo '<td>' . number_format($row['current_t'], 2) . '</td>';
            echo '<td>' . number_format($row['power_total'], 0) . '</td>';
            echo '<td>' . number_format($row['energy_total'], 2) . '</td>';
            echo '<td>' . number_format($row['power_factor'], 2) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>
            </table>
        </body>
        </html>';
    }

} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
} 