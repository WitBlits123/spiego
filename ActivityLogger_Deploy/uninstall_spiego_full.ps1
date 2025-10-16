# Spiego Activity Logger Uninstaller
# Removes ActivityLogger.exe, scheduled task, and install directory

$ErrorActionPreference = 'Stop'

$targetDir = "C:\Spiego"
$exeName = "ActivityLogger.exe"
$targetExe = Join-Path $targetDir $exeName
$taskName = "SpiegoActivityLogger"

# Stop the running process if active
$proc = Get-Process -Name "ActivityLogger" -ErrorAction SilentlyContinue
if ($proc) {
    Write-Host "Stopping running ActivityLogger.exe..." -ForegroundColor Cyan
    $proc | Stop-Process -Force
}

# Remove scheduled task
if (Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue) {
    Write-Host "Removing scheduled task..." -ForegroundColor Cyan
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
}

# Remove EXE
if (Test-Path $targetExe) {
    Write-Host "Deleting $targetExe..." -ForegroundColor Cyan
    Remove-Item $targetExe -Force
}

# Remove directory if empty
if (Test-Path $targetDir) {
    if ((Get-ChildItem $targetDir | Measure-Object).Count -eq 0) {
        Write-Host "Removing $targetDir..." -ForegroundColor Cyan
        Remove-Item $targetDir -Force
    }
}

Write-Host "Uninstall complete!" -ForegroundColor Green
