# Quick Start Guide

## Step 1: Configure

1. Open `config.ini`
2. Change the `auth_key` under `[Security]` to something secure (e.g., `my-secret-key-12345`)

## Step 2: Start the Server

Open a PowerShell terminal and run:

```powershell
cd C:\WebSites\HugoApp
python server.py
```

You should see:
```
Server starting...
Auth key: your-secret-auth-key-change-me
Dashboard: http://127.0.0.1:5000
```

Open `http://127.0.0.1:5000` in your browser.

## Step 3: Start the Client

Open another PowerShell terminal and run:

```powershell
cd C:\WebSites\HugoApp
python activity_logger_client.py
```

You should see:
```
Activity logger started. Sending to http://127.0.0.1:5000/api/events
Press Ctrl+C to stop.
```

## Step 4: View the Dashboard

Refresh your browser at `http://127.0.0.1:5000`. You should see:
- Device count: 1
- Recent events appearing in the timeline
- Your hostname in the devices list
- Activity data populating the charts

## Testing

Try these actions to generate events:
1. Switch between different applications
2. Open a browser and visit different websites
3. Move your mouse around
4. Type some text
5. Let the mouse sit idle for 60+ seconds

Watch the dashboard update with your activity!

## Stopping

Press `Ctrl+C` in each terminal to stop the client and server.

## Next Steps

- Deploy the server to a remote machine
- Configure multiple clients to report to the same server
- Add SSL/HTTPS for production use
- Set up automated backups of `activity_logs.db`
