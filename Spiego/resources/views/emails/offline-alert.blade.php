<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
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
        .alert-box {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .info-row {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 5px;
        }
        .label {
            font-weight: bold;
            color: #667eea;
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
        <h1>🔴 Device Offline Alert</h1>
    </div>
    <div class="content">
        <p>Hello,</p>
        
        <div class="alert-box">
            <strong>A device has been offline for longer than expected!</strong>
        </div>

        <div class="info-row">
            <span class="label">Device:</span> {{ $device->hostname }}
        </div>
        <div class="info-row">
            <span class="label">Platform:</span> {{ $device->platform }}
        </div>
        <div class="info-row">
            <span class="label">Offline Duration:</span> {{ $offlineDuration }} minutes
        </div>
        <div class="info-row">
            <span class="label">Last Seen:</span> {{ $device->last_seen->format('Y-m-d H:i:s') }}
        </div>

        <p style="margin-top: 20px;">
            This device has not sent any activity data for longer than the configured threshold. 
            The client may have been stopped or the device may be powered off.
        </p>

        <p>
            <a href="{{ url('/devices/' . $device->hostname) }}" 
               style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                      color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin-top: 10px;">
                View Device Details
            </a>
        </p>
    </div>
    <div class="footer">
        <p>This is an automated notification from Spiego Activity Monitoring System</p>
        <p>To configure notification settings, visit the Admin Settings page</p>
    </div>
</body>
</html>
