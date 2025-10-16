<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 700px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
        .content {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .summary-box {
            background: #d1ecf1;
            border-left: 4px solid #0c5460;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .device-card {
            background: white;
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .device-name {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 15px;
        }
        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .stat-label {
            font-weight: bold;
            color: #666;
        }
        .stat-value {
            color: #333;
        }
        .app-badge {
            display: inline-block;
            background: #e3f2fd;
            padding: 5px 12px;
            border-radius: 15px;
            margin: 5px;
            font-size: 13px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 {{ ucfirst($period) }} Activity Summary Report</h1>
        <p style="margin: 5px 0 0 0; opacity: 0.9;">{{ now()->format('F j, Y') }}</p>
    </div>
    <div class="content">
        <p>Hello,</p>
        
        <div class="summary-box">
            <strong>Activity Summary for {{ count($devices) }} device(s)</strong>
            <p style="margin: 5px 0 0 0;">Here's your {{ $period }} activity report from Spiego.</p>
        </div>

        @foreach($devices as $device)
            @php
                $summary = $summaries[$device->hostname] ?? null;
            @endphp
            
            @if($summary)
            <div class="device-card">
                <div class="device-name">
                    🖥️ {{ $device->hostname }}
                </div>

                <div class="stat-row">
                    <span class="stat-label">Top Application:</span>
                    <span class="stat-value">{{ $summary['top_app'] ?? 'N/A' }}</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Total Active Time:</span>
                    <span class="stat-value">{{ $summary['hours'] ?? 0 }} hours</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Most Used App (by frequency):</span>
                    <span class="stat-value">{{ $summary['most_used_app']['process_name'] ?? 'N/A' }}</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Weekly Total:</span>
                    <span class="stat-value">{{ round(($summary['weekly_total_seconds'] ?? 0) / 3600, 1) }} hours</span>
                </div>

                @if(!empty($summary['weekly_top_apps']))
                <div style="margin-top: 15px;">
                    <div class="stat-label" style="margin-bottom: 10px;">Top Applications (Week):</div>
                    @foreach($summary['weekly_top_apps'] as $app)
                        <span class="app-badge">{{ $app['process_name'] }} - {{ $app['hours'] }}h</span>
                    @endforeach
                </div>
                @endif

                <div style="margin-top: 15px;">
                    <a href="{{ url('/devices/' . $device->hostname) }}" 
                       style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                              color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-size: 14px;">
                        View Full Details
                    </a>
                </div>
            </div>
            @endif
        @endforeach

        @if(count($devices) == 0)
            <p style="text-align: center; color: #666; padding: 30px;">
                No device activity to report for this period.
            </p>
        @endif
    </div>
    <div class="footer">
        <p>This is an automated report from Spiego Activity Monitoring System</p>
        <p>To configure notification settings, visit the Admin Settings page</p>
    </div>
</body>
</html>
