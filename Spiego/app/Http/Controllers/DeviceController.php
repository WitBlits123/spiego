<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Event;
use App\Models\BlockedSite;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DeviceController extends Controller
{
    /**
     * Display a listing of all devices
     */
    public function index()
    {
        $devices = Device::orderBy('last_seen', 'desc')->get();
        $activeDevices = Device::where('last_seen', '>', Carbon::now()->subDay())->count();
        $totalEvents = Event::count();
        
        return view('devices.index', compact('devices', 'activeDevices', 'totalEvents'));
    }

    /**
     * Display device detail with event type summary
     */
    public function show($hostname)
    {
        $device = Device::where('hostname', $hostname)->firstOrFail();
        
        // Get event counts for last 24 hours
        $eventTypes = [
            'foreground_change' => [
                'label' => 'Window Changes',
                'icon' => 'window-stack',
                'color' => '#667eea',
                'count' => 0
            ],
            'key_count' => [
                'label' => 'Keystrokes',
                'icon' => 'keyboard',
                'color' => '#f093fb',
                'count' => 0
            ],
            'mouse_active' => [
                'label' => 'Mouse Active',
                'icon' => 'mouse',
                'color' => '#43e97b',
                'count' => 0
            ],
            'mouse_idle' => [
                'label' => 'Mouse Idle',
                'icon' => 'pause-circle',
                'color' => '#ffa07a',
                'count' => 0
            ],
            'metadata' => [
                'label' => 'System Info',
                'icon' => 'info-circle',
                'color' => '#4facfe',
                'count' => 0
            ],
        ];
        
        // Count events for each type
        $counts = Event::where('hostname', $hostname)
            ->where('timestamp', '>', Carbon::now()->subDay())
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->pluck('count', 'event_type');
        
        foreach ($counts as $type => $count) {
            if (isset($eventTypes[$type])) {
                $eventTypes[$type]['count'] = $count;
            }
        }
        // Build application groups from foreground_change events (last 24h)
        // Extract process_name from the JSON `data` column. Use json_extract for SQLite compatibility.
        $appRows = Event::where('hostname', $hostname)
            ->where('event_type', 'foreground_change')
            ->where('timestamp', '>', Carbon::now()->subDay())
            ->selectRaw("json_extract(data, '$.process_name') as process_name, COUNT(*) as cnt, MAX(timestamp) as last_seen")
            ->groupByRaw("json_extract(data, '$.process_name')")
            ->orderByDesc('cnt')
            ->get();

        $applications = [];
        foreach ($appRows as $r) {
            $applications[] = [
                'process_name' => $r->process_name ?? 'Unknown',
                'count' => $r->cnt,
                'last_seen' => $r->last_seen,
            ];
        }

        // Compute per-application usage durations and hourly buckets over the last 24 hours
        $windowStart = Carbon::now()->subDay();
        $windowEnd = Carbon::now();

        // Prefer explicit screen_time events if they exist (these have duration_seconds), otherwise fall back
        $hasScreenTime = Event::where('hostname', $hostname)
            ->where('event_type', 'screen_time')
            ->where('timestamp', '>', $windowStart)
            ->exists();

        $usage = []; // ['proc' => ['seconds' => 0, 'buckets' => [24 ints]]]

        if ($hasScreenTime) {
            $screenEvents = Event::where('hostname', $hostname)
                ->where('event_type', 'screen_time')
                ->where('timestamp', '>', $windowStart)
                ->orderBy('timestamp')
                ->get(['timestamp', 'data']);

            // Also set eventsWindow for frequency counting to the screenEvents
            $eventsWindow = $screenEvents;

            foreach ($screenEvents as $ev) {
                $data = $ev->data;
                $proc = 'Unknown';
                if (is_array($data) && isset($data['process_name'])) {
                    $proc = $data['process_name'];
                } elseif (is_object($data) && isset($data->process_name)) {
                    $proc = $data->process_name;
                }
                $duration = 0;
                if (is_array($data) && isset($data['duration_seconds'])) $duration = intval($data['duration_seconds']);
                elseif (is_object($data) && isset($data->duration_seconds)) $duration = intval($data->duration_seconds);

                if ($duration <= 0) continue;

                if (!isset($usage[$proc])) $usage[$proc] = ['seconds' => 0, 'buckets' => array_fill(0, 24, 0)];

                // screen_time timestamp is treated as the end time of the interval
                $end = Carbon::parse($ev->timestamp);
                $start = $end->copy()->subSeconds($duration);

                // clamp to window
                if ($start->lessThan($windowStart)) $start = $windowStart->copy();
                if ($end->greaterThan($windowEnd)) $end = $windowEnd->copy();
                if ($end->lessThanOrEqualTo($start)) continue;

                $usage[$proc]['seconds'] += $end->diffInSeconds($start);

                $cursor = $start->copy()->startOfHour();
                while ($cursor->lessThan($end)) {
                    $bucketStart = $cursor->copy();
                    $bucketEnd = $cursor->copy()->addHour();
                    $overlapStart = $start->greaterThan($bucketStart) ? $start->copy() : $bucketStart->copy();
                    $overlapEnd = $end->lessThan($bucketEnd) ? $end->copy() : $bucketEnd->copy();
                    if ($overlapEnd->greaterThan($overlapStart)) {
                        $sec = $overlapEnd->diffInSeconds($overlapStart);
                        $usage[$proc]['buckets'][$bucketStart->hour] += $sec;
                    }
                    $cursor->addHour();
                }
            }
        } else {
            // fallback to inferring durations from consecutive foreground_change events
            $eventsWindow = Event::where('hostname', $hostname)
                ->where('event_type', 'foreground_change')
                ->where('timestamp', '>', $windowStart)
                ->orderBy('timestamp')
                ->get(['timestamp', 'data']);

            $count = $eventsWindow->count();
            for ($i = 0; $i < $count; $i++) {
                $ev = $eventsWindow[$i];
                $start = Carbon::parse($ev->timestamp);
                $end = ($i + 1 < $count) ? Carbon::parse($eventsWindow[$i + 1]->timestamp) : $windowEnd;

                if ($end->lessThanOrEqualTo($start)) continue;

                // clamp to window
                if ($start->lessThan($windowStart)) $start = $windowStart->copy();
                if ($end->greaterThan($windowEnd)) $end = $windowEnd->copy();
                if ($end->lessThanOrEqualTo($start)) continue;

                $data = $ev->data;
                $proc = 'Unknown';
                if (is_array($data) && isset($data['process_name'])) $proc = $data['process_name'];
                elseif (is_object($data) && isset($data->process_name)) $proc = $data->process_name;

                if (!isset($usage[$proc])) $usage[$proc] = ['seconds' => 0, 'buckets' => array_fill(0, 24, 0)];

                $seconds = $end->diffInSeconds($start);
                $usage[$proc]['seconds'] += $seconds;

                $cursor = $start->copy()->startOfHour();
                while ($cursor->lessThan($end)) {
                    $bucketStart = $cursor->copy();
                    $bucketEnd = $cursor->copy()->addHour();
                    $overlapStart = $start->greaterThan($bucketStart) ? $start->copy() : $bucketStart->copy();
                    $overlapEnd = $end->lessThan($bucketEnd) ? $end->copy() : $bucketEnd->copy();
                    if ($overlapEnd->greaterThan($overlapStart)) {
                        $sec = $overlapEnd->diffInSeconds($overlapStart);
                        $usage[$proc]['buckets'][$bucketStart->hour] += $sec;
                    }
                    $cursor->addHour();
                }
            }
        }

        // Determine top app by seconds and prepare summary arrays
        $topApp = null;
        $topAppStats = null;
        if (!empty($usage)) {
            uasort($usage, function ($a, $b) {
                return $b['seconds'] <=> $a['seconds'];
            });
            $topProc = array_key_first($usage);
            $topApp = $topProc;
            $topAppStats = $usage[$topProc];

            // prepare most used apps list (by hours)
            $mostUsedApps = [];
            foreach ($usage as $procName => $info) {
                $mostUsedApps[] = ['process_name' => $procName, 'seconds' => $info['seconds']];
            }
            usort($mostUsedApps, function ($a, $b) { return $b['seconds'] <=> $a['seconds']; });
        } else {
            $mostUsedApps = [];
        }

        // Load blocked sites for this device
        $blocked = BlockedSite::where('hostname', $hostname)->orderBy('domain')->get();

        return view('devices.show', compact('device', 'eventTypes', 'blocked', 'applications', 'topApp', 'topAppStats', 'mostUsedApps'));
    }

    /**
     * Add a blocked domain for a device (web form)
     */
    public function addBlockedSite($hostname, Request $request)
    {
        $request->validate([
            'domain' => 'required|string|max:255',
        ]);

        $domain = trim($request->input('domain'));
        BlockedSite::firstOrCreate([
            'hostname' => $hostname,
            'domain' => $domain,
        ]);

        return redirect()->route('devices.show', $hostname)->with('success', 'Blocked site added');
    }

    /**
     * Remove a blocked domain for a device
     */
    public function removeBlockedSite($hostname, $id)
    {
        $bs = BlockedSite::where('hostname', $hostname)->where('id', $id)->firstOrFail();
        $bs->delete();
        return redirect()->route('devices.show', $hostname)->with('success', 'Blocked site removed');
    }

    /**
     * Display events for a specific type
     */
    public function events($hostname, Request $request)
    {
        $device = Device::where('hostname', $hostname)->firstOrFail();
    $eventType = $request->input('type', 'foreground_change');
    $appFilter = $request->input('app', null);
    $urlFilter = $request->input('url', null);
    $windowFilter = $request->input('window', null);
        

        // Get events for this type (last 24 hours, paginated)
        $query = Event::where('hostname', $hostname)
            ->where('event_type', $eventType)
            ->where('timestamp', '>', Carbon::now()->subDay());

        if ($eventType === 'foreground_change') {
            if ($appFilter) {
                // SQLite: use json_extract to compare the process_name field inside the JSON data column
                $query = $query->whereRaw("json_extract(data, '$.process_name') = ?", [$appFilter]);
                $eventTypeLabel = ($appFilter . ' - App Logs');
            }
            // If a window name search is provided, filter by the `title` key inside the JSON data (case-insensitive)
            if ($windowFilter) {
                $likeWindow = '%' . strtolower($windowFilter) . '%';
                $query = $query->whereRaw("lower(json_extract(data, '$.title')) LIKE ?", [$likeWindow]);
                $eventTypeLabel = 'Window search: ' . $windowFilter;
            }
            // If a URL search is provided, filter by the `url` key inside the JSON data (case-insensitive)
            if ($urlFilter) {
                $likeTerm = '%' . strtolower($urlFilter) . '%';
                $query = $query->whereRaw("lower(json_extract(data, '$.url')) LIKE ?", [$likeTerm]);
                $eventTypeLabel = 'URL search: ' . $urlFilter;
            }
        }

        $events = $query->orderBy('timestamp', 'desc')->paginate(50);
        
        // Event type labels and icons
        $eventTypeLabels = [
            'foreground_change' => 'Window Changes',
            'key_count' => 'Keystrokes',
            'mouse_active' => 'Mouse Active',
            'mouse_idle' => 'Mouse Idle',
            'metadata' => 'System Information',
        ];
        
        $eventIcons = [
            'foreground_change' => 'window-stack',
            'key_count' => 'keyboard',
            'mouse_active' => 'mouse',
            'mouse_idle' => 'pause-circle',
            'metadata' => 'info-circle',
        ];
        
        $eventTypeLabel = $eventTypeLabels[$eventType] ?? ucfirst(str_replace('_', ' ', $eventType));
        $eventIcon = $eventIcons[$eventType] ?? 'circle-fill';
        
    return view('devices.events', compact('device', 'events', 'eventType', 'eventTypeLabel', 'eventIcon', 'appFilter', 'urlFilter', 'windowFilter'));
    }

    /**
     * Delete a device and all its events
     */
    public function destroy($hostname)
    {
        $device = Device::where('hostname', $hostname)->firstOrFail();
        
        // Delete all events for this device
        Event::where('hostname', $hostname)->delete();
        
        // Delete the device
        $device->delete();
        
        return redirect()->route('devices.index')->with('success', 'Device and all its events deleted successfully');
    }

    /**
     * Return quick summary JSON for AJAX (top app, estimated hours, peak hour)
     */
    public function quickSummary($hostname)
    {
        $device = Device::where('hostname', $hostname)->firstOrFail();

        // reuse the same 24h window aggregation as in show()
        $windowStart = Carbon::now()->subDay();
        $windowEnd = Carbon::now();

        // Prefer explicit screen_time events
        $hasScreenTime = Event::where('hostname', $hostname)
            ->where('event_type', 'screen_time')
            ->where('timestamp', '>', $windowStart)
            ->exists();

        $usage = [];
        // DEBUG: Log hostname and time window
        file_put_contents(base_path('storage/logs/quick_summary_debug.log'),
            "[" . now()->toIso8601String() . "] Hostname: $hostname, Window: $windowStart to $windowEnd\n",
            FILE_APPEND);
        if ($hasScreenTime) {
            $screenEvents = Event::where('hostname', $hostname)
                ->where('event_type', 'screen_time')
                ->where('timestamp', '>', $windowStart)
                ->orderBy('timestamp')
                ->get(['timestamp', 'data']);
            // DEBUG: Log number of events found
            file_put_contents(base_path('storage/logs/quick_summary_debug.log'),
                "Found screen_time events: " . count($screenEvents) . "\n",
                FILE_APPEND);
            // Set eventsWindow for frequency counting
            $eventsWindow = $screenEvents;

            // System apps to exclude from quick summary
            $excludedApps = [
                'explorer.exe',
                'ApplicationFrameHost.exe',
                'SearchHost.exe',
                'SearchApp.exe',
                'ShellExperienceHost.exe',
                'StartMenuExperienceHost.exe',
                'TextInputHost.exe',
                'SystemSettings.exe',
                'dwm.exe',
                'csrss.exe',
                'winlogon.exe',
                'taskhostw.exe',
            ];

            foreach ($screenEvents as $ev) {
                $data = $ev->data;
                $proc = 'Unknown';
                if (is_array($data) && isset($data['process_name'])) $proc = $data['process_name'];
                elseif (is_object($data) && isset($data->process_name)) $proc = $data->process_name;

                // Skip excluded system apps
                if (in_array(strtolower($proc), array_map('strtolower', $excludedApps))) continue;

                $duration = 0;
                if (is_array($data) && isset($data['duration_seconds'])) $duration = intval($data['duration_seconds']);
                elseif (is_object($data) && isset($data->duration_seconds)) $duration = intval($data->duration_seconds);

                $end = Carbon::parse($ev->timestamp);
                $start = $end->copy()->subSeconds($duration);
                // Calculate interval as end - start (should be positive)
                $interval = $end->lessThanOrEqualTo($start) ? 0 : ($end->timestamp - $start->timestamp);

                // DEBUG: Log event details
                file_put_contents(base_path('storage/logs/quick_summary_debug.log'),
                    "Event: proc=$proc, ts={$ev->timestamp}, duration=$duration, start={$start->toIso8601String()}, end={$end->toIso8601String()}, interval=$interval\n",
                    FILE_APPEND);

                if ($duration <= 0) continue;
                if ($start->lessThan($windowStart)) $start = $windowStart->copy();
                if ($end->greaterThan($windowEnd)) $end = $windowEnd->copy();
                if ($end->lessThanOrEqualTo($start)) continue;
                if ($interval <= 0) continue;

                if (!isset($usage[$proc])) $usage[$proc] = ['seconds' => 0, 'buckets' => array_fill(0, 24, 0)];
                $usage[$proc]['seconds'] += $interval;

                $cursor = $start->copy()->startOfHour();
                while ($cursor->lessThan($end)) {
                    $bucketStart = $cursor->copy();
                    $bucketEnd = $cursor->copy()->addHour();
                    $overlapStart = $start->greaterThan($bucketStart) ? $start->copy() : $bucketStart->copy();
                    $overlapEnd = $end->lessThan($bucketEnd) ? $end->copy() : $bucketEnd->copy();
                    if ($overlapEnd->greaterThan($overlapStart)) {
                        $sec = $overlapEnd->diffInSeconds($overlapStart);
                        if ($sec > 0) {
                            $usage[$proc]['buckets'][$bucketStart->hour] += $sec;
                        }
                    }
                    $cursor->addHour();
                }
            }
        } else {
            $eventsWindow = Event::where('hostname', $hostname)
                ->where('event_type', 'foreground_change')
                ->where('timestamp', '>', $windowStart)
                ->orderBy('timestamp')
                ->get(['timestamp', 'data']);

            $count = $eventsWindow->count();
            for ($i = 0; $i < $count; $i++) {
                $ev = $eventsWindow[$i];
                $start = Carbon::parse($ev->timestamp);
                $end = ($i + 1 < $count) ? Carbon::parse($eventsWindow[$i + 1]->timestamp) : $windowEnd;
                if ($end->lessThanOrEqualTo($start)) continue;
                if ($start->lessThan($windowStart)) $start = $windowStart->copy();
                if ($end->greaterThan($windowEnd)) $end = $windowEnd->copy();
                if ($end->lessThanOrEqualTo($start)) continue;
                $data = $ev->data;
                $proc = 'Unknown';
                if (is_array($data) && isset($data['process_name'])) $proc = $data['process_name'];
                elseif (is_object($data) && isset($data->process_name)) $proc = $data->process_name;
                if (!isset($usage[$proc])) $usage[$proc] = ['seconds' => 0, 'buckets' => array_fill(0, 24, 0)];
                $interval = $end->diffInSeconds($start);
                if ($interval <= 0) continue;
                $usage[$proc]['seconds'] += $interval;
                $cursor = $start->copy()->startOfHour();
                while ($cursor->lessThan($end)) {
                    $bucketStart = $cursor->copy();
                    $bucketEnd = $cursor->copy()->addHour();
                    $overlapStart = $start->greaterThan($bucketStart) ? $start->copy() : $bucketStart->copy();
                    $overlapEnd = $end->lessThan($bucketEnd) ? $end->copy() : $bucketEnd->copy();
                    if ($overlapEnd->greaterThan($overlapStart)) {
                        $sec = $overlapEnd->diffInSeconds($overlapStart);
                        if ($sec > 0) {
                            $usage[$proc]['buckets'][$bucketStart->hour] += $sec;
                        }
                    }
                    $cursor->addHour();
                }
            }
        }

        if (empty($usage)) {
            return response()->json(['top_app' => null, 'hours' => 0, 'peak_hour' => null, 'buckets' => [], 'most_used_app' => null]);
        }

        // Top app by seconds
        uasort($usage, function ($a, $b) { return $b['seconds'] <=> $a['seconds']; });
        $topProc = array_key_first($usage);
        $top = $usage[$topProc];
        $maxHour = null; $maxSec = 0;
        foreach ($top['buckets'] as $h => $s) { if ($s > $maxSec) { $maxSec = $s; $maxHour = $h; } }

        // Most-used app by event frequency (count appearances in eventsWindow)
        $freq = [];
        foreach ($eventsWindow as $ev) {
            $d = $ev->data;
            $p = 'Unknown';
            if (is_array($d) && isset($d['process_name'])) $p = $d['process_name'];
            elseif (is_object($d) && isset($d->process_name)) $p = $d->process_name;
            if (!isset($freq[$p])) $freq[$p] = 0;
            $freq[$p]++;
        }
        arsort($freq);
        $mostUsedApp = null;
        if (!empty($freq)) {
            $mostUsedApp = ['process_name' => array_key_first($freq), 'count' => reset($freq)];
        }

        // Also compute a weekly (7-day) heatmap and top apps for the week
        $weekStart = Carbon::now()->subDays(7);
        $weekEnd = Carbon::now();
        $weeklyBuckets = array_fill(0, 7, array_fill(0, 24, 0));
        $weeklyTotal = 0;
        $appSecondsWeek = [];

        $hasScreenTimeWeek = Event::where('hostname', $hostname)
            ->where('event_type', 'screen_time')
            ->where('timestamp', '>', $weekStart)
            ->exists();

        if ($hasScreenTimeWeek) {
            $wevents = Event::where('hostname', $hostname)
                ->where('event_type', 'screen_time')
                ->where('timestamp', '>', $weekStart)
                ->orderBy('timestamp')
                ->get(['timestamp', 'data']);

            foreach ($wevents as $ev) {
                $data = $ev->data;
                $proc = 'Unknown';
                if (is_array($data) && isset($data['process_name'])) $proc = $data['process_name'];
                elseif (is_object($data) && isset($data->process_name)) $proc = $data->process_name;

                // Skip excluded system apps
                if (in_array(strtolower($proc), array_map('strtolower', $excludedApps))) continue;

                $duration = 0;
                if (is_array($data) && isset($data['duration_seconds'])) $duration = intval($data['duration_seconds']);
                elseif (is_object($data) && isset($data->duration_seconds)) $duration = intval($data->duration_seconds);
                if ($duration <= 0) continue;

                $end = Carbon::parse($ev->timestamp);
                $start = $end->copy()->subSeconds($duration);
                if ($start->lessThan($weekStart)) $start = $weekStart->copy();
                if ($end->greaterThan($weekEnd)) $end = $weekEnd->copy();
                if ($end->lessThanOrEqualTo($start)) continue;

                // Calculate interval as end - start (should be positive)
                $interval = $end->lessThanOrEqualTo($start) ? 0 : ($end->timestamp - $start->timestamp);
                if ($interval <= 0) continue;

                $weeklyTotal += $interval;
                if (!isset($appSecondsWeek[$proc])) $appSecondsWeek[$proc] = 0;
                $appSecondsWeek[$proc] += $interval;

                $cursor = $start->copy()->startOfHour();
                while ($cursor->lessThan($end)) {
                    $bucketStart = $cursor->copy();
                    $bucketEnd = $cursor->copy()->addHour();
                    $overlapStart = $start->greaterThan($bucketStart) ? $start->copy() : $bucketStart->copy();
                    $overlapEnd = $end->lessThan($bucketEnd) ? $end->copy() : $bucketEnd->copy();
                    if ($overlapEnd->greaterThan($overlapStart)) {
                        $sec = $overlapEnd->diffInSeconds($overlapStart);
                        if ($sec > 0) {
                            $dayIndex = intval($overlapStart->dayOfWeek); // 0=Sun..6=Sat
                            $hour = $bucketStart->hour;
                            $weeklyBuckets[$dayIndex][$hour] += $sec;
                        }
                    }
                    $cursor->addHour();
                }
            }
        } else {
            // Fallback: infer from foreground_change events across 7 days
            $wevents = Event::where('hostname', $hostname)
                ->where('timestamp', '>', $weekStart)
                ->where('event_type', 'foreground_change')
                ->orderBy('timestamp')
                ->get(['timestamp', 'data']);

            $wc = $wevents->count();
            for ($i = 0; $i < $wc; $i++) {
                $ev = $wevents[$i];
                $start = Carbon::parse($ev->timestamp);
                $end = ($i + 1 < $wc) ? Carbon::parse($wevents[$i + 1]->timestamp) : $weekEnd;
                if ($end->lessThanOrEqualTo($start)) continue;
                if ($start->lessThan($weekStart)) $start = $weekStart->copy();
                if ($end->greaterThan($weekEnd)) $end = $weekEnd->copy();
                if ($end->lessThanOrEqualTo($start)) continue;

                $data = $ev->data;
                $proc = 'Unknown';
                if (is_array($data) && isset($data['process_name'])) $proc = $data['process_name'];
                elseif (is_object($data) && isset($data->process_name)) $proc = $data->process_name;

                $interval = $end->diffInSeconds($start);
                if ($interval <= 0) continue;

                $weeklyTotal += $interval;
                if (!isset($appSecondsWeek[$proc])) $appSecondsWeek[$proc] = 0;
                $appSecondsWeek[$proc] += $interval;

                $cursor = $start->copy()->startOfHour();
                while ($cursor->lessThan($end)) {
                    $bucketStart = $cursor->copy();
                    $bucketEnd = $cursor->copy()->addHour();
                    $overlapStart = $start->greaterThan($bucketStart) ? $start->copy() : $bucketStart->copy();
                    $overlapEnd = $end->lessThan($bucketEnd) ? $end->copy() : $bucketEnd->copy();
                    if ($overlapEnd->greaterThan($overlapStart)) {
                        $sec = $overlapEnd->diffInSeconds($overlapStart);
                        if ($sec > 0) {
                            $dayIndex = intval($overlapStart->dayOfWeek);
                            $hour = $bucketStart->hour;
                            $weeklyBuckets[$dayIndex][$hour] += $sec;
                        }
                    }
                    $cursor->addHour();
                }
            }
        }

        // Top apps for the week by seconds
        arsort($appSecondsWeek);
        $weeklyTopApps = [];
        foreach (array_slice($appSecondsWeek, 0, 6, true) as $pname => $secs) {
            $weeklyTopApps[] = ['process_name' => $pname, 'hours' => round($secs / 3600, 2)];
        }

        return response()->json([
            'top_app' => $topProc,
            'hours' => round($top['seconds'] / 3600, 2),
            'peak_hour' => $maxHour,
            'buckets' => $top['buckets'],
            'most_used_app' => $mostUsedApp,
            'weekly_total_seconds' => $weeklyTotal,
            'weekly_buckets' => $weeklyBuckets,
            'weekly_top_apps' => $weeklyTopApps,
        ]);
    }

    /**
     * Show timeline view for a device
     */
    public function timeline($hostname, Request $request)
    {
        $device = Device::where('hostname', $hostname)->firstOrFail();
        $range = $request->input('range', '12h');
        
        // Check for custom date/time range
        $customFrom = $request->input('from');
        $customTo = $request->input('to');
        
        if ($customFrom && $customTo) {
            // Use custom date/time range
            $windowStart = Carbon::parse($customFrom);
            $windowEnd = Carbon::parse($customTo);
            $range = 'custom';
        } else {
            // Parse range
            $hours = 12;
            if (preg_match('/(\d+)h/', $range, $m)) {
                $hours = intval($m[1]);
            }
            
            $windowEnd = now();
            $windowStart = now()->subHours($hours);
        }
        
        // Get all events in range
        $events = $device->events()
            ->where('timestamp', '>=', $windowStart)
            ->where('timestamp', '<=', $windowEnd)
            ->orderBy('timestamp')
            ->get();
        
        $totalEvents = $events->count();
    $lastEventId = $events->max('id') ?? 0;
        $windowSeconds = $windowEnd->diffInSeconds($windowStart);
        
        // Helper to convert timestamp to percentage position
        $toPercent = function($ts) use ($windowStart, $windowSeconds) {
            $offset = $ts->diffInSeconds($windowStart);
            return ($offset / $windowSeconds) * 100;
        };
        
        // Build AFK segments - prefer new afk_end events with duration, fallback to old mouse_idle logic
        $afkSegments = [];
        $afkEndEvents = $events->where('event_type', 'afk_end');
        
        if ($afkEndEvents->count() > 0) {
            // Use new AFK tracking events with precise durations
            foreach ($afkEndEvents as $ev) {
                $data = $ev->data;
                $startTime = null;
                $endTime = $ev->timestamp;
                $duration = 0;
                
                // Extract start_time, end_time, and duration from event data
                if (is_array($data)) {
                    $startTime = isset($data['start_time']) ? Carbon::parse($data['start_time'])->setTimezone(config('app.timezone')) : null;
                    $endTime = isset($data['end_time']) ? Carbon::parse($data['end_time'])->setTimezone(config('app.timezone')) : $endTime;
                    $duration = $data['duration_seconds'] ?? 0;
                } elseif (is_object($data)) {
                    $startTime = isset($data->start_time) ? Carbon::parse($data->start_time)->setTimezone(config('app.timezone')) : null;
                    $endTime = isset($data->end_time) ? Carbon::parse($data->end_time)->setTimezone(config('app.timezone')) : $endTime;
                    $duration = $data->duration_seconds ?? 0;
                }
                
                // If no start_time, calculate from duration
                if (!$startTime && $duration > 0) {
                    $startTime = $endTime->copy()->subSeconds($duration);
                }
                
                if ($startTime && $duration > 0) {
                    // Clamp to window
                    if ($startTime->lessThan($windowStart)) $startTime = $windowStart->copy();
                    if ($endTime->greaterThan($windowEnd)) $endTime = $windowEnd->copy();
                    if ($endTime->greaterThan($startTime)) {
                        $afkSegments[] = [
                            'afk' => true,
                            'left' => $toPercent($startTime),
                            'width' => $toPercent($endTime) - $toPercent($startTime),
                            'label' => 'AFK: ' . $startTime->format('H:i') . ' - ' . $endTime->format('H:i') . ' (' . gmdate('H:i:s', $duration) . ')',
                            'start' => $startTime->toIso8601String(),
                            'end' => $endTime->toIso8601String(),
                        ];
                    }
                }
            }
        } else {
            // Fallback to old mouse_idle/mouse_active logic
            $lastAfkState = null;
            $lastAfkTime = $windowStart;
            
            foreach ($events as $ev) {
                if ($ev->event_type === 'mouse_idle') {
                    if ($lastAfkState !== 'afk') {
                        if ($lastAfkState !== null) {
                            $afkSegments[] = [
                                'afk' => false,
                                'left' => $toPercent($lastAfkTime),
                                'width' => $toPercent($ev->timestamp) - $toPercent($lastAfkTime),
                                'label' => 'Active: ' . $lastAfkTime->format('H:i') . ' - ' . $ev->timestamp->format('H:i'),
                                'start' => $lastAfkTime->toIso8601String(),
                                'end' => $ev->timestamp->toIso8601String(),
                            ];
                        }
                        $lastAfkState = 'afk';
                        $lastAfkTime = $ev->timestamp;
                    }
                } elseif (in_array($ev->event_type, ['mouse_active', 'foreground_change', 'key_count'])) {
                    if ($lastAfkState !== 'active') {
                        if ($lastAfkState !== null) {
                            $afkSegments[] = [
                                'afk' => true,
                                'left' => $toPercent($lastAfkTime),
                                'width' => $toPercent($ev->timestamp) - $toPercent($lastAfkTime),
                                'label' => 'AFK: ' . $lastAfkTime->format('H:i') . ' - ' . $ev->timestamp->format('H:i'),
                            ];
                        }
                        $lastAfkState = 'active';
                        $lastAfkTime = $ev->timestamp;
                    }
                }
            }
            // Close last segment
            if ($lastAfkState !== null) {
                $afkSegments[] = [
                    'afk' => $lastAfkState === 'afk',
                    'left' => $toPercent($lastAfkTime),
                    'width' => $toPercent($windowEnd) - $toPercent($lastAfkTime),
                    'label' => ($lastAfkState === 'afk' ? 'AFK' : 'Active') . ': ' . $lastAfkTime->format('H:i') . ' - ' . $windowEnd->format('H:i'),
                    'start' => $lastAfkTime->toIso8601String(),
                    'end' => $windowEnd->toIso8601String(),
                ];
            }
        }
        
        // Build input segments - prefer new key_count_segment events with duration, fallback to old key_count markers
        $inputSegments = [];
        $keySegmentEvents = $events->where('event_type', 'key_count_segment');
        
        if ($keySegmentEvents->count() > 0) {
            // Use new key_count_segment events with precise durations
            foreach ($keySegmentEvents as $ev) {
                $data = $ev->data;
                $startTime = null;
                $endTime = $ev->timestamp;
                $duration = 0;
                $count = 0;
                
                if (is_array($data)) {
                    $startTime = isset($data['start_time']) ? Carbon::parse($data['start_time'])->setTimezone(config('app.timezone')) : null;
                    $endTime = isset($data['end_time']) ? Carbon::parse($data['end_time'])->setTimezone(config('app.timezone')) : $endTime;
                    $duration = $data['duration_seconds'] ?? 0;
                    $count = $data['count'] ?? 0;
                } elseif (is_object($data)) {
                    $startTime = isset($data->start_time) ? Carbon::parse($data->start_time)->setTimezone(config('app.timezone')) : null;
                    $endTime = isset($data->end_time) ? Carbon::parse($data->end_time)->setTimezone(config('app.timezone')) : $endTime;
                    $duration = $data->duration_seconds ?? 0;
                    $count = $data->count ?? 0;
                }
                
                if (!$startTime && $duration > 0) {
                    $startTime = $endTime->copy()->subSeconds($duration);
                }
                
                if ($startTime && $duration > 0) {
                    // Clamp to window
                    if ($startTime->lessThan($windowStart)) $startTime = $windowStart->copy();
                    if ($endTime->greaterThan($windowEnd)) $endTime = $windowEnd->copy();
                    if ($endTime->greaterThan($startTime)) {
                        $inputSegments[] = [
                            'left' => $toPercent($startTime),
                            'width' => $toPercent($endTime) - $toPercent($startTime),
                            'label' => $count . ' keys: ' . $startTime->format('H:i:s') . ' - ' . $endTime->format('H:i:s'),
                            'start' => $startTime->toIso8601String(),
                            'end' => $endTime->toIso8601String(),
                        ];
                    }
                }
            }
        } else {
            // Fallback to old key_count events (markers)
            foreach ($events->where('event_type', 'key_count') as $ev) {
                $count = $ev->data['count'] ?? 0;
                $left = $toPercent($ev->timestamp);
                $inputSegments[] = [
                    'left' => $left,
                    'width' => 0.5, // Small marker
                    'label' => $count . ' keys at ' . $ev->timestamp->format('H:i:s'),
                    'start' => $ev->timestamp->toIso8601String(),
                    'end' => $ev->timestamp->toIso8601String(),
                ];
            }
        }
        
        // Build a single chronological Apps row from foreground_change events (no merging)
        $appSegments = [];
        $appColors = [];
        $colorIndex = 0;
        $colors = ['#667eea', '#764ba2', '#f093fb', '#43e97b', '#ffa07a', '#4facfe', '#00f2fe', '#fa709a', '#fee140', '#30cfd0'];

        $fgEvents = $events->where('event_type', 'foreground_change')->values();
        
        for ($i = 0; $i < $fgEvents->count(); $i++) {
            $ev = $fgEvents[$i];
            $proc = $ev->data['process_name'] ?? 'Unknown';
            $title = $ev->data['title'] ?? '';

            if (!isset($appColors[$proc])) {
                $appColors[$proc] = $colors[$colorIndex % count($colors)];
                $colorIndex++;
            }

            $start = $ev->timestamp->copy();
            
            // For the last event, limit duration to 5 minutes instead of extending to window end
            if ($i + 1 < $fgEvents->count()) {
                $end = $fgEvents[$i + 1]->timestamp->copy();
            } else {
                // Last event: extend by max 5 minutes or until now, whichever is sooner
                $end = $start->copy()->addMinutes(5);
                $now = now();
                if ($end->greaterThan($now)) {
                    $end = $now;
                }
                if ($end->greaterThan($windowEnd)) {
                    $end = $windowEnd->copy();
                }
            }

            // Clamp to window boundaries
            if ($start->lessThan($windowStart)) $start = $windowStart->copy();
            if ($end->greaterThan($windowEnd)) $end = $windowEnd->copy();
            if ($end->lessThanOrEqualTo($start)) continue;

            $left = $toPercent($start);
            $width = $toPercent($end) - $left;

            $appSegments[] = [
                'id' => $ev->id,  // Use actual database ID
                'left' => $left,
                'width' => $width,
                'color' => $appColors[$proc],
                'label' => $proc . ': ' . $title . ' (' . $start->format('H:i') . ' - ' . $end->format('H:i') . ')',
                'short' => strlen($title) > 20 ? substr($title, 0, 20) . '...' : $title,
                'process' => $proc,
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
            ];
        }
        
        return view('devices.timeline', compact('device', 'range', 'windowStart', 'windowEnd', 'totalEvents', 'afkSegments', 'inputSegments', 'appSegments', 'lastEventId'));
    }

    /**
     * Return full timeline segments for a device for the requested window as JSON.
     * Accepts same range/from/to parameters as timeline() view.
     */
    public function timelineData($hostname, Request $request)
    {
        $device = Device::where('hostname', $hostname)->firstOrFail();
        $range = $request->input('range', '12h');
        $customFrom = $request->input('from');
        $customTo = $request->input('to');

        if ($customFrom && $customTo) {
            $windowStart = Carbon::parse($customFrom);
            $windowEnd = Carbon::parse($customTo);
        } else {
            $hours = 12;
            if (preg_match('/(\d+)h/', $range, $m)) $hours = intval($m[1]);
            $windowEnd = now();
            $windowStart = now()->subHours($hours);
        }

        $events = $device->events()
            ->where('timestamp', '>=', $windowStart)
            ->where('timestamp', '<=', $windowEnd)
            ->orderBy('timestamp')
            ->get();

        // build segments (reuse same logic as timeline())
        $windowSeconds = $windowEnd->diffInSeconds($windowStart);
        $toPercent = function($ts) use ($windowStart, $windowSeconds) {
            $offset = $ts->diffInSeconds($windowStart);
            return ($offset / $windowSeconds) * 100;
        };

        // AFK - prefer new afk_end events, fallback to old mouse_idle logic
        $afkSegments = [];
        $afkEndEvents = $events->where('event_type', 'afk_end');
        
        if ($afkEndEvents->count() > 0) {
            foreach ($afkEndEvents as $ev) {
                $data = $ev->data;
                $startTime = null;
                $endTime = $ev->timestamp;
                $duration = 0;
                
                if (is_array($data)) {
                    $startTime = isset($data['start_time']) ? Carbon::parse($data['start_time'])->setTimezone(config('app.timezone')) : null;
                    $endTime = isset($data['end_time']) ? Carbon::parse($data['end_time'])->setTimezone(config('app.timezone')) : $endTime;
                    $duration = $data['duration_seconds'] ?? 0;
                } elseif (is_object($data)) {
                    $startTime = isset($data->start_time) ? Carbon::parse($data->start_time)->setTimezone(config('app.timezone')) : null;
                    $endTime = isset($data->end_time) ? Carbon::parse($data->end_time)->setTimezone(config('app.timezone')) : $endTime;
                    $duration = $data->duration_seconds ?? 0;
                }
                
                if (!$startTime && $duration > 0) {
                    $startTime = $endTime->copy()->subSeconds($duration);
                }
                
                if ($startTime && $duration > 0) {
                    if ($startTime->lessThan($windowStart)) $startTime = $windowStart->copy();
                    if ($endTime->greaterThan($windowEnd)) $endTime = $windowEnd->copy();
                    if ($endTime->greaterThan($startTime)) {
                        $afkSegments[] = [
                            'afk' => true,
                            'left' => $toPercent($startTime),
                            'width' => $toPercent($endTime) - $toPercent($startTime),
                            'label' => 'AFK: ' . $startTime->format('H:i') . ' - ' . $endTime->format('H:i'),
                            'start' => $startTime->toIso8601String(),
                            'end' => $endTime->toIso8601String(),
                        ];
                    }
                }
            }
        } else {
            // Fallback to old logic
            $lastAfkState = null;
            $lastAfkTime = $windowStart;
            foreach ($events as $ev) {
                if ($ev->event_type === 'mouse_idle') {
                    if ($lastAfkState !== 'afk') {
                        if ($lastAfkState !== null) {
                            $afkSegments[] = [
                                'afk' => false,
                                'left' => $toPercent($lastAfkTime),
                                'width' => $toPercent($ev->timestamp) - $toPercent($lastAfkTime),
                                'label' => 'Active: ' . $lastAfkTime->format('H:i') . ' - ' . $ev->timestamp->format('H:i'),
                                'start' => $lastAfkTime->toIso8601String(),
                                'end' => $ev->timestamp->toIso8601String(),
                            ];
                        }
                        $lastAfkState = 'afk';
                        $lastAfkTime = $ev->timestamp;
                    }
                } elseif (in_array($ev->event_type, ['mouse_active', 'foreground_change', 'key_count'])) {
                    if ($lastAfkState !== 'active') {
                        if ($lastAfkState !== null) {
                            $afkSegments[] = [
                                'afk' => true,
                                'left' => $toPercent($lastAfkTime),
                                'width' => $toPercent($ev->timestamp) - $toPercent($lastAfkTime),
                                'label' => 'AFK: ' . $lastAfkTime->format('H:i') . ' - ' . $ev->timestamp->format('H:i'),
                                'start' => $lastAfkTime->toIso8601String(),
                                'end' => $ev->timestamp->toIso8601String(),
                            ];
                        }
                        $lastAfkState = 'active';
                        $lastAfkTime = $ev->timestamp;
                    }
                }
            }
            if ($lastAfkState !== null) {
                $afkSegments[] = [
                    'afk' => $lastAfkState === 'afk',
                    'left' => $toPercent($lastAfkTime),
                    'width' => $toPercent($windowEnd) - $toPercent($lastAfkTime),
                    'label' => ($lastAfkState === 'afk' ? 'AFK' : 'Active') . ': ' . $lastAfkTime->format('H:i') . ' - ' . $windowEnd->format('H:i'),
                    'start' => $lastAfkTime->toIso8601String(),
                    'end' => $windowEnd->toIso8601String(),
                ];
            }
        }

        // Input - prefer new key_count_segment events, fallback to old key_count markers
        $inputSegments = [];
        $keySegmentEvents = $events->where('event_type', 'key_count_segment');
        
        if ($keySegmentEvents->count() > 0) {
            foreach ($keySegmentEvents as $ev) {
                $data = $ev->data;
                $startTime = null;
                $endTime = $ev->timestamp;
                $duration = 0;
                $count = 0;
                
                if (is_array($data)) {
                    $startTime = isset($data['start_time']) ? Carbon::parse($data['start_time'])->setTimezone(config('app.timezone')) : null;
                    $endTime = isset($data['end_time']) ? Carbon::parse($data['end_time'])->setTimezone(config('app.timezone')) : $endTime;
                    $duration = $data['duration_seconds'] ?? 0;
                    $count = $data['count'] ?? 0;
                } elseif (is_object($data)) {
                    $startTime = isset($data->start_time) ? Carbon::parse($data->start_time)->setTimezone(config('app.timezone')) : null;
                    $endTime = isset($data->end_time) ? Carbon::parse($data->end_time)->setTimezone(config('app.timezone')) : $endTime;
                    $duration = $data->duration_seconds ?? 0;
                    $count = $data->count ?? 0;
                }
                
                if (!$startTime && $duration > 0) {
                    $startTime = $endTime->copy()->subSeconds($duration);
                }
                
                if ($startTime && $duration > 0) {
                    if ($startTime->lessThan($windowStart)) $startTime = $windowStart->copy();
                    if ($endTime->greaterThan($windowEnd)) $endTime = $windowEnd->copy();
                    if ($endTime->greaterThan($startTime)) {
                        $inputSegments[] = [
                            'left' => $toPercent($startTime),
                            'width' => $toPercent($endTime) - $toPercent($startTime),
                            'label' => $count . ' keys: ' . $startTime->format('H:i:s') . ' - ' . $endTime->format('H:i:s'),
                            'start' => $startTime->toIso8601String(),
                            'end' => $endTime->toIso8601String(),
                            'id' => 'ev_'.$ev->id,
                        ];
                    }
                }
            }
        } else {
            foreach ($events->where('event_type', 'key_count') as $ev) {
                $count = $ev->data['count'] ?? 0;
                $inputSegments[] = [
                    'left' => $toPercent($ev->timestamp),
                    'width' => 0.5,
                    'label' => $count . ' keys at ' . $ev->timestamp->format('H:i:s'),
                    'start' => $ev->timestamp->toIso8601String(),
                    'end' => $ev->timestamp->toIso8601String(),
                    'id' => 'ev_'.$ev->id,
                ];
            }
        }

        // Apps: chronological (no merging)
        $appSegments = [];
        $appColors = [];
        $colorIndex = 0;
        $colors = ['#667eea', '#764ba2', '#f093fb', '#43e97b', '#ffa07a', '#4facfe', '#00f2fe', '#fa709a', '#fee140', '#30cfd0'];
        $fgEvents = $events->where('event_type', 'foreground_change')->values();
        
        for ($i = 0; $i < $fgEvents->count(); $i++) {
            $ev = $fgEvents[$i];
            $proc = $ev->data['process_name'] ?? 'Unknown';
            $title = $ev->data['title'] ?? '';
            if (!isset($appColors[$proc])) {
                $appColors[$proc] = $colors[$colorIndex % count($colors)];
                $colorIndex++;
            }
            $start = $ev->timestamp->copy();
            
            // For the last event, limit duration to 5 minutes instead of extending to window end
            if ($i + 1 < $fgEvents->count()) {
                $end = $fgEvents[$i + 1]->timestamp->copy();
            } else {
                // Last event: extend by max 5 minutes or until now, whichever is sooner
                $end = $start->copy()->addMinutes(5);
                $now = now();
                if ($end->greaterThan($now)) {
                    $end = $now;
                }
                if ($end->greaterThan($windowEnd)) {
                    $end = $windowEnd->copy();
                }
            }
            
            if ($start->lessThan($windowStart)) $start = $windowStart->copy();
            if ($end->greaterThan($windowEnd)) $end = $windowEnd->copy();
            if ($end->lessThanOrEqualTo($start)) continue;
            
            $appSegments[] = [
                'id' => 'ev_'.$ev->id,
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
                'process' => $proc,
                'label' => $proc . ': ' . $title . ' (' . $start->format('H:i') . ' - ' . $end->format('H:i') . ')',
                'color' => $appColors[$proc],
            ];
        }

        $lastEventId = $events->max('id') ?? 0;

        return response()->json([
            'app' => $appSegments,
            'afk' => $afkSegments,
            'input' => $inputSegments,
            'last_event_id' => $lastEventId,
            'server_time' => Carbon::now()->toIso8601String(),
        ]);
    }

    /**
     * Return incremental timeline updates as JSON. Accepts optional `since` ISO timestamp to only return events after that time.
     */
    public function timelineUpdates($hostname, Request $request)
    {
        $device = Device::where('hostname', $hostname)->firstOrFail();
        $since = $request->input('since');
        $windowEnd = Carbon::now();

        $query = $device->events()->orderBy('timestamp');
        if ($since) {
            try {
                $sinceTs = Carbon::parse($since);
                $query = $query->where('timestamp', '>', $sinceTs);
            } catch (\Exception $e) {
                // ignore parse errors and return full recent window
            }
        } else {
            // limit to last 5 minutes by default
            $query = $query->where('timestamp', '>', Carbon::now()->subMinutes(5));
        }

        $events = $query->get();

        $afkSegments = [];
        $inputSegments = [];
        $appSegments = [];

        // Build simple segments for new events (only do a minimal transform so client can append)
        // To build durations, find next related events when possible
        $eventsByType = $events->groupBy('event_type');

        // Handle foreground_change: for each, find next foreground_change to set end time
        $fgEvents = $eventsByType->get('foreground_change', collect());
        foreach ($fgEvents as $i => $ev) {
            $proc = $ev->data['process_name'] ?? 'Unknown';
            $title = $ev->data['title'] ?? '';
            // default end = next fg event timestamp or now
            $endTs = null;
            if (isset($fgEvents[$i + 1])) {
                $endTs = Carbon::parse($fgEvents[$i + 1]->timestamp)->setTimezone(config('app.timezone'));
            } else {
                $endTs = Carbon::now()->setTimezone(config('app.timezone'));
            }
            // ensure end >= start
            $startTs = Carbon::parse($ev->timestamp)->setTimezone(config('app.timezone'));
            if ($endTs->lessThanOrEqualTo($startTs)) $endTs = $startTs->copy()->addSeconds(1);

            $appSegments[] = [
                'id' => 'ev_'.$ev->id,
                'start' => $startTs->toIso8601String(),
                'end' => $endTs->toIso8601String(),
                'process' => $proc,
                'label' => $proc . ': ' . $title . ' (' . $startTs->format('H:i') . ' - ' . $endTs->format('H:i') . ')',
                'color' => '#667eea',
            ];
        }

        // Handle AFK / mouse_idle / mouse_active: pair consecutive events to produce segments
        $mouseEvents = collect();
        foreach (['mouse_idle', 'mouse_active'] as $t) {
            if ($eventsByType->has($t)) {
                $mouseEvents = $mouseEvents->merge($eventsByType->get($t));
            }
        }
        $mouseEvents = $mouseEvents->sortBy('timestamp')->values();
        for ($i = 0; $i < $mouseEvents->count(); $i++) {
            $ev = $mouseEvents[$i];
            $startTs = Carbon::parse($ev->timestamp);
            // find next mouse event to be the end
            $endTs = null;
            if (isset($mouseEvents[$i + 1])) {
                $endTs = Carbon::parse($mouseEvents[$i + 1]->timestamp)->setTimezone(config('app.timezone'));
            } else {
                $endTs = Carbon::now()->setTimezone(config('app.timezone'));
            }
            if ($endTs->lessThanOrEqualTo($startTs)) $endTs = $startTs->copy()->addSeconds(1);

            $afkSegments[] = [
                'id' => 'ev_'.$ev->id,
                'start' => $startTs->toIso8601String(),
                'end' => $endTs->toIso8601String(),
                'afk' => $ev->event_type === 'mouse_idle',
                'label' => ($ev->event_type === 'mouse_idle' ? 'AFK' : 'Active') . ': ' . $startTs->format('H:i') . ' - ' . $endTs->format('H:i'),
            ];
        }

        // Handle key_count events: group consecutive key events into input segments
        $keyEvents = $eventsByType->get('key_count', collect())->sortBy('timestamp')->values();
        $maxGapSeconds = 120; // treat gaps <= 2min as continuous input
        $maxSegmentSeconds = 600; // cap segment length to 10 minutes
        if ($keyEvents->count() > 0) {
            $segStart = null;
            $segEnd = null;
            $segFirstId = null;
            for ($i = 0; $i < $keyEvents->count(); $i++) {
                $ev = $keyEvents[$i];
                $ts = Carbon::parse($ev->timestamp)->setTimezone(config('app.timezone'));
                if ($segStart === null) {
                    $segStart = $ts->copy();
                    $segEnd = $ts->copy();
                    $segFirstId = $ev->id;
                } else {
                    $gap = $ts->diffInSeconds($segEnd);
                    if ($gap <= $maxGapSeconds) {
                        // extend segment end
                        $segEnd = $ts->copy();
                    } else {
                        // close previous segment and possibly split if too long
                        $duration = $segEnd->diffInSeconds($segStart);
                        if ($duration <= $maxSegmentSeconds) {
                            $inputSegments[] = [
                                'id' => 'ev_'.$segFirstId,
                                'start' => $segStart->toIso8601String(),
                                'end' => $segEnd->toIso8601String(),
                                'label' => 'Input: ' . $segStart->format('H:i') . ' - ' . $segEnd->format('H:i'),
                            ];
                        } else {
                            // split into multiple chunks of maxSegmentSeconds
                            $cursor = $segStart->copy();
                            while ($cursor->lessThan($segEnd)) {
                                $chunkEnd = $cursor->copy()->addSeconds($maxSegmentSeconds);
                                if ($chunkEnd->greaterThan($segEnd)) $chunkEnd = $segEnd->copy();
                                $inputSegments[] = [
                                    'id' => 'ev_'.$segFirstId . '_' . $cursor->timestamp,
                                    'start' => $cursor->toIso8601String(),
                                    'end' => $chunkEnd->toIso8601String(),
                                    'label' => 'Input: ' . $cursor->format('H:i') . ' - ' . $chunkEnd->format('H:i'),
                                ];
                                $cursor = $chunkEnd->copy()->addSecond();
                            }
                        }
                        // start new segment
                        $segStart = $ts->copy();
                        $segEnd = $ts->copy();
                        $segFirstId = $ev->id;
                    }
                }
            }
            // close last segment
            if ($segStart !== null) {
                $duration = $segEnd->diffInSeconds($segStart);
                if ($duration <= $maxSegmentSeconds) {
                    $inputSegments[] = [
                        'id' => 'ev_'.$segFirstId,
                        'start' => $segStart->toIso8601String(),
                        'end' => $segEnd->toIso8601String(),
                        'label' => 'Input: ' . $segStart->format('H:i') . ' - ' . $segEnd->format('H:i'),
                    ];
                } else {
                    $cursor = $segStart->copy();
                    while ($cursor->lessThan($segEnd)) {
                        $chunkEnd = $cursor->copy()->addSeconds($maxSegmentSeconds);
                        if ($chunkEnd->greaterThan($segEnd)) $chunkEnd = $segEnd->copy();
                        $inputSegments[] = [
                            'id' => 'ev_'.$segFirstId . '_' . $cursor->timestamp,
                            'start' => $cursor->toIso8601String(),
                            'end' => $chunkEnd->toIso8601String(),
                            'label' => 'Input: ' . $cursor->format('H:i') . ' - ' . $chunkEnd->format('H:i'),
                        ];
                        $cursor = $chunkEnd->copy()->addSecond();
                    }
                }
            }
        }

        $lastEventId = $device->events()->max('id') ?? 0;
        return response()->json([ 'app' => $appSegments, 'afk' => $afkSegments, 'input' => $inputSegments, 'last_event_id' => $lastEventId, 'server_time' => $windowEnd->toIso8601String() ]);
    }
}
