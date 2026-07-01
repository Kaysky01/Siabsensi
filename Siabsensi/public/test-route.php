<?php
// Test file untuk memastikan web server berjalan
echo json_encode([
    'status' => 'Web server berjalan!',
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'
]);
