<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spiego - {{ $device->hostname }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px 0;
        }
        .container {
            max-width: 1200px;
        }
        .header-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .device-info {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .device-badge {
            display: inline-block;
            padding: 8px 15px;
            background: #e3f2fd;
            border-radius: 20px;
            font-size: 0.9rem;
            margin: 5px 5px 5px 0;
        }
        .event-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
            text-decoration: none;
        }
        .event-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .event-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .event-count {
            font-size: 2rem;
            font-weight: bold;
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
    </style>
</head>
<body>
    <div class="container">
        <!-- Back Button -->
        <div class="mb-3">
            <a href="{{ route('devices.index') }}" class="back-btn">
                <i class="bi bi-arrow-left"></i> Back to Devices
            </a>
        </div>

        <!-- Device Info -->
        <div class="device-info">
            <h2 class="mb-3">
                <i class="bi bi-laptop text-primary"></i> {{ $device->hostname }}
            </h2>
            
            <div class="mb-3">
                <div class="device-badge">
                    <i class="bi bi-window"></i> {{ $device->platform }}
                </div>
                <div class="device-badge">
                    <i class="bi bi-code-slash"></i> Python {{ $device->python_version }}
                </div>
                <div class="device-badge">
                    <i class="bi bi-cpu"></i> {{ $device->cpu_count }} CPUs
                </div>
                <div class="device-badge">
                    <i class="bi bi-memory"></i> {{ number_format($device->memory_total / 1024 / 1024 / 1024, 1) }} GB RAM
                </div>
            </div>
            
            <p class="text-muted mb-0">
                <i class="bi bi-clock"></i> Last seen: <strong>{{ $device->last_seen->diffForHumans() }}</strong>
            </p>
        </div>

        <!-- Event Types -->
        <h3 class="text-white mb-3">Activity Overview (Last 24 Hours)</h3>
        
        <div class="row">
            <!-- Timeline Card -->
            <div class="col-md-4">
                <a href="{{ route('devices.timeline', ['hostname' => $device->hostname, 'range' => '12h']) }}" class="event-card text-center">
                    <div class="event-icon" style="color: #4facfe">
                        <i class="bi bi-bar-chart-line"></i>
                    </div>
                    <div class="event-title">Timeline View</div>
                    <div class="event-count" style="color: #4facfe">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <small class="text-muted">View activity timeline</small>
                </a>
            </div>
            @foreach($eventTypes as $type => $data)
                <div class="col-md-4">
                    <a href="{{ route('devices.events', ['hostname' => $device->hostname, 'type' => $type]) }}" class="event-card text-center">
                        <div class="event-icon" style="color: {{ $data['color'] }}">
                            <i class="bi bi-{{ $data['icon'] }}"></i>
                        </div>
                        <div class="event-title">{{ $data['label'] }}</div>
                        <div class="event-count" style="color: {{ $data['color'] }}">
                            {{ number_format($data['count']) }}
                        </div>
                        <small class="text-muted">Click to view details</small>
                    </a>
                </div>
            @endforeach
        </div>

        <!-- Blocked Sites -->
        <h3 class="text-white mt-4 mb-3">Blocked Sites</h3>
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="device-info">
                    <form action="{{ route('devices.blocked.add', $device->hostname) }}" method="POST" class="d-flex mb-3">
                        @csrf
                        <input type="text" name="domain" class="form-control me-2" placeholder="example.com" required>
                        <button class="btn btn-primary">Add</button>
                    </form>

                    @if($blocked->isEmpty())
                        <p class="text-muted">No blocked sites for this device.</p>
                    @else
                        <ul class="list-group">
                            @foreach($blocked as $b)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ $b->domain }}</strong>
                                        @if($b->created_at)
                                            <div class="text-muted small">Added {{ \Carbon\Carbon::parse($b->created_at)->diffForHumans() }}</div>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <!-- Quick Summary button opens a modal with device stats -->
                                        <button type="button" class="btn btn-sm btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#quickSummaryModal">
                                            <i class="bi bi-info-circle"></i>
                                        </button>

                                        <form action="{{ route('devices.blocked.remove', ['hostname' => $device->hostname, 'id' => $b->id]) }}" method="POST" onsubmit="return confirm('Remove blocked site {{ $b->domain }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">Remove</button>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        <!-- App Logs -->
        <h3 class="text-white mt-4 mb-3">App Logs</h3>
        <div class="row mb-3">
            <div class="col-md-8">
                <div class="device-info">
                    @if(empty($applications))
                        <p class="text-muted">No application activity in the last 24 hours.</p>
                    @else
                        <ul class="list-group">
                            @foreach($applications as $app)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ $app['process_name'] }}</strong>
                                        <div class="text-muted small">Events: {{ $app['count'] }} — Last: {{ \Carbon\Carbon::parse($app['last_seen'])->diffForHumans() }}</div>
                                    </div>
                                    <a href="{{ route('devices.events', ['hostname' => $device->hostname, 'type' => 'foreground_change', 'app' => $app['process_name']]) }}" class="btn btn-sm btn-primary">View</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
        <!-- Quick Summary Modal -->
        <div class="modal fade" id="quickSummaryModal" tabindex="-1" aria-labelledby="quickSummaryLabel" aria-hidden="true">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="quickSummaryLabel">Quick Summary — {{ $device->hostname }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-6">
                                <h6>Last Seen</h6>
                                <p class="mb-2">{{ $device->last_seen->diffForHumans() }}</p>

                                <h6>Platform</h6>
                                <p class="mb-2">{{ $device->platform }}</p>

                                <h6>CPU / RAM</h6>
                                <p class="mb-2">{{ $device->cpu_count }} CPUs — {{ number_format($device->memory_total / 1024 / 1024 / 1024, 1) }} GB</p>
                            </div>
                            <div class="col-6">
                                                <h6>Activity (24h)</h6>
                                                <ul class="list-unstyled mb-2">
                                                    @foreach($eventTypes as $key => $et)
                                                        <li><strong>{{ $et['label'] }}:</strong> {{ number_format($et['count']) }}</li>
                                                    @endforeach
                                                </ul>

                                                <h6>Top App</h6>
                                                @if(!empty($topApp) && !empty($topAppStats))
                                                    <p class="mb-1"><strong>{{ $topApp }}</strong></p>
                                                    <p class="mb-1 text-muted">Estimated usage: <strong>{{ round($topAppStats['seconds'] / 3600, 2) }} h</strong></p>
                                                    <p class="mb-1 text-muted">Peak hour: <strong>
                                                        @php
                                                            $maxHour = null; $maxSec = 0;
                                                            foreach ($topAppStats['buckets'] as $h => $s) { if ($s > $maxSec) { $maxSec = $s; $maxHour = $h; } }
                                                        @endphp
                                                        {{ $maxHour !== null ? $maxHour . ':00' : 'N/A' }}
                                                    </strong></p>

                                                    <div class="mb-2" style="display:flex;gap:4px;align-items:end;height:40px;">
                                                        @php $maxBucket = max($topAppStats['buckets']) ?: 1; @endphp
                                                        @foreach($topAppStats['buckets'] as $h => $s)
                                                            @php $height = intval(($s / $maxBucket) * 100); @endphp
                                                            <div title="{{ $h }}:00 — {{ round($s/60,1) }}m" style="width:4%;background:#667eea;border-radius:3px;height:{{ $height }}%;min-height:4px"></div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <p class="text-muted mb-0">No app activity</p>
                                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="{{ route('devices.show', $device->hostname) }}" class="btn btn-primary">Open Full Device Page</a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
</body>
</html>
