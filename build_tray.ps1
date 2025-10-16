# Build script for Activity Logger (System Tray - No Console)
# Creates a standalone EXE that runs in the system tray

# Install PyInstaller if not already installed
pip install pyinstaller

# Build the EXE with no console window (--noconsole flag)
pyinstaller --onefile `
  --noconsole `
  --name ActivityLogger `
  --icon=NONE `
  --add-data "config.ini;." `
  --hidden-import=pystray `
  --hidden-import=PIL `
  --hidden-import=win32timezone `
  --hidden-import=win32api `
  --hidden-import=win32con `
  activity_logger_tray.py

Write-Host ""
Write-Host "Build complete!" -ForegroundColor Green
Write-Host "Executable: dist\ActivityLogger.exe" -ForegroundColor Cyan
Write-Host ""
Write-Host "This version runs in the system tray with NO console window." -ForegroundColor Yellow
Write-Host "Right-click the tray icon for options:" -ForegroundColor Yellow
Write-Host "  - View Status" -ForegroundColor Gray
Write-Host "  - Open Dashboard" -ForegroundColor Gray
Write-Host "  - Exit" -ForegroundColor Gray
