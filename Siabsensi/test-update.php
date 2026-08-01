<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$req = Illuminate\Http\Request::create('/admin/pkkmb-sesi/1', 'PUT', [
    'nama_sesi' => 'Sesi Testing Update '.time(),
    'jam_mulai' => '10:00',
    'jam_selesai' => '11:00',
    'is_active' => 1
]);

$controller = app(\App\Http\Controllers\Admin\PkkmbSesiController::class);

try {
    $response = $controller->update($req, 1);
    echo 'Success! Validation passed. Status: ' . $response->getStatusCode() . "\n";
} catch (\Illuminate\Validation\ValidationException $e) {
    echo 'Validation Error: ' . json_encode($e->errors()) . "\n";
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
