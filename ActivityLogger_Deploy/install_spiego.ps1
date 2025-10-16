# Spiego Activity Logger Installer
# This script installs ActivityLogger.exe to C:\Spiego\ and sets it to run as admin on startup

$ErrorActionPreference = 'Stop'

# Define paths
$targetDir = "C:\Spiego"
$exeName = "ActivityLogger.exe"
$sourceExe = Join-Path $PSScriptRoot $exeName
$targetExe = Join-Path $targetDir $exeName

# Create target directory if it doesn't exist
if (!(Test-Path $targetDir)) {
    Write-Host "Creating $targetDir..." -ForegroundColor Cyan
    New-Item -ItemType Directory -Path $targetDir | Out-Null
}

# Copy EXE to target directory
Write-Host "Copying $exeName to $targetDir..." -ForegroundColor Cyan
Copy-Item $sourceExe $targetExe -Force

# Create a shortcut in Startup folder
$WshShell = New-Object -ComObject WScript.Shell
$startupPath = [Environment]::GetFolderPath('Startup')
$shortcutPath = Join-Path $startupPath "Spiego Activity Logger.lnk"

Write-Host "Creating startup shortcut..." -ForegroundColor Cyan
$shortcut = $WshShell.CreateShortcut($shortcutPath)
$shortcut.TargetPath = $targetExe
$shortcut.WorkingDirectory = $targetDir
$shortcut.WindowStyle = 1
$shortcut.Description = "Spiego Activity Logger"
$shortcut.IconLocation = $targetExe
$shortcut.Save()

# Set shortcut to run as administrator (requires modifying .lnk file)
# This requires PowerShell 7+ and the Windows Script Host Object Model
try {
    $bytes = [System.IO.File]::ReadAllBytes($shortcutPath)
    $bytes[0x15] = $bytes[0x15] -bor 0x20
    [System.IO.File]::WriteAllBytes($shortcutPath, $bytes)
    Write-Host "Set shortcut to run as administrator." -ForegroundColor Green
} catch {
    Write-Warning "Could not set shortcut to run as administrator. Please set manually if needed."
}

Write-Host "Installation complete!" -ForegroundColor Green
Write-Host "Spiego Activity Logger will run on startup."
