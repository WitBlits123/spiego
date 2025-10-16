@echo off
title Activity Logger Server
echo Starting Activity Logger Server...
echo.

REM Read config file
for /f "tokens=1,2 delims==" %%a in (server_config.txt) do (
    if "%%a"=="AUTH_KEY" set AUTH_KEY=%%b
    if "%%a"=="HOST" set HOST=%%b
    if "%%a"=="PORT" set PORT=%%b
    if "%%a"=="LICENSE_KEY" set LICENSE_KEY=%%b
)

REM Set environment variable for auth key
set AUTH_KEY=%AUTH_KEY%

echo Configuration:
echo - Host: %HOST%
echo - Port: %PORT%
echo - Auth Key: %AUTH_KEY%
echo - License Key: %LICENSE_KEY%
echo.
echo Server will start in system tray (look for green 'S' icon)
echo Right-click the tray icon to view status or open dashboard
echo.
echo Starting server...

REM Start the server
ActivityLoggerServer.exe --auth-key %AUTH_KEY% --host %HOST% --port %PORT% --license-key %LICENSE_KEY%
