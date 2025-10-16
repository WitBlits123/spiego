<?php
// delete_all_screen_time.php
// Run with: php Spiego/scripts/delete_all_screen_time.php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Event;

$count = Event::where('event_type', 'screen_time')->delete();
echo "Deleted $count screen_time events\n";
