<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     * GET /api/dashboard/stats
     */
    public function stats(Request $request)
    {
        $deviceCount = Device::count();
        
        $activeDevices = Device::where('last_seen', '>', Carbon::now()->subHours(24))->count();
        
        $eventCount24h = Event::where('timestamp', '>', Carbon::now()->subHours(24))->count();
        
        $totalEvents = Event::count();

        return response()->json([
            'device_count' => $deviceCount,
            'active_devices' => $activeDevices,
            'event_count_24h' => $eventCount24h,
            'total_events' => $totalEvents,
        ]);
    }

    /**
     * Get all devices
     * GET /api/dashboard/devices
     */
    public function devices(Request $request)
    {
        $devices = Device::orderBy('last_seen', 'desc')->get();

        return response()->json([
            'devices' => $devices,
        ]);
    }

    /**
     * Get activity timeline
     * GET /api/dashboard/activity_timeline
     */
    public function activityTimeline(Request $request)
    {
        $hours = $request->input('hours', 24);
        $hostname = $request->input('hostname');

        $query = Event::select(
                DB::raw("strftime('%Y-%m-%d %H:00:00', timestamp) as hour"),
                'event_type',
                DB::raw('COUNT(*) as count')
            )
            ->where('timestamp', '>', Carbon::now()->subHours($hours))
            ->groupBy('hour', 'event_type')
            ->orderBy('hour');

        if ($hostname) {
            $query->where('hostname', $hostname);
        }

        $results = $query->get();

        $timeline = [];
        foreach ($results as $row) {
            if (!isset($timeline[$row->hour])) {
                $timeline[$row->hour] = [];
            }
            $timeline[$row->hour][$row->event_type] = $row->count;
        }

        return response()->json([
            'timeline' => $timeline,
        ]);
    }

    /**
     * Get top domains and apps
     * GET /api/dashboard/top_domains
     */
    public function topDomains(Request $request)
    {
        $limit = $request->input('limit', 20);
        $hours = $request->input('hours', 24);
        $hostname = $request->input('hostname');

        $query = Event::where('timestamp', '>', Carbon::now()->subHours($hours))
            ->where('event_type', 'foreground_change');

        if ($hostname) {
            $query->where('hostname', $hostname);
        }

        $events = $query->get();

        $domainCounts = [];
        $appCounts = [];

        foreach ($events as $event) {
            $data = $event->data;
            
            // Count apps (process names)
            if (isset($data['process_name']) && $data['process_name']) {
                $appName = $data['process_name'];
                $appCounts[$appName] = ($appCounts[$appName] ?? 0) + 1;
            }

            // Count domains
            if (isset($data['url']) && $data['url']) {
                $url = $data['url'];
                $domain = parse_url($url, PHP_URL_HOST) ?? $url;
                if ($domain) {
                    $domainCounts[$domain] = ($domainCounts[$domain] ?? 0) + 1;
                }
            }
        }

        // Sort and combine
        arsort($appCounts);
        arsort($domainCounts);

        $combined = [];

        // Add top applications
        foreach (array_slice($appCounts, 0, $limit, true) as $app => $count) {
            $combined[] = [
                'domain' => $app,
                'count' => $count,
                'type' => 'app',
            ];
        }

        // Add top domains if we have space
        $remaining = $limit - count($combined);
        if ($remaining > 0) {
            foreach (array_slice($domainCounts, 0, $remaining, true) as $domain => $count) {
                $combined[] = [
                    'domain' => $domain,
                    'count' => $count,
                    'type' => 'url',
                ];
            }
        }

        return response()->json([
            'domains' => $combined,
        ]);
    }

    /**
     * Get recent events with pagination
     * GET /api/dashboard/recent_events
     */
    public function recentEvents(Request $request)
    {
        $limit = $request->input('limit', 50);
        $page = $request->input('page', 1);
        $hostname = $request->input('hostname');
        $eventType = $request->input('type');
        $hours = $request->input('hours', 24);
        $appFilter = $request->input('app');

        $query = Event::where('timestamp', '>', Carbon::now()->subHours($hours));

        if ($hostname) {
            $query->where('hostname', $hostname);
        }

        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        // Filter by app/domain using JSON field
        if ($appFilter) {
            $query->where(function($q) use ($appFilter) {
                $q->where('data->process_name', $appFilter)
                  ->orWhere('data->url', 'like', "%{$appFilter}%");
            });
        }

        $total = $query->count();
        $totalPages = ceil($total / $limit);

        $events = $query->orderBy('timestamp', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get()
            ->map(function ($event) {
                return [
                    'timestamp' => $event->timestamp->toIso8601String(),
                    'event_type' => $event->event_type,
                    'hostname' => $event->hostname,
                    'data' => $event->data,
                ];
            });

        return response()->json([
            'events' => $events,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $totalPages,
        ]);
    }

    /**
     * Get device activity details
     * GET /api/dashboard/device_activity
     */
    public function deviceActivity(Request $request)
    {
        $hostname = $request->input('hostname');
        if (!$hostname) {
            return response()->json(['error' => 'hostname parameter required'], 400);
        }

        $hours = $request->input('hours', 24);

        $device = Device::where('hostname', $hostname)->first();
        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        // Get mouse activity statistics
        $mouseStats = Event::where('hostname', $hostname)
            ->where('timestamp', '>', Carbon::now()->subHours($hours))
            ->whereIn('event_type', ['mouse_active', 'mouse_idle'])
            ->select(
                DB::raw("SUM(CASE WHEN event_type = 'mouse_active' THEN 1 ELSE 0 END) as active_count"),
                DB::raw("SUM(CASE WHEN event_type = 'mouse_idle' THEN 1 ELSE 0 END) as idle_count"),
                DB::raw("MAX(CASE WHEN event_type = 'mouse_active' THEN timestamp END) as last_active")
            )
            ->first();

        // Get recent activity events
        $recentActivity = Event::where('hostname', $hostname)
            ->where('timestamp', '>', Carbon::now()->subHours($hours))
            ->orderBy('timestamp', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($event) {
                $item = [
                    'timestamp' => $event->timestamp->toIso8601String(),
                    'type' => $event->event_type,
                ];

                $data = $event->data;

                if ($event->event_type === 'foreground_change' && isset($data['url'])) {
                    $item['url'] = $data['url'];
                }

                if (isset($data['process_name'])) {
                    $item['process_name'] = $data['process_name'];
                }

                if ($event->event_type === 'key_count' && isset($data['keystrokes'])) {
                    $item['keystrokes'] = $data['keystrokes'];
                }

                return $item;
            });

        return response()->json([
            'hostname' => $device->hostname,
            'platform' => $device->platform,
            'last_active' => $mouseStats->last_active ?? null,
            'mouse_active_count' => $mouseStats->active_count ?? 0,
            'mouse_idle_count' => $mouseStats->idle_count ?? 0,
            'recent_activity' => $recentActivity,
        ]);
    }
}
