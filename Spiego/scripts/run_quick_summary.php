<?php
// Run from project root: php scripts/run_quick_summary.php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Bootstrap the application kernel so Eloquent and Facades work
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Device;
use App\Http\Controllers\DeviceController;

$device = Device::first();
if (!$device) {
    echo "No device found in database\n";
    exit(1);
}
$hostname = $device->hostname;
$ctrl = new DeviceController();
$response = $ctrl->quickSummary($hostname);

if ($response instanceof Illuminate\Http\JsonResponse) {
    echo $response->getContent() . "\n";
} else {
    // Try to print whatever it is
    var_dump($response);
}
