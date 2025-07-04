@echo off
echo =========================================
echo MSF110 Quick Fix Tool
echo =========================================
echo.

echo Step 1: Checking current service status...
tasklist | findstr -i "mantra"
if %errorlevel% == 0 (
    echo [INFO] Mantra service is running
) else (
    echo [WARNING] Mantra service not found
)
echo.

echo Step 2: Checking port 11100...
netstat -ano | findstr :11100
if %errorlevel% == 0 (
    echo [INFO] Port 11100 is bound
) else (
    echo [WARNING] Port 11100 not found
)
echo.

echo Step 3: Testing HTTP response...
curl -s -w "HTTP Status: %%{http_code}\n" http://127.0.0.1:11100/rd/info
echo.

echo Step 4: Attempting service restart...
echo Killing existing Mantra processes...
taskkill /f /im MantraRDService.exe 2>nul
taskkill /f /im "Mantra RD Service.exe" 2>nul
timeout /t 3 >nul

echo.
echo Trying to start service from common locations...

if exist "C:\Program Files\Mantra\RDService\MantraRDService.exe" (
    echo Starting from: C:\Program Files\Mantra\RDService\
    start "" "C:\Program Files\Mantra\RDService\MantraRDService.exe"
    goto :servicestarted
)

if exist "C:\Program Files (x86)\Mantra\RDService\MantraRDService.exe" (
    echo Starting from: C:\Program Files (x86)\Mantra\RDService\
    start "" "C:\Program Files (x86)\Mantra\RDService\MantraRDService.exe"
    goto :servicestarted
)

if exist "C:\MantraRD\MantraRDService.exe" (
    echo Starting from: C:\MantraRD\
    start "" "C:\MantraRD\MantraRDService.exe"
    goto :servicestarted
)

echo [ERROR] MantraRDService.exe not found in common locations!
echo Please manually locate and start the service.
goto :end

:servicestarted
echo [INFO] Service start command executed
timeout /t 5 >nul

echo.
echo Step 5: Verifying fix...
echo Checking if service started...
tasklist | findstr -i "mantra"
if %errorlevel% == 0 (
    echo [SUCCESS] ✅ Mantra service is now running!
) else (
    echo [ERROR] ❌ Service failed to start
)

echo.
echo Checking port binding...
netstat -ano | findstr :11100
if %errorlevel% == 0 (
    echo [SUCCESS] ✅ Port 11100 is bound!
) else (
    echo [ERROR] ❌ Port not bound
)

echo.
echo Testing HTTP response...
curl -s http://127.0.0.1:11100/rd/info > temp_response.txt 2>nul
if %errorlevel% == 0 (
    echo [SUCCESS] ✅ HTTP response received!
    echo Response preview:
    type temp_response.txt | head -3
    del temp_response.txt 2>nul
) else (
    echo [ERROR] ❌ No HTTP response
)

:end
echo.
echo =========================================
echo Quick Fix Complete!
echo =========================================
echo.
echo If still not working:
echo 1. Check device USB connection
echo 2. Reinstall device drivers
echo 3. Run Windows Device Manager
echo 4. Contact Mantra support
echo.
pause