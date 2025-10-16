<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Carbon\Carbon;

$t = '2025-10-15T17:47:14.636815+00:00';
$tz = config('app.timezone');
$converted = Carbon::parse($t)->setTimezone($tz)->toIso8601String();
echo "orig: $t\nconverted: $converted\napp.tz: $tz\n";