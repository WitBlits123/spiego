# Activity Logger - Deployment Package

## What's Included

- **ActivityLogger.exe** - Standalone Windows executable (no Python needed!)
- **config.ini** - Configuration file

## Quick Start

1. Edit `config.ini`:
   - Change `host` to your server IP/hostname
   - Change `auth_key` to match your server's auth key
   
2. Double-click `ActivityLogger.exe` or run from command line:
   ```
   ActivityLogger.exe
   ```

3. The logger will start collecting activity and sending to your server.

## Configuration

Edit `config.ini` to customize:

```ini
[Server]
host = 127.0.0.1        # Your server IP or hostname
port = 5000             # Server port
use_ssl = false         # Set to true for HTTPS

[Security]
auth_key = your-secret-auth-key-change-me  # Must match server

[Logging]
send_interval = 5       # Seconds between sending batches
idle_threshold = 60     # Mouse idle timeout
```

## Running at Startup

### Option 1: Startup Folder (Simple)
1. Press `Win + R`
2. Type `shell:startup` and press Enter
3. Create a shortcut to `ActivityLogger.exe` in that folder

### Option 2: Task Scheduler (Advanced)
1. Open Task Scheduler
2. Create Basic Task
3. Set trigger: "When the computer starts"
4. Set action: Start program → Browse to ActivityLogger.exe
5. Check "Run with highest privileges"

## Stopping the Logger

- If running in console: Press `Ctrl+C`
- If running in background: Use Task Manager to end the process

## Troubleshooting

**"config.ini not found"**
- Make sure config.ini is in the same folder as ActivityLogger.exe

**"Failed to connect to server"**
- Check server IP and port in config.ini
- Verify server is running
- Check firewall settings

**Console window closes immediately**
- Run from Command Prompt to see error messages:
  ```
  cd C:\Path\To\ActivityLogger
  ActivityLogger.exe
  ```

## Uninstall

1. Stop the ActivityLogger.exe process
2. Remove from startup (if configured)
3. Delete the ActivityLogger folder

## Support

For issues or questions, contact your system administrator.

---

**Privacy Notice**: This software monitors device activity. Only use on authorized devices with proper consent.
