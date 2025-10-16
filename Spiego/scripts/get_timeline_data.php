<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\DeviceController;

$hostname = $argv[1] ?? null;
$range = $argv[2] ?? '12h';
if (!$hostname) { echo "Usage: php get_timeline_data.php <hostname> [range]\n"; exit(1); }

$controller = new DeviceController();
$request = Request::create('/dummy', 'GET', ['range' => $range]);
$response = $controller->timelineData($hostname, $request);
// timelineData returns a JsonResponse
echo json_encode($response->getData(), JSON_PRETTY_PRINT) . "\n";
