<?php
// Test script to verify attendance validation
// Run with: php test_attendance.php

// Simulate /api/sync request
$url = 'http://127.0.0.1:8000/api/sync';

// Test data - attendance outside schedule (should be REJECTED)
$data = [
    'data' => [
        [
            'mahasiswa_id' => '426193494', // Use a valid mahasiswa ID from your database
            'check_in' => date('Y-m-d') . ' 21:00:00', // 9:00 PM - should be rejected
            'status' => 'hadir'
        ]
    ]
];

// Send POST request
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n";
echo json_encode(json_decode($response), JSON_PRETTY_PRINT);
echo "\n\n";

// Check Laravel log for rejection message
echo "=== Recent Laravel Log Entries ===\n";
$logFile = 'Siabsensi/storage/logs/laravel.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recentLines = array_slice($lines, -20);
    foreach ($recentLines as $line) {
        if (strpos($line, 'rejected') !== false || strpos($line, 'Too late') !== false) {
            echo $line;
        }
    }
} else {
    echo "Log file not found\n";
}
