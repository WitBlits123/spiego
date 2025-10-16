# Activity Logger Server - Deployment Guide

## Quick Start

1. **Edit `server_config.txt`** - Set your authentication key
2. **Double-click `START_SERVER.bat`** to start the server
3. The server will run silently in the system tray (green circle with 'S')
4. Right-click the tray icon to:
   - View Status - See server statistics
   - Open Dashboard - View activity logs in browser

## Files Included

- `ActivityLoggerServer.exe` - Server application (runs in system tray)
- `START_SERVER.bat` - Quick start script
- `server_config.txt` - Configuration file
- `README.txt` - This file
- `templates/` - Web dashboard templates (embedded in EXE)

## Configuration

Edit `server_config.txt` and set your authentication key:

```
AUTH_KEY=your-secret-auth-key-here
HOST=0.0.0.0
PORT=5000
```

**Important:** Make sure clients use the same AUTH_KEY in their `config.ini`

## Running the Server

### Option 1: Quick Start (Recommended)
Double-click `START_SERVER.bat`

### Option 2: Manual Start
```
ActivityLoggerServer.exe --auth-key YOUR-KEY-HERE --host 0.0.0.0 --port 5000
```

## Accessing the Dashboard

Once running, open a web browser and go to:
- `http://localhost:5000` (from the server machine)
- `http://SERVER-IP:5000` (from other machines on your network)

## System Tray

The server runs with no console window. You'll see a green icon with 'S' in your system tray.

Right-click the icon for options:
- **View Status** - Shows uptime, event count, active devices
- **Open Dashboard** - Opens the web interface in your default browser

## Database

Activity logs are stored in `activity_logs.db` (SQLite database).
- Automatically created on first run
- Located in the same folder as the EXE
- Can be deleted to clear all data

## Firewall

If clients can't connect, you may need to allow port 5000 through Windows Firewall:

```powershell
netsh advfirewall firewall add rule name="Activity Logger Server" dir=in action=allow protocol=TCP localport=5000
```

## Security Notes

1. **Change the default AUTH_KEY** - Never use the default key in production
2. **HTTPS** - For production, consider using a reverse proxy (nginx/Apache) with SSL
3. **Network** - By default, the server binds to `0.0.0.0` (all interfaces)
   - To restrict to localhost only, set `HOST=127.0.0.1` in `server_config.txt`

## Troubleshooting

### Server won't start
- Check if port 5000 is already in use
- Try a different port by editing `server_config.txt`

### Clients can't connect
- Verify firewall allows port 5000
- Check clients have correct SERVER IP in their `config.ini`
- Verify AUTH_KEY matches between server and clients

### Dashboard shows no data
- Check that clients are running and sending data
- Verify AUTH_KEY matches
- Right-click tray icon → View Status to see event count

## Support

For issues or questions, check:
- Server status via tray icon
- Database file exists (`activity_logs.db`)
- Network connectivity between clients and server
- Authentication keys match
