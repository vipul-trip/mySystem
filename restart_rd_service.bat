@echo off
echo ========================================
echo MSF110 Mantra RD Service Restart Script
echo ========================================
echo.

echo Checking current service status...
tasklist | findstr -i "mantra" > nul
if %errorlevel% == 0 (
    echo Mantra service is currently running.
    echo Stopping MSF110 RD Service...
    taskkill /f /im MantraRDService.exe 2>nul
    taskkill /f /im "Mantra RD Service.exe" 2>nul
    timeout /t 3 > nul
) else (
    echo Mantra service is not currently running.
)

echo.
echo Checking port 11100...
netstat -ano | findstr :11100
echo.

echo Starting MSF110 RD Service...
echo Please check these common locations:
echo.

if exist "C:\Program Files\Mantra\RDService\MantraRDService.exe" (
    echo Found: C:\Program Files\Mantra\RDService\MantraRDService.exe
    start "" "C:\Program Files\Mantra\RDService\MantraRDService.exe"
) else if exist "C:\Program Files (x86)\Mantra\RDService\MantraRDService.exe" (
    echo Found: C:\Program Files (x86)\Mantra\RDService\MantraRDService.exe
    start "" "C:\Program Files (x86)\Mantra\RDService\MantraRDService.exe"
) else if exist "C:\MantraRD\MantraRDService.exe" (
    echo Found: C:\MantraRD\MantraRDService.exe
    start "" "C:\MantraRD\MantraRDService.exe"
) else (
    echo ERROR: MantraRDService.exe not found in common locations!
    echo Please locate and start the service manually.
    echo.
    echo Common installation paths:
    echo - C:\Program Files\Mantra\RDService\
    echo - C:\Program Files (x86)\Mantra\RDService\
    echo - C:\MantraRD\
    echo.
)

timeout /t 5 > nul

echo.
echo Verifying service is running...
timeout /t 2 > nul
tasklist | findstr -i "mantra"
echo.

echo Checking port binding...
netstat -ano | findstr :11100
echo.

echo Testing service response...
curl -s http://127.0.0.1:11100/rd/info > nul 2>&1
if %errorlevel% == 0 (
    echo SUCCESS: Service is responding!
) else (
    echo WARNING: Service may not be responding properly.
    echo Try testing manually with: curl http://127.0.0.1:11100/rd/info
)

echo.
echo ========================================
echo Script completed!
echo ========================================
pause