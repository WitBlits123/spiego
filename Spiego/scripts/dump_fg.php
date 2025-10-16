<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Device;
use App\Models\Event;

$d = Device::first();
if (!$d) { echo "NO_DEVICE\n"; exit(0); }
echo "HOSTNAME: {$d->hostname}\n";
$events = Event::where('hostname', $d->hostname)
    ->where('event_type', 'foreground_change')
    ->orderBy('timestamp', 'desc')
    ->take(200)
    ->get();

foreach ($events as $e) {
    echo $e->id . "\t" . $e->timestamp->toIso8601String() . "\t" . json_encode($e->data) . "\n";
}
