<?php
// delete_invalid_screen_time.php
// Run with: php Spiego/scripts/delete_invalid_screen_time.php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Event;

$count = Event::where('event_type', 'screen_time')
    ->where(function($q) {
        $q->whereRaw("CAST(json_extract(data, '$.duration_seconds') AS INTEGER) <= 0")
          ->orWhereNull('data');
    })
    ->delete();

echo "Deleted $count invalid screen_time events\n";
