<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - Spiego</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px 0;
        }
        .container {
            max-width: 900px;
        }
        .header-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .settings-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
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
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            opacity: 0.9;
        }
        .section-title {
            color: #667eea;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
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
                <i class="bi bi-gear-fill"></i>
                <div>
                    <h1>Admin Settings</h1>
                    <p class="text-muted mb-0">Configure email notifications and alerts</p>
                </div>
            </div>
            <div class="mt-3">
                <a href="{{ route('devices.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Devices
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

        <!-- Settings Form -->
        <form action="{{ route('admin.settings.update') }}" method="POST">
            @csrf
            
            <!-- General Settings -->
            <div class="settings-card">
                <h4 class="section-title"><i class="bi bi-envelope"></i> Email Configuration</h4>
                
                <div class="mb-3">
                    <label for="admin_email" class="form-label">Admin Email Address</label>
                    <input type="email" class="form-control @error('admin_email') is-invalid @enderror" 
                           id="admin_email" name="admin_email" 
                           value="{{ old('admin_email', $settings['admin_email']) }}"
                           placeholder="admin@example.com">
                    <div class="form-text">Email address to receive notifications</div>
                    @error('admin_email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="notifications_enabled" 
                           name="notifications_enabled" value="1"
                           {{ old('notifications_enabled', $settings['notifications_enabled']) ? 'checked' : '' }}>
                    <label class="form-check-label" for="notifications_enabled">
                        <strong>Enable Email Notifications</strong>
                        <div class="form-text">Master switch for all email notifications</div>
                    </label>
                </div>
            </div>

            <!-- Mouse Idle Alerts -->
            <div class="settings-card">
                <h4 class="section-title"><i class="bi bi-mouse"></i> Mouse Idle Alerts</h4>
                
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="idle_alert_enabled" 
                           name="idle_alert_enabled" value="1"
                           {{ old('idle_alert_enabled', $settings['idle_alert_enabled']) ? 'checked' : '' }}>
                    <label class="form-check-label" for="idle_alert_enabled">
                        <strong>Enable Idle Alerts</strong>
                        <div class="form-text">Receive alerts when devices are idle for extended periods</div>
                    </label>
                </div>

                <div class="mb-3">
                    <label for="idle_threshold_minutes" class="form-label">Idle Threshold (minutes)</label>
                    <input type="number" class="form-control @error('idle_threshold_minutes') is-invalid @enderror" 
                           id="idle_threshold_minutes" name="idle_threshold_minutes" 
                           value="{{ old('idle_threshold_minutes', $settings['idle_threshold_minutes']) }}"
                           min="1" max="1440">
                    <div class="form-text">Send alert when mouse is idle for longer than this duration</div>
                    @error('idle_threshold_minutes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- Summary Reports -->
            <div class="settings-card">
                <h4 class="section-title"><i class="bi bi-file-earmark-text"></i> Summary Reports</h4>
                
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="summary_report_enabled" 
                           name="summary_report_enabled" value="1"
                           {{ old('summary_report_enabled', $settings['summary_report_enabled']) ? 'checked' : '' }}>
                    <label class="form-check-label" for="summary_report_enabled">
                        <strong>Enable Summary Reports</strong>
                        <div class="form-text">Receive automated activity summary reports via email</div>
                    </label>
                </div>

                <div class="mb-3">
                    <label for="summary_report_frequency" class="form-label">Report Frequency</label>
                    <select class="form-select @error('summary_report_frequency') is-invalid @enderror" 
                            id="summary_report_frequency" name="summary_report_frequency">
                        <option value="daily" {{ old('summary_report_frequency', $settings['summary_report_frequency']) == 'daily' ? 'selected' : '' }}>Daily</option>
                        <option value="weekly" {{ old('summary_report_frequency', $settings['summary_report_frequency']) == 'weekly' ? 'selected' : '' }}>Weekly</option>
                        <option value="monthly" {{ old('summary_report_frequency', $settings['summary_report_frequency']) == 'monthly' ? 'selected' : '' }}>Monthly</option>
                    </select>
                    <div class="form-text">How often to send summary reports</div>
                    @error('summary_report_frequency')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- Offline Device Alerts -->
            <div class="settings-card">
                <h4 class="section-title"><i class="bi bi-wifi-off"></i> Offline Device Alerts</h4>
                
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="offline_alert_enabled" 
                           name="offline_alert_enabled" value="1"
                           {{ old('offline_alert_enabled', $settings['offline_alert_enabled']) ? 'checked' : '' }}>
                    <label class="form-check-label" for="offline_alert_enabled">
                        <strong>Enable Offline Alerts</strong>
                        <div class="form-text">Receive alerts when devices go offline</div>
                    </label>
                </div>

                <div class="mb-3">
                    <label for="offline_threshold_minutes" class="form-label">Offline Threshold (minutes)</label>
                    <input type="number" class="form-control @error('offline_threshold_minutes') is-invalid @enderror" 
                           id="offline_threshold_minutes" name="offline_threshold_minutes" 
                           value="{{ old('offline_threshold_minutes', $settings['offline_threshold_minutes']) }}"
                           min="1" max="1440">
                    <div class="form-text">Send alert when device hasn't been seen for this duration</div>
                    @error('offline_threshold_minutes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- Save Button -->
            <div class="settings-card">
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-save"></i> Save Settings
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
