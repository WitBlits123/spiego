<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spiego - Devices</title>
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
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }
        .logo i {
            font-size: 3rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .logo h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .device-card {
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
        .device-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
            text-decoration: none;
        }
        .device-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 15px;
        }
        .device-badge {
            display: inline-block;
            padding: 8px 15px;
            background: #e3f2fd;
            border-radius: 20px;
            font-size: 0.9rem;
            margin: 5px 5px 5px 0;
        }
        .device-status {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }
        .status-online {
            width: 10px;
            height: 10px;
            background: #28a745;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        .status-offline {
            width: 10px;
            height: 10px;
            background: #dc3545;
            border-radius: 50%;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .empty-state {
            background: white;
            border-radius: 15px;
            padding: 60px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .empty-state i {
            font-size: 5rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        .delete-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .delete-btn:hover {
            background: #c82333;
            transform: scale(1.05);
        }
        .device-card {
            position: relative;
            padding-top: 50px; /* Extra space for buttons */
        }
        .device-actions {
            position: absolute;
            top: 12px;
            right: 12px;
            display: flex;
            gap: 8px;
            z-index: 20;
        }
        .alert {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header-card">
            <div class="logo">
                <i class="bi bi-eye"></i>
                <div>
                    <h1>Spiego</h1>
                    <p class="text-muted mb-0">Activity Monitoring System</p>
                </div>
            </div>
            <div class="mt-3">
                <a href="{{ route('admin.settings') }}" class="btn btn-outline-primary">
                    <i class="bi bi-gear-fill"></i> Admin Settings
                </a>
                <a href="{{ route('license.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-key"></i> License
                </a>
            </div>
        </div>

        <!-- Success Message -->
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="device-card text-center">
                    <h3 class="text-primary mb-0">{{ $devices->count() }}</h3>
                    <small class="text-muted">Total Devices</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="device-card text-center">
                    <h3 class="text-success mb-0">{{ $activeDevices }}</h3>
                    <small class="text-muted">Active (24h)</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="device-card text-center">
                    <h3 class="text-info mb-0">{{ number_format($totalEvents) }}</h3>
                    <small class="text-muted">Total Events</small>
                </div>
            </div>
        </div>

        <!-- Devices List -->
        <h3 class="text-white mb-3"><i class="bi bi-laptop"></i> Devices</h3>
        
        @if($devices->isEmpty())
            <div class="empty-state">
                <i class="bi bi-laptop"></i>
                <h4 class="text-muted">No devices registered yet</h4>
                <p class="text-muted mb-0">Devices will appear here once they start sending activity data</p>
            </div>
        @else
            <div class="row">
                @foreach($devices as $device)
                    <div class="col-md-6">
                        <div class="device-card">
                            <!-- Action Buttons -->
                            <div class="device-actions">
                                <button type="button" class="btn btn-sm btn-outline-primary" title="Quick summary" data-hostname="{{ $device->hostname }}" id="qs-btn-{{ $device->hostname }}">
                                    <i class="bi bi-info-circle"></i>
                                </button>
                                <form action="{{ route('devices.destroy', $device->hostname) }}" method="POST" 
                                      onsubmit="return confirm('Are you sure you want to delete {{ $device->hostname }} and all its events? This cannot be undone.');"
                                      style="display: inline; margin: 0;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="delete-btn" title="Delete device and all events">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Device Link -->
                            <a href="{{ route('devices.show', $device->hostname) }}" style="text-decoration: none; color: inherit; display: block;">
                                <div class="device-name">
                                    <i class="bi bi-laptop"></i> {{ $device->hostname }}
                                </div>
                                
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
                                
                                <div class="device-status">
                                    @if($device->last_seen->diffInMinutes(now()) < 5)
                                        <div class="status-online"></div>
                                        <span class="text-success"><strong>Online</strong></span>
                                    @else
                                        <div class="status-offline"></div>
                                        <span class="text-danger"><strong>Offline</strong></span>
                                    @endif
                                    <span class="text-muted ms-auto">
                                        Last seen: {{ $device->last_seen->diffForHumans() }}
                                    </span>
                                </div>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
        <!-- Quick Summary Modal (dark themed) -->
        <div class="modal fade" id="dashboardQuickSummary" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content bg-dark text-white rounded-4">
                    <div class="modal-header border-0">
                        <h5 class="modal-title" id="dashboardQuickSummaryLabel">Quick Summary</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="qs-loading">Loading…</div>
                        <div id="qs-content" style="display:none;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h5 id="qs-topapp" class="mb-0"></h5>
                                        <small id="qs-mostused" class="text-muted"></small>
                                    </div>
                                    <div class="text-end">
                                        <div class="small text-muted">Weekly Total</div>
                                        <div id="qs-week-total" style="font-size:1.25rem;font-weight:700"></div>
                                    </div>
                                </div>

                                <!-- Weekly heatmap 7 rows x 24 cols -->
                                <div id="qs-week-heat" style="display:flex;flex-direction:column;gap:6px;height:180px;">
                                </div>

                                <div class="mt-3">
                                    <h6 class="mb-2">Top Apps (week)</h6>
                                    <div id="qs-week-topapps" class="d-flex gap-2 flex-wrap"></div>
                                </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = new bootstrap.Modal(document.getElementById('dashboardQuickSummary'));
                document.querySelectorAll('button[data-hostname]').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        const host = btn.getAttribute('data-hostname');
                        document.getElementById('qs-loading').style.display = 'block';
                        document.getElementById('qs-content').style.display = 'none';
                        modal.show();
                        try {
                            const resp = await fetch(`/devices/${host}/quick_summary`);
                            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                            const j = await resp.json();
                            if (!j.top_app) {
                                document.getElementById('qs-topapp').textContent = 'No app activity in the last 24h.';
                                document.getElementById('qs-week-total').textContent = '';
                                document.getElementById('qs-week-heat').innerHTML = '';
                                document.getElementById('qs-week-topapps').innerHTML = '';
                            } else {
                                document.getElementById('qs-topapp').textContent = `${j.top_app} — ${j.hours} h (24h)`;
                                document.getElementById('qs-mostused').textContent = j.most_used_app ? `Most used: ${j.most_used_app.process_name}` : '';

                                // Weekly total
                                document.getElementById('qs-week-total').textContent = `${(j.weekly_total_seconds ? (Math.round(j.weekly_total_seconds/3600*10)/10) : 0)} h`;

                                // Weekly heatmap (7 rows, Sunday..Saturday)
                                const weekBuckets = j.weekly_buckets || [];
                                const weekContainer = document.getElementById('qs-week-heat');
                                weekContainer.innerHTML = '';
                                // compute max across all buckets for scaling
                                let globalMax = 1;
                                for (let d = 0; d < 7; d++) {
                                    const row = weekBuckets[d] || Array(24).fill(0);
                                    for (let h = 0; h < 24; h++) { if (row[h] && row[h] > globalMax) globalMax = row[h]; }
                                }

                                const dayNames = ['S','M','T','W','T','F','S'];
                                for (let d = 0; d < 7; d++) {
                                    const row = document.createElement('div');
                                    row.style.display = 'flex';
                                    row.style.alignItems = 'end';
                                    row.style.gap = '4px';
                                    const label = document.createElement('div');
                                    label.textContent = dayNames[d];
                                    label.style.width = '18px';
                                    label.style.color = '#cbd5e1';
                                    label.style.fontSize = '12px';
                                    label.style.marginRight = '6px';
                                    row.appendChild(label);
                                    const cols = weekBuckets[d] || Array(24).fill(0);
                                    for (let h = 0; h < 24; h++) {
                                        const s = cols[h] || 0;
                                        const bar = document.createElement('div');
                                        const hPerc = Math.max(6, Math.round((s / globalMax) * 100));
                                        bar.style.width = '3%';
                                        bar.style.height = hPerc + '%';
                                        bar.style.background = '#2563eb';
                                        bar.style.borderRadius = '3px';
                                        bar.title = `${h}:00 — ${Math.round(s/60)}m`;
                                        row.appendChild(bar);
                                    }
                                    weekContainer.appendChild(row);
                                }

                                // Weekly top apps
                                const topAppsDiv = document.getElementById('qs-week-topapps');
                                topAppsDiv.innerHTML = '';
                                const tops = j.weekly_top_apps || [];
                                tops.forEach(t => {
                                    const pill = document.createElement('div');
                                    pill.className = 'badge bg-light text-dark';
                                    pill.style.padding = '8px';
                                    pill.textContent = `${t.process_name} — ${t.hours}h`;
                                    topAppsDiv.appendChild(pill);
                                });
                            }
                        } catch (e) {
                            console.error('Quick summary error:', e);
                            document.getElementById('qs-topapp').textContent = 'Error loading summary: ' + e.message;
                        } finally {
                            document.getElementById('qs-loading').style.display = 'none';
                            document.getElementById('qs-content').style.display = 'block';
                        }
                    });
                });
            });
        </script>
</body>
</html>
