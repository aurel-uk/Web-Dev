@echo off
REM ============================================
REM HOTEL AUTOMATION SYSTEM - WINDOWS SETUP
REM ============================================
REM Requires: Docker Desktop for Windows
REM ============================================

echo.
echo ============================================
echo   HOTEL AUTOMATION SYSTEM - SETUP
echo ============================================
echo.

REM Check if Docker is running
docker --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Docker is not installed or not running!
    echo.
    echo Please install Docker Desktop from:
    echo https://www.docker.com/products/docker-desktop/
    echo.
    pause
    exit /b 1
)

echo [OK] Docker is installed
echo.

REM Check if docker-compose is available
docker-compose --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Docker Compose is not available!
    pause
    exit /b 1
)

echo [OK] Docker Compose is available
echo.

REM Create .env file if not exists
if not exist .env (
    echo Creating .env file...
    (
        echo # HOTEL AUTOMATION - ENVIRONMENT VARIABLES
        echo.
        echo # Database
        echo DB_USER=hotel_admin
        echo DB_PASSWORD=HotelSecure2024!
        echo DB_NAME=hotel_automation
        echo.
        echo # n8n Configuration
        echo N8N_HOST=0.0.0.0
        echo N8N_PROTOCOL=http
        echo N8N_USER=admin
        echo N8N_PASSWORD=HotelAdmin2024!
        echo N8N_ENCRYPTION_KEY=your-32-character-encryption-key!
        echo.
        echo # Webhook URL
        echo WEBHOOK_URL=http://localhost:5678
        echo.
        echo # Timezone
        echo TIMEZONE=Europe/Tirane
        echo.
        echo # API Keys - REPLACE WITH YOUR REAL KEYS
        echo OPENAI_API_KEY=sk-your-openai-api-key-here
        echo TELEGRAM_BOT_TOKEN=your-telegram-bot-token
        echo TELEGRAM_CHAT_ID=your-chat-id
    ) > .env
    echo [OK] Created .env file
) else (
    echo [OK] .env file already exists
)

echo.
echo ============================================
echo   STARTING DOCKER CONTAINERS
echo ============================================
echo.

REM Stop any existing containers
docker-compose down >nul 2>&1

REM Start containers
echo Starting PostgreSQL and n8n...
docker-compose up -d

if %errorlevel% neq 0 (
    echo [ERROR] Failed to start containers!
    pause
    exit /b 1
)

echo.
echo Waiting for services to start...
timeout /t 15 /nobreak >nul

echo.
echo ============================================
echo   SETUP COMPLETE!
echo ============================================
echo.
echo n8n is now running!
echo.
echo   URL:      http://localhost:5678
echo   Username: admin
echo   Password: HotelAdmin2024!
echo.
echo Database:
echo   Host:     localhost
echo   Port:     5432
echo   Database: hotel_automation
echo   User:     hotel_admin
echo   Password: HotelSecure2024!
echo.
echo ============================================
echo   NEXT STEPS
echo ============================================
echo.
echo 1. Open http://localhost:5678 in your browser
echo 2. Login with admin / HotelAdmin2024!
echo 3. Go to Settings ^> Credentials
echo 4. Add PostgreSQL, OpenAI, and Telegram credentials
echo 5. Import workflows from the 'workflows' folder
echo.
echo To view logs:  docker-compose logs -f
echo To stop:       docker-compose down
echo.
pause
