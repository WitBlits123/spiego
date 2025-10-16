<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Event;
use Illuminate\Http\Request;
use Carbon\Carbon;

class EventController extends Controller
{
    /**
     * Receive and store events from clients
     * POST /api/events
     */
    public function store(Request $request)
    {
        // Verify authentication
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $token = substr($authHeader, 7);
        $expectedKey = env('AUTH_KEY', 'your-secret-auth-key-change-me');

        if ($token !== $expectedKey) {
            return response()->json(['error' => 'Invalid authentication key'], 401);
        }

        try {
            $events = $request->input('events', $request->input()); // Support both formats
            if (!is_array($events)) {
                $events = [$events];
            }

            $receivedCount = 0;

            foreach ($events as $eventData) {
                $eventType = $eventData['type'] ?? 'unknown';
                $timestamp = $eventData['timestamp'] ?? now()->toIso8601String();
                $hostname = $eventData['hostname'] ?? 'unknown';

                // Parse incoming timestamp and convert to application timezone so DB stores the correct local time
                try {
                    $parsedTs = Carbon::parse($timestamp)->setTimezone(config('app.timezone'));
                } catch (\Exception $e) {
                    // Fallback to now in app timezone
                    $parsedTs = now()->setTimezone(config('app.timezone'));
                }

                // Store event
                Event::create([
                    'timestamp' => $parsedTs,
                    'event_type' => $eventType,
                    'hostname' => $hostname,
                    'data' => $eventData,
                ]);

                $receivedCount++;

                // Update device metadata if this is a metadata event
                if ($eventType === 'metadata') {
                    Device::updateOrCreate(
                        ['hostname' => $hostname],
                        [
                            'platform' => $eventData['platform'] ?? 'unknown',
                            'processor' => $eventData['processor'] ?? null,
                            'python_version' => $eventData['python_version'] ?? 'unknown',
                            'cpu_count' => $eventData['cpu_count'] ?? null,
                            'memory_total' => $eventData['memory_total'] ?? null,
                            'mac_addresses' => $eventData['mac_addresses'] ?? [],
                            'last_seen' => Carbon::parse($timestamp)->setTimezone(config('app.timezone')),
                        ]
                    );
                } else {
                    // Only update last_seen, platform, python_version if not a metadata event
                    Device::updateOrCreate(
                        ['hostname' => $hostname],
                        [
                            'last_seen' => Carbon::parse($timestamp)->setTimezone(config('app.timezone')),
                            'platform' => $eventData['platform'] ?? 'Unknown',
                            'python_version' => $eventData['python_version'] ?? '3.x',
                        ]
                    );
                }
            }

            return response()->json([
                'status' => 'success',
                'received' => $receivedCount
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
