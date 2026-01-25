@echo off
REM ============================================
REM HOTEL AUTOMATION - QUICK START (No Docker)
REM ============================================
REM Requires: Node.js 18+
REM ============================================

echo.
echo ============================================
echo   HOTEL AUTOMATION - QUICK START
echo   (No Docker Required)
echo ============================================
echo.

REM Check Node.js
node --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Node.js is not installed!
    echo.
    echo Please install Node.js from:
    echo https://nodejs.org/
    echo.
    pause
    exit /b 1
)

for /f "tokens=*" %%i in ('node --version') do set NODE_VER=%%i
echo [OK] Node.js %NODE_VER% found

REM Check npm
npm --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] npm is not installed!
    pause
    exit /b 1
)

for /f "tokens=*" %%i in ('npm --version') do set NPM_VER=%%i
echo [OK] npm %NPM_VER% found

echo.
echo ============================================
echo   INSTALLING N8N
echo ============================================
echo.

REM Check if n8n is installed
where n8n >nul 2>&1
if %errorlevel% neq 0 (
    echo Installing n8n globally...
    echo This may take a few minutes...
    npm install n8n -g
    if %errorlevel% neq 0 (
        echo [ERROR] Failed to install n8n!
        pause
        exit /b 1
    )
    echo [OK] n8n installed successfully
) else (
    echo [OK] n8n is already installed
)

echo.
echo ============================================
echo   CONFIGURING N8N
echo ============================================
echo.

REM Set environment variables
set N8N_PORT=5678
set N8N_PROTOCOL=http
set N8N_HOST=localhost
set GENERIC_TIMEZONE=Europe/Tirane
set N8N_ENCRYPTION_KEY=hotel-automation-key-32-chars!!
set N8N_BASIC_AUTH_ACTIVE=true
set N8N_BASIC_AUTH_USER=admin
set N8N_BASIC_AUTH_PASSWORD=hotel2024
set WEBHOOK_URL=http://localhost:5678

echo Configuration:
echo   Port:     5678
echo   Username: admin
echo   Password: hotel2024
echo.

echo ============================================
echo   STARTING N8N
echo ============================================
echo.
echo n8n is starting...
echo.
echo Access URL: http://localhost:5678
echo Username:   admin
echo Password:   hotel2024
echo.
echo Press Ctrl+C to stop
echo.

REM Start n8n
n8n start
