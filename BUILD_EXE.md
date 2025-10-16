# Building the Executable

This guide shows how to build a standalone Windows executable (.exe) from the activity logger client.

## Prerequisites

- Python 3.8+ installed
- All dependencies installed

## Quick Build

### Option 1: Automatic Build (Recommended)

Run the build script which handles everything:

```powershell
python build_exe.py
```

This will:
1. Install PyInstaller if needed
2. Build the executable
3. Place it in `dist/ActivityLogger.exe`

### Option 2: Manual Build

1. Install PyInstaller:
```powershell
pip install -r requirements-build.txt
```

2. Build using the spec file:
```powershell
pyinstaller ActivityLogger.spec
```

Or build with command line options:
```powershell
pyinstaller --onefile --name ActivityLogger --console activity_logger_client.py
```

## Output

After building, you'll find:
- **`dist/ActivityLogger.exe`** - The standalone executable (this is what you need)
- `build/` - Temporary build files (can be deleted)
- `ActivityLogger.spec` - Build configuration (keep for rebuilding)

## Deployment

### Package for Deployment

1. Create a deployment folder:
```powershell
mkdir ActivityLogger_Deploy
```

2. Copy these files:
```powershell
copy dist\ActivityLogger.exe ActivityLogger_Deploy\
copy config.ini ActivityLogger_Deploy\
```

3. Edit `ActivityLogger_Deploy\config.ini`:
   - Set the correct server IP/hostname
   - Set the correct auth_key
   - Adjust other settings as needed

4. Zip the folder and distribute:
```powershell
Compress-Archive -Path ActivityLogger_Deploy -DestinationPath ActivityLogger_Deploy.zip
```

### Installing on Target Machines

1. Extract `ActivityLogger_Deploy.zip`
2. Edit `config.ini` if needed
3. Double-click `ActivityLogger.exe` or run from command line
4. The logger will start and run in the background

### Running as Windows Service (Advanced)

To run the logger automatically on startup:

**Option A: Task Scheduler**
1. Open Task Scheduler
2. Create Task → General tab:
   - Name: "Activity Logger"
   - Run whether user is logged on or not
   - Run with highest privileges
3. Triggers tab → New:
   - Begin: At startup
4. Actions tab → New:
   - Action: Start a program
   - Program: `C:\Path\To\ActivityLogger.exe`
   - Start in: `C:\Path\To\` (folder containing config.ini)

**Option B: Startup Folder**
1. Press Win+R, type `shell:startup`
2. Create a shortcut to `ActivityLogger.exe` in that folder

## Troubleshooting

### Build Errors

**"Module not found" error:**
```powershell
pip install -r requirements.txt
pip install pyinstaller
```

**"UPX is not available" warning:**
- This is safe to ignore, or install UPX from https://upx.github.io/

**Large EXE size:**
- Normal size: 30-50 MB (includes Python runtime and all dependencies)
- Use `--onefile` for single EXE (slower startup) or omit for faster startup with multiple files

### Runtime Errors

**"config.ini not found":**
- Make sure config.ini is in the same folder as ActivityLogger.exe

**"Failed to connect to server":**
- Check server IP/port in config.ini
- Verify server is running
- Check firewall settings

**"Missing dependencies" in console:**
- Rebuild the EXE with updated spec file
- Add missing modules to `hiddenimports` in ActivityLogger.spec

## Customization

### Hide Console Window

Edit `ActivityLogger.spec`, change:
```python
console=False,  # No console window
```

Then rebuild:
```powershell
pyinstaller ActivityLogger.spec
```

### Add Icon

1. Get a .ico file (icon image)
2. Edit `ActivityLogger.spec`, change:
```python
icon='path/to/icon.ico',
```

### Change EXE Name

Edit `ActivityLogger.spec`, change:
```python
name='YourAppName',
```

## File Sizes

Typical sizes:
- **Executable (onefile)**: ~35-45 MB
- **Executable (onedir)**: ~25 MB + folder with DLLs
- **Deployment package**: ~40-50 MB (with config)

## Security Notes

⚠️ Important:
- The config.ini file contains the auth_key in plain text
- Protect the deployment package
- Use strong, unique auth_keys for each deployment
- Consider encrypting config.ini for production use

## Advanced: Silent Background Operation

To run completely silently (no window, no tray icon):

1. Build with `console=False` in spec file
2. Use Windows Task Scheduler to run at startup
3. Configure logging to file in the client code if needed

## Distribution Checklist

Before distributing:
- ✅ Test the EXE on a clean Windows machine (without Python)
- ✅ Verify config.ini has correct server details
- ✅ Test network connectivity from target machines
- ✅ Document any firewall rules needed
- ✅ Create uninstall instructions (just delete folder + remove from startup)
- ✅ Consider code signing for production (prevents Windows warnings)

## Code Signing (Optional)

To avoid "Unknown Publisher" warnings:

1. Get a code signing certificate
2. Sign the EXE:
```powershell
signtool sign /f certificate.pfx /p password /t http://timestamp.digicert.com ActivityLogger.exe
```

This is recommended for production deployments.
