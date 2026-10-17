@echo off
setlocal

cd /d "%~dp0"

echo ============================================================
echo DxClinic Automated Test Runner
echo ============================================================
echo.

REM 1. Ensure Application Server is running
call "%~dp0start_dxclinic.bat"
if %errorlevel% neq 0 (
    echo [FAIL] Could not ensure DxClinic application server is running.
    exit /b %errorlevel%
)

REM 2. Determine Test Target
set TARGET=%~1
if "%TARGET%"=="" set TARGET=playwright/revision33_settings_pwa_designer_e2e.js

echo.
echo ============================================================
echo Executing Test Target: %TARGET%
echo ============================================================
echo.

if "%TARGET%"=="php" (
    php artisan test
    set TEST_EXIT=%errorlevel%
) else if "%TARGET%"=="phpunit" (
    php artisan test
    set TEST_EXIT=%errorlevel%
) else (
    node "%TARGET%"
    set TEST_EXIT=%errorlevel%
)

echo.
echo ============================================================
echo Test Execution Finished with Exit Code: %TEST_EXIT%
echo ============================================================
exit /b %TEST_EXIT%
