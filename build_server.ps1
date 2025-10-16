# Build script for Activity Logger Server
# Creates a standalone EXE with system tray support

Write-Host "Building Activity Logger Server..." -ForegroundColor Green

# Build the server EXE
python -m PyInstaller --onefile --noconsole --name ActivityLoggerServer `
    --add-data "templates;templates" `
    --add-data "static;static" `
    --hidden-import=pystray `
    --hidden-import=PIL `
    --hidden-import=win32timezone `
    --hidden-import=win32api `
    --hidden-import=win32con `
    --hidden-import=flask `
    --hidden-import=sqlite3 `
    server_tray.py

Write-Host ""
Write-Host "Build complete!" -ForegroundColor Green
Write-Host "Output: dist\ActivityLoggerServer.exe" -ForegroundColor Cyan
