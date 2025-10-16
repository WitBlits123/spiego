# Build script for Spiego Server (Laravel + Scheduler)
# Creates a standalone EXE with system tray support and Laravel scheduler

Write-Host "Building Spiego Server..." -ForegroundColor Green
Write-Host ""

# Ensure we're in the virtual environment
if (-not $env:VIRTUAL_ENV) {
    Write-Host "Activating virtual environment..." -ForegroundColor Yellow
    & "C:\WebSites\HugoApp\.venv\Scripts\Activate.ps1"
}

# Change to Spiego directory
Set-Location "C:\WebSites\HugoApp\Spiego"

# Build the Spiego server EXE
Write-Host "Running PyInstaller..." -ForegroundColor Cyan
python -m PyInstaller --onefile --noconsole --name SpiegoServer `
    --icon=NONE `
    --hidden-import=pystray `
    --hidden-import=PIL `
    --hidden-import=win32api `
    --hidden-import=win32con `
    --hidden-import=subprocess `
    --hidden-import=threading `
    spiego_server.py

Write-Host ""
Write-Host "Build complete!" -ForegroundColor Green
Write-Host "Output: dist\SpiegoServer.exe" -ForegroundColor Cyan
Write-Host ""
Write-Host "Note: Copy the entire Spiego Laravel folder with the SpiegoServer.exe to deploy" -ForegroundColor Yellow
