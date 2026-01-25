#!/bin/bash

# ============================================
# HOTEL AUTOMATION SYSTEM - SETUP SCRIPT
# ============================================
# This script sets up the entire hotel automation system
# Requirements: Docker, Docker Compose
# ============================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Print colored message
print_msg() {
    echo -e "${2}${1}${NC}"
}

print_header() {
    echo ""
    echo -e "${BLUE}============================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}============================================${NC}"
    echo ""
}

# Check if command exists
check_command() {
    if ! command -v $1 &> /dev/null; then
        print_msg "ERROR: $1 is not installed. Please install it first." "$RED"
        exit 1
    fi
}

# ============================================
# MAIN SETUP
# ============================================

print_header "HOTEL AUTOMATION SYSTEM SETUP"

# Check prerequisites
print_msg "Checking prerequisites..." "$YELLOW"
check_command docker
check_command docker-compose

print_msg "✓ Docker is installed" "$GREEN"
print_msg "✓ Docker Compose is installed" "$GREEN"

# Create .env file if not exists
if [ ! -f .env ]; then
    print_msg "Creating .env file from template..." "$YELLOW"
    cat > .env << 'EOF'
# ==============================================
# HOTEL AUTOMATION - ENVIRONMENT VARIABLES
# ==============================================

# Database
DB_USER=hotel_admin
DB_PASSWORD=HotelSecure2024!
DB_NAME=hotel_automation

# n8n Configuration
N8N_HOST=0.0.0.0
N8N_PROTOCOL=http
N8N_USER=admin
N8N_PASSWORD=HotelAdmin2024!
N8N_ENCRYPTION_KEY=your-32-character-encryption-key!

# Webhook URL (change to your domain in production)
WEBHOOK_URL=http://localhost:5678

# Timezone
TIMEZONE=Europe/Tirane

# ==============================================
# API KEYS (Replace with real values)
# ==============================================

# OpenAI API Key
OPENAI_API_KEY=sk-your-openai-api-key-here

# WhatsApp Business API
WHATSAPP_PHONE_NUMBER_ID=your-phone-number-id
WHATSAPP_ACCESS_TOKEN=your-access-token
WHATSAPP_VERIFY_TOKEN=your-verify-token

# Twilio
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=your-auth-token
TWILIO_PHONE_NUMBER=+15551234567

# Telegram Bot
TELEGRAM_BOT_TOKEN=1234567890:ABCdefGHIjklMNOpqrsTUVwxyz
TELEGRAM_CHAT_ID=123456789
EOF
    print_msg "✓ Created .env file. Please edit it with your API keys!" "$GREEN"
else
    print_msg "✓ .env file already exists" "$GREEN"
fi

# Start Docker containers
print_header "Starting Docker Containers"
print_msg "Starting PostgreSQL and n8n..." "$YELLOW"

docker-compose down 2>/dev/null || true
docker-compose up -d

# Wait for services to be healthy
print_msg "Waiting for services to be ready..." "$YELLOW"
sleep 10

# Check if PostgreSQL is ready
until docker-compose exec -T postgres pg_isready -U hotel_admin -d hotel_automation > /dev/null 2>&1; do
    print_msg "Waiting for PostgreSQL..." "$YELLOW"
    sleep 2
done
print_msg "✓ PostgreSQL is ready" "$GREEN"

# Check if n8n is ready
until curl -s http://localhost:5678/healthz > /dev/null 2>&1; do
    print_msg "Waiting for n8n..." "$YELLOW"
    sleep 2
done
print_msg "✓ n8n is ready" "$GREEN"

# ============================================
# DISPLAY SUCCESS MESSAGE
# ============================================

print_header "SETUP COMPLETE!"

echo -e "${GREEN}The Hotel Automation System is now running!${NC}"
echo ""
echo -e "${BLUE}Access n8n:${NC}"
echo "  URL: http://localhost:5678"
echo "  Username: admin"
echo "  Password: HotelAdmin2024!"
echo ""
echo -e "${BLUE}Database:${NC}"
echo "  Host: localhost"
echo "  Port: 5432"
echo "  Database: hotel_automation"
echo "  Username: hotel_admin"
echo "  Password: HotelSecure2024!"
echo ""
echo -e "${YELLOW}NEXT STEPS:${NC}"
echo "1. Open http://localhost:5678 in your browser"
echo "2. Log in with the credentials above"
echo "3. Go to Settings > Credentials and add:"
echo "   - PostgreSQL credential"
echo "   - OpenAI API credential"
echo "   - Telegram Bot credential"
echo "4. Import workflows from the 'workflows' folder"
echo "5. Update your API keys in the .env file"
echo ""
echo -e "${YELLOW}To import workflows automatically, run:${NC}"
echo "  ./import-workflows.sh"
echo ""
echo -e "${BLUE}Useful commands:${NC}"
echo "  docker-compose logs -f n8n     # View n8n logs"
echo "  docker-compose logs -f postgres # View database logs"
echo "  docker-compose down            # Stop all services"
echo "  docker-compose restart         # Restart services"
echo ""
