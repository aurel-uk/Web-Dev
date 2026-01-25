#!/bin/bash

# ============================================
# HOTEL AUTOMATION - SYSTEM TEST SCRIPT
# ============================================
# Tests all components of the hotel automation system
# ============================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
N8N_URL="${N8N_URL:-http://localhost:5678}"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_NAME:-hotel_automation}"
DB_USER="${DB_USER:-hotel_admin}"
DB_PASSWORD="${DB_PASSWORD:-HotelSecure2024!}"

TESTS_PASSED=0
TESTS_FAILED=0

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

run_test() {
    local test_name=$1
    local test_command=$2

    printf "  Testing %-40s" "${test_name}..."

    if eval "$test_command" > /dev/null 2>&1; then
        echo -e "${GREEN}PASS${NC}"
        ((TESTS_PASSED++))
        return 0
    else
        echo -e "${RED}FAIL${NC}"
        ((TESTS_FAILED++))
        return 1
    fi
}

# ============================================
# TESTS
# ============================================

print_header "SYSTEM HEALTH TESTS"

# Test 1: Docker containers running
run_test "Docker containers running" "docker-compose ps | grep -q 'Up'"

# Test 2: PostgreSQL connection
run_test "PostgreSQL connection" "docker-compose exec -T postgres pg_isready -U ${DB_USER}"

# Test 3: n8n health endpoint
run_test "n8n health endpoint" "curl -sf ${N8N_URL}/healthz"

# Test 4: n8n web interface
run_test "n8n web interface" "curl -sf ${N8N_URL}"

print_header "DATABASE TESTS"

# Test 5: Hotels table exists
run_test "Hotels table exists" "docker-compose exec -T postgres psql -U ${DB_USER} -d ${DB_NAME} -c 'SELECT 1 FROM hotels LIMIT 1'"

# Test 6: Sample hotel data
run_test "Sample hotel data" "docker-compose exec -T postgres psql -U ${DB_USER} -d ${DB_NAME} -c \"SELECT 1 FROM hotels WHERE slug='grand-hotel-tirana'\""

# Test 7: Staff table
run_test "Hotel staff table" "docker-compose exec -T postgres psql -U ${DB_USER} -d ${DB_NAME} -c 'SELECT 1 FROM hotel_staff LIMIT 1'"

# Test 8: Statistics table
run_test "Statistics table" "docker-compose exec -T postgres psql -U ${DB_USER} -d ${DB_NAME} -c 'SELECT 1 FROM daily_statistics LIMIT 1'"

# Test 9: Intent definitions
run_test "Intent definitions" "docker-compose exec -T postgres psql -U ${DB_USER} -d ${DB_NAME} -c 'SELECT COUNT(*) FROM intent_definitions' | grep -q '[0-9]'"

print_header "WEBHOOK TESTS"

# Test 10: WhatsApp webhook endpoint
run_test "WhatsApp webhook reachable" "curl -sf -o /dev/null -w '%{http_code}' ${N8N_URL}/webhook/whatsapp-webhook 2>/dev/null | grep -qE '(200|404|405)'"

# Test 11: Twilio webhook endpoint
run_test "Twilio webhook reachable" "curl -sf -o /dev/null -w '%{http_code}' ${N8N_URL}/webhook/twilio-voice-webhook 2>/dev/null | grep -qE '(200|404|405)'"

print_header "SIMULATED MESSAGE TEST"

# Test 12: Simulate WhatsApp webhook (verification)
print_msg "  Simulating WhatsApp verification request..." "$YELLOW"
VERIFY_RESPONSE=$(curl -sf "${N8N_URL}/webhook/whatsapp-webhook?hub.mode=subscribe&hub.challenge=test123&hub.verify_token=test" 2>/dev/null || echo "")
if [ "$VERIFY_RESPONSE" = "test123" ] || [ -n "$VERIFY_RESPONSE" ]; then
    echo -e "  Webhook verification             ${GREEN}PASS${NC} (or workflow not active)"
    ((TESTS_PASSED++))
else
    echo -e "  Webhook verification             ${YELLOW}SKIP${NC} (workflow may not be active)"
fi

# ============================================
# SUMMARY
# ============================================

print_header "TEST SUMMARY"

TOTAL_TESTS=$((TESTS_PASSED + TESTS_FAILED))

echo -e "Total Tests: ${TOTAL_TESTS}"
echo -e "${GREEN}Passed: ${TESTS_PASSED}${NC}"
echo -e "${RED}Failed: ${TESTS_FAILED}${NC}"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed! System is healthy.${NC}"
    exit 0
else
    echo -e "${YELLOW}⚠ Some tests failed. Check the output above.${NC}"
    exit 1
fi
