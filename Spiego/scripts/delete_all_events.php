<?php
// delete_all_events.php
// Run with: php Spiego/scripts/delete_all_events.php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Event;

$count = Event::truncate();
echo "Deleted $count events (all events table truncated)\n";
