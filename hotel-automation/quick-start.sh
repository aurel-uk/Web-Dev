#!/bin/bash

# ============================================
# HOTEL AUTOMATION - QUICK START (No Docker)
# ============================================
# This script starts n8n locally without Docker
# Uses SQLite for simplicity
# Requires: Node.js 18+, npm
# ============================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

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

# ============================================
# CHECK PREREQUISITES
# ============================================

print_header "QUICK START - NO DOCKER"

print_msg "Checking prerequisites..." "$YELLOW"

# Check Node.js
if ! command -v node &> /dev/null; then
    print_msg "ERROR: Node.js is not installed" "$RED"
    print_msg "Install it from: https://nodejs.org/" "$YELLOW"
    exit 1
fi

NODE_VERSION=$(node -v | cut -d'v' -f2 | cut -d'.' -f1)
if [ "$NODE_VERSION" -lt 18 ]; then
    print_msg "ERROR: Node.js 18+ required (found: $(node -v))" "$RED"
    exit 1
fi

print_msg "✓ Node.js $(node -v) found" "$GREEN"

# Check npm
if ! command -v npm &> /dev/null; then
    print_msg "ERROR: npm is not installed" "$RED"
    exit 1
fi
print_msg "✓ npm $(npm -v) found" "$GREEN"

# ============================================
# INSTALL N8N
# ============================================

print_header "INSTALLING N8N"

# Check if n8n is installed
if ! command -v n8n &> /dev/null; then
    print_msg "Installing n8n globally..." "$YELLOW"
    npm install n8n -g
    print_msg "✓ n8n installed" "$GREEN"
else
    print_msg "✓ n8n already installed ($(n8n --version))" "$GREEN"
fi

# ============================================
# CREATE DATA DIRECTORY
# ============================================

N8N_DATA_DIR="$HOME/.n8n-hotel"
mkdir -p "$N8N_DATA_DIR"

# ============================================
# SET ENVIRONMENT VARIABLES
# ============================================

print_header "CONFIGURING N8N"

export N8N_USER_FOLDER="$N8N_DATA_DIR"
export N8N_PORT=5678
export N8N_PROTOCOL=http
export N8N_HOST=localhost
export GENERIC_TIMEZONE=Europe/Tirane
export N8N_ENCRYPTION_KEY="hotel-automation-encryption-key-32!"

# Basic auth
export N8N_BASIC_AUTH_ACTIVE=true
export N8N_BASIC_AUTH_USER=admin
export N8N_BASIC_AUTH_PASSWORD=hotel2024

# Webhook URL
export WEBHOOK_URL="http://localhost:5678"

print_msg "Configuration set:" "$BLUE"
echo "  Data folder: $N8N_DATA_DIR"
echo "  Port: 5678"
echo "  Username: admin"
echo "  Password: hotel2024"

# ============================================
# START N8N
# ============================================

print_header "STARTING N8N"

print_msg "Starting n8n server..." "$YELLOW"
print_msg "" "$NC"
print_msg "${GREEN}n8n is starting!${NC}" "$NC"
print_msg "" "$NC"
print_msg "Access URL: ${BLUE}http://localhost:5678${NC}" "$NC"
print_msg "Username:   ${BLUE}admin${NC}" "$NC"
print_msg "Password:   ${BLUE}hotel2024${NC}" "$NC"
print_msg "" "$NC"
print_msg "${YELLOW}Press Ctrl+C to stop${NC}" "$NC"
print_msg "" "$NC"

# Start n8n
n8n start
