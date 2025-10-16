# Spiego Activity Logger Full Installer
# Installs ActivityLogger.exe to C:\Spiego, sets to run as admin on startup, and launches it as admin after install

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


# Create a scheduled task to run ActivityLogger.exe at logon with highest privileges
$taskName = "SpiegoActivityLogger"
Write-Host "Creating scheduled task to run ActivityLogger.exe as administrator on logon..." -ForegroundColor Cyan

# Remove any existing task with the same name
if (Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue) {
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
}

$action = New-ScheduledTaskAction -Execute $targetExe
$trigger = New-ScheduledTaskTrigger -AtLogOn
$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Highest
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
$task = New-ScheduledTask -Action $action -Trigger $trigger -Principal $principal -Settings $settings
Register-ScheduledTask -TaskName $taskName -InputObject $task | Out-Null
Write-Host "Scheduled task created successfully." -ForegroundColor Green

# Launch the application as administrator immediately after install
Write-Host "Launching ActivityLogger.exe as administrator..." -ForegroundColor Cyan
Start-Process -FilePath $targetExe -Verb RunAs

Write-Host "Installation complete!" -ForegroundColor Green
Write-Host "Spiego Activity Logger will run on startup with admin privileges."
