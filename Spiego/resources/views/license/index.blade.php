<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License - Spiego</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px 0;
        }
        .container {
            max-width: 800px;
        }
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
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
        .status-badge {
            font-size: 1.2rem;
            padding: 10px 20px;
            border-radius: 10px;
        }
        .license-input {
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
            text-align: center;
        }
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo">
                <i class="bi bi-eye"></i>
                <div>
                    <h1>Spiego</h1>
                    <p class="text-muted mb-0">License Management</p>
                </div>
            </div>

            <a href="{{ route('devices.index') }}" class="btn btn-outline-secondary mb-4">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>

            @if(session('success'))
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> {{ $errors->first() }}
                </div>
            @endif

            <!-- License Status -->
            <div class="text-center mb-4">
                <h3 class="mb-3">License Status</h3>
                
                @if($status['type'] === 'licensed')
                    <span class="status-badge badge bg-success">
                        <i class="bi bi-check-circle"></i> Licensed
                    </span>
                    <p class="text-muted mt-3">{{ $status['message'] }}</p>
                @elseif($status['type'] === 'trial')
                    <span class="status-badge badge bg-warning">
                        <i class="bi bi-hourglass-split"></i> Trial Mode
                    </span>
                    <p class="text-muted mt-3">{{ $status['message'] }}</p>
                @else
                    <span class="status-badge badge bg-danger">
                        <i class="bi bi-x-circle"></i> Expired
                    </span>
                    <p class="text-muted mt-3">{{ $status['message'] }}</p>
                @endif
            </div>

            <hr class="my-4">

            <!-- License Activation Form -->
            <h4 class="mb-3">Activate License</h4>
            <form action="{{ route('license.activate') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label">License Key</label>
                    <input type="text" 
                           name="license_key" 
                           class="form-control license-input" 
                           placeholder="SPIEGO-XXXXX-XXXXX-XXXXX-XXXXX"
                           maxlength="30"
                           value="{{ old('license_key') }}"
                           required>
                    <small class="text-muted">Enter your license key in the format shown above</small>
                </div>
                <button type="submit" class="btn btn-gradient btn-lg w-100">
                    <i class="bi bi-key"></i> Activate License
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
