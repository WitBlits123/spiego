<?php
// tinker_app_durations.php
// Run with: php Spiego/scripts/tinker_app_durations.php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Event;

$hostname = isset($argv[1]) ? $argv[1] : null;
if (!$hostname) {
    echo "Usage: php Spiego/scripts/tinker_app_durations.php <hostname>\n";
    exit(1);
}

$windowStart = now()->subDay();
$screenEvents = Event::where('hostname', $hostname)
    ->where('event_type', 'screen_time')
    ->where('timestamp', '>', $windowStart)
    ->orderBy('timestamp')
    ->get(['timestamp', 'data']);

$durations = [];
foreach ($screenEvents as $ev) {
    $data = $ev->data;
    $proc = 'Unknown';
    if (is_array($data) && isset($data['process_name'])) $proc = $data['process_name'];
    elseif (is_object($data) && isset($data->process_name)) $proc = $data->process_name;
    $duration = 0;
    if (is_array($data) && isset($data['duration_seconds'])) $duration = intval($data['duration_seconds']);
    elseif (is_object($data) && isset($data->duration_seconds)) $duration = intval($data->duration_seconds);
    if ($duration > 0) {
        if (!isset($durations[$proc])) $durations[$proc] = 0;
        $durations[$proc] += $duration;
    }
}

arsort($durations);
echo "App durations in last 24h (seconds):\n";
foreach ($durations as $proc => $secs) {
    echo "$proc: $secs\n";
}
