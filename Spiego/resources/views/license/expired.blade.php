<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trial Expired - Spiego</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .license-card {
            background: white;
            border-radius: 20px;
            padding: 50px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            text-align: center;
        }
        .logo {
            font-size: 4rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
        }
        .title {
            font-size: 2rem;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        .expired-icon {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 20px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            font-size: 1.1rem;
            border-radius: 10px;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .license-input {
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            text-align: center;
            padding: 15px;
            border-radius: 10px;
            border: 2px solid #dee2e6;
        }
        .license-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
    </style>
</head>
<body>
    <div class="license-card">
        <div class="logo">
            <i class="bi bi-eye"></i>
        </div>
        <div class="title">Spiego</div>
        <p class="text-muted mb-4">Activity Monitoring System</p>

        <div class="expired-icon">
            <i class="bi bi-hourglass-bottom"></i>
        </div>

        <h2 class="mb-3">Trial Period Expired</h2>
        <p class="text-muted mb-4">
            Your 10-minute trial period has ended. Please enter a valid license key to continue using Spiego.
        </p>

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

        <form action="{{ route('license.activate') }}" method="POST" class="mt-4">
            @csrf
            <div class="mb-3">
                <input type="text" 
                       name="license_key" 
                       class="form-control license-input" 
                       placeholder="SPIEGO-XXXXX-XXXXX-XXXXX-XXXXX"
                       maxlength="30"
                       required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100">
                <i class="bi bi-key"></i> Activate License
            </button>
        </form>

        <div class="mt-4">
            <small class="text-muted">
                Need a license key? Contact your administrator.
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
