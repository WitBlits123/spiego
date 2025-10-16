<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spiego - Timeline - {{ $device->hostname }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px 0;
        }
        .container-fluid {
            max-width: 95%;
        }
        .header-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .timeline-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .back-btn {
            background: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .back-btn:hover {
            transform: translateX(-5px);
            color: #764ba2;
        }
        .timeline-container {
            overflow-x: auto;
            padding: 10px 0;
        }
        .timeline-row {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            min-height: 32px;
        }
        .timeline-label {
            width: 200px;
            font-weight: 600;
            font-size: 0.9rem;
            padding-right: 15px;
            flex-shrink: 0;
        }
        .timeline-track {
            display: flex;
            flex: 1;
            height: 28px;
            background: #f8f9fa;
            border-radius: 4px;
            position: relative;
            min-width: 100%;
        }
        .timeline-hour-markers {
            display: flex;
            margin-left: 200px;
            margin-bottom: 5px;
            min-width: 100%;
        }
        .hour-marker {
            flex: 1;
            text-align: center;
            font-size: 0.75rem;
            color: #6c757d;
            border-left: 1px solid #dee2e6;
            padding: 2px;
            white-space: nowrap;
        }
        .hour-marker:first-child {
            border-left: none;
        }
        .timeline-segment {
            position: absolute;
            height: 100%;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            color: white;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            padding: 0 4px;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .timeline-segment:hover {
            opacity: 0.8;
        }
        .segment-afk {
            background: #28a745;
        }
        .segment-not-afk {
            background: #dc3545;
        }
        .segment-input {
            background: #007bff;
        }
        .btn-group .btn {
            border-radius: 8px;
        }
        .filter-badge {
            display: inline-block;
            padding: 8px 12px;
            background: #e3f2fd;
            border-radius: 20px;
            font-size: 0.85rem;
            margin: 5px 5px 5px 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Back Button -->
        <div class="mb-3">
            <a href="{{ route('devices.show', $device->hostname) }}" class="back-btn">
                <i class="bi bi-arrow-left"></i> Back to {{ $device->hostname }}
            </a>
        </div>

        <!-- Header -->
        <div class="header-card">
            <h3 class="mb-3">
                <i class="bi bi-laptop text-primary"></i> {{ $device->hostname }} — Timeline
            </h3>
            <div class="mb-3">
                <span class="filter-badge">
                    <i class="bi bi-window"></i> {{ $device->platform }}
                </span>
                <span class="filter-badge">
                    <i class="bi bi-cpu"></i> {{ $device->cpu_count }} CPUs
                </span>
                <span class="filter-badge">
                    <i class="bi bi-memory"></i> {{ number_format($device->memory_total / 1024 / 1024 / 1024, 1) }} GB RAM
                </span>
                <span class="filter-badge">
                    <i class="bi bi-clock"></i> Last seen: {{ $device->last_seen->diffForHumans() }}
                </span>
            </div>
            <div class="btn-group me-3" role="group">
                <a href="{{ route('devices.timeline', ['hostname' => $device->hostname, 'range' => '1h']) }}" 
                   class="btn btn-sm {{ $range === '1h' ? 'btn-primary' : 'btn-outline-primary' }}">1h</a>
                <a href="{{ route('devices.timeline', ['hostname' => $device->hostname, 'range' => '2h']) }}" 
                   class="btn btn-sm {{ $range === '2h' ? 'btn-primary' : 'btn-outline-primary' }}">2h</a>
                <a href="{{ route('devices.timeline', ['hostname' => $device->hostname, 'range' => '6h']) }}" 
                   class="btn btn-sm {{ $range === '6h' ? 'btn-primary' : 'btn-outline-primary' }}">6h</a>
                <a href="{{ route('devices.timeline', ['hostname' => $device->hostname, 'range' => '12h']) }}" 
                   class="btn btn-sm {{ $range === '12h' ? 'btn-primary' : 'btn-outline-primary' }}">12h</a>
                <a href="{{ route('devices.timeline', ['hostname' => $device->hostname, 'range' => '24h']) }}" 
                   class="btn btn-sm {{ $range === '24h' ? 'btn-primary' : 'btn-outline-primary' }}">24h</a>
                <a href="{{ route('devices.timeline', ['hostname' => $device->hostname, 'range' => '48h']) }}" 
                   class="btn btn-sm {{ $range === '48h' ? 'btn-primary' : 'btn-outline-primary' }}">48h</a>
            </div>
            
            <!-- Custom Date/Time Range -->
            <form method="GET" action="{{ route('devices.timeline', $device->hostname) }}" class="d-inline-flex align-items-center gap-2">
                <label class="form-label mb-0 fw-bold">Custom Range:</label>
                <input type="datetime-local" name="from" class="form-control form-control-sm" 
                       value="{{ request('from', $windowStart->format('Y-m-d\TH:i')) }}" style="width: 200px;">
                <span>to</span>
                <input type="datetime-local" name="to" class="form-control form-control-sm" 
                       value="{{ request('to', $windowEnd->format('Y-m-d\TH:i')) }}" style="width: 200px;">
                <button type="submit" class="btn btn-sm btn-success">
                    <i class="bi bi-calendar-check"></i> Apply
                </button>
                @if(request('from') || request('to'))
                    <a href="{{ route('devices.timeline', ['hostname' => $device->hostname, 'range' => '12h']) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x"></i> Clear
                    </a>
                @endif
            </form>
        </div>

        <!-- Timeline -->
        <div class="timeline-card">
            <h5 class="mb-3">Activity Timeline — Last {{ $range }}</h5>
                <div class="mb-2">
                    <div class="form-check form-switch d-inline-block">
                        <input class="form-check-input" type="checkbox" id="liveToggle" checked>
                        <label class="form-check-label" for="liveToggle">Live</label>
                    </div>
                </div>
            <p class="text-muted mb-3">Showing {{ $totalEvents }} events from {{ $windowStart->format('M d, Y H:i') }} to {{ $windowEnd->format('M d, Y H:i') }}</p>

            <div id="vis-timeline" style="height: 420px; background: white; border-radius:8px; padding:12px;"></div>

            <!-- Prepare data for vis-timeline -->
            <script>
                // Embed server-side arrays directly as JS variables. Using json_encode with JSON_HEX_*
                // to avoid breaking out of script tags if strings contain '<\/script>' or quotes.
                const appData = {!! json_encode($appSegments, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!} || [];
                const afkData = {!! json_encode($afkSegments, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!} || [];
                const inputData = {!! json_encode($inputSegments, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!} || [];
            </script>
            <link href="https://unpkg.com/vis-timeline@7.7.3/styles/vis-timeline-graph2d.min.css" rel="stylesheet" />
            <script src="https://unpkg.com/vis-timeline@7.7.3/standalone/umd/vis-timeline-graph2d.min.js"></script>
            <script>
                (function(){
                    const items = new vis.DataSet();
                    // Single group for apps, plus groups for AFK and Input
                    const groups = new vis.DataSet([
                        {id: 1, content: 'Apps'},
                        {id: 2, content: 'AFK'},
                        {id: 3, content: 'Input'}
                    ]);

                    appData.forEach((s, i) => {
                        // Use DB id when present to ensure uniqueness, fall back to index
                        const id = (s.id !== undefined && s.id !== null) ? ('app-' + s.id) : ('app-' + i);
                        // Ensure start/end are parsed as Date objects in the browser so timezones are handled consistently
                        const start = s.start ? new Date(s.start) : null;
                        const end = s.end ? new Date(s.end) : null;
                        items.add({
                            id: id,
                            group: 1,
                            start: start,
                            end: end,
                            content: s.short || s.process,
                            title: s.label,
                            style: 'background:' + (s.color || '#667eea') + '; color: #fff;'
                        });
                    });

                    afkData.forEach((s,i) => {
                        const id = s.id ? ('afk-' + s.id) : ('afk-' + i);
                        const start = s.start ? new Date(s.start) : null;
                        const end = s.end ? new Date(s.end) : null;
                        items.add({
                            id: id,
                            group: 2,
                            start: start,
                            end: end,
                            content: s.afk ? 'AFK' : 'Active',
                            title: s.label,
                            style: s.afk ? 'background:#28a745;color:#fff' : 'background:#dc3545;color:#fff'
                        });
                    });

                    inputData.forEach((s,i) => {
                        const id = s.id ? ('in-' + s.id) : ('in-' + i);
                        const start = s.start ? new Date(s.start) : null;
                        const end = s.end ? new Date(s.end) : null;
                        items.add({
                            id: id,
                            group: 3,
                            start: start,
                            end: end,
                            content: ' ',
                            title: s.label,
                            style: 'background:#007bff;'
                        });
                    });

                    const container = document.getElementById('vis-timeline');
                    const options = {
                        stack: false,
                        horizontalScroll: true,
                        zoomable: true,
                        groupOrder: 'id',
                        showCurrentTime: true,
                        start: new Date("{{ $windowStart->toIso8601String() }}"),
                        end: new Date("{{ $windowEnd->toIso8601String() }}"),
                        orientation: { axis: 'top' }
                    };

                    const timeline = new vis.Timeline(container);
                    timeline.setOptions(options);
                    timeline.setGroups(groups);
                    timeline.setItems(items);

                    // Hover is handled by native title attribute; we can also add click handler
                    timeline.on('itemover', function (props) {
                        // optional: custom hover
                    });

                    // Real-time polling for incremental updates with a live toggle
                    let lastServerTime = null;
                    let lastEventId = {{ $lastEventId ?? 0 }};
                    const hostname = @json($device->hostname);
                    let pollingBackoff = 1000;
                    const liveToggle = document.getElementById('liveToggle');

                    async function fetchUpdates() {
                        try {
                            let url = `{{ url('/devices') }}/${hostname}/timeline/updates`;
                            if (lastServerTime) url += `?since=${encodeURIComponent(lastServerTime)}`;
                            const resp = await fetch(url, { credentials: 'same-origin' });
                            if (!resp.ok) throw new Error('HTTP ' + resp.status);
                            const data = await resp.json();
                            // update lastServerTime
                            if (data.server_time) lastServerTime = data.server_time;
                            // If server indicates there are newer events (last_event_id), fetch full data to resync
                            if (data.last_event_id && data.last_event_id !== lastEventId) {
                                lastEventId = data.last_event_id;
                                // fetch full timeline data for current range and replace items
                                const dataUrl = `{{ url('/devices') }}/${hostname}/timeline/data?range={{ $range }}&from={{ request('from', '') }}&to={{ request('to', '') }}`;
                                fetch(dataUrl, { credentials: 'same-origin' }).then(r => r.json()).then(full => {
                                    // clear and re-add
                                    items.clear();
                                    (full.app || []).forEach((a, idx) => items.add({ id: a.id || ('a_'+idx), group:1, start: a.start, end: a.end, content: a.process || a.label, title: a.label, style: 'background:' + (a.color || '#667eea') + '; color:#fff' }));
                                    (full.afk || []).forEach((a, idx) => items.add({ id: a.id || ('afk_'+idx), group:2, start: a.start, end: a.end, content: a.afk ? 'AFK' : 'Active', title: a.label, style: a.afk ? 'background:#28a745;color:#fff' : 'background:#dc3545;color:#fff' }));
                                    (full.input || []).forEach((it, idx) => items.add({ id: it.id || ('in_'+idx), group:3, start: it.start, end: it.end, content: ' ', title: it.label, style: 'background:#007bff;' }));
                                }).catch(e => console.error('Failed to fetch full timeline data', e));
                            }

                            // Append app items
                            (data.app || []).forEach((a, idx) => {
                                const id = a.id || ('re_app_' + (Date.now() + idx));
                                const it = { id, group: 1, start: a.start, end: a.end, content: a.process || a.label || 'App', title: a.label, style: 'background:' + (a.color || '#667eea') + '; color:#fff' };
                                if (items.get(id)) items.update(it); else items.add(it);
                            });

                            // Append afk markers (convert to tiny items on AFK group)
                            (data.afk || []).forEach((a, idx) => {
                                const id = a.id || ('re_afk_' + (Date.now() + idx));
                                const it = { id, group: 2, start: a.start, end: a.end, content: a.afk ? 'AFK' : 'Active', title: a.label, style: a.afk ? 'background:#28a745;color:#fff' : 'background:#dc3545;color:#fff' };
                                if (items.get(id)) items.update(it); else items.add(it);
                            });

                            // Append input markers
                            (data.input || []).forEach((it, idx) => {
                                const id = it.id || ('re_in_' + (Date.now() + idx));
                                const itemObj = { id, group: 3, start: it.start, end: it.end, content: ' ', title: it.label, style: 'background:#007bff;' };
                                if (items.get(id)) items.update(itemObj); else items.add(itemObj);
                            });

                            // reset backoff and schedule next
                            pollingBackoff = 1000;
                            scheduleNextPoll(2000);
                        } catch (err) {
                            console.error('Failed to fetch timeline updates', err);
                            // increase backoff
                            pollingBackoff = Math.min(30000, pollingBackoff * 2);
                            scheduleNextPoll(pollingBackoff);
                        }
                    }

                    let pollTimer = null;
                    function scheduleNextPoll(delay) {
                        if (pollTimer) clearTimeout(pollTimer);
                        pollTimer = setTimeout(() => {
                            // only poll when page is visible
                            if (document.visibilityState === 'visible' && liveToggle.checked) fetchUpdates();
                            else scheduleNextPoll(5000);
                        }, delay);
                    }

                    // Start polling after initial load
                    lastServerTime = new Date().toISOString();
                    scheduleNextPoll(2000);

                })();
            </script>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>