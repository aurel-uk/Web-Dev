#!/bin/bash

# ============================================
# HOTEL AUTOMATION - WORKFLOW IMPORT SCRIPT
# ============================================
# This script imports all workflows into n8n
# Prerequisites: n8n must be running
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
N8N_USER="${N8N_USER:-admin}"
N8N_PASSWORD="${N8N_PASSWORD:-HotelAdmin2024!}"

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

# Check if n8n is running
check_n8n() {
    if ! curl -s "${N8N_URL}/healthz" > /dev/null 2>&1; then
        print_msg "ERROR: n8n is not running at ${N8N_URL}" "$RED"
        print_msg "Please start it first with: docker-compose up -d" "$YELLOW"
        exit 1
    fi
}

# Import a workflow using n8n CLI inside container
import_workflow() {
    local workflow_file=$1
    local workflow_name=$(basename "$workflow_file" .json)

    print_msg "Importing: ${workflow_name}..." "$YELLOW"

    # Copy workflow to container and import
    docker cp "$workflow_file" hotel-n8n:/tmp/workflow.json
    docker exec hotel-n8n n8n import:workflow --input=/tmp/workflow.json 2>/dev/null || {
        print_msg "  Warning: Could not import via CLI, workflow may need manual import" "$YELLOW"
        return 1
    }

    print_msg "  ✓ Imported ${workflow_name}" "$GREEN"
}

# ============================================
# MAIN
# ============================================

print_header "IMPORTING N8N WORKFLOWS"

check_n8n
print_msg "✓ n8n is running" "$GREEN"

# Import all workflows
print_msg "" "$NC"
print_msg "Importing workflows..." "$YELLOW"

WORKFLOW_DIR="./workflows"
IMPORTED=0
FAILED=0

for workflow in "$WORKFLOW_DIR"/*.json; do
    if [ -f "$workflow" ]; then
        if import_workflow "$workflow"; then
            ((IMPORTED++))
        else
            ((FAILED++))
        fi
    fi
done

# ============================================
# SUMMARY
# ============================================

print_header "IMPORT COMPLETE"

echo -e "${GREEN}Successfully imported: ${IMPORTED} workflows${NC}"
if [ $FAILED -gt 0 ]; then
    echo -e "${YELLOW}Failed/Skipped: ${FAILED} workflows${NC}"
fi

echo ""
echo -e "${BLUE}Manual Import Instructions (if needed):${NC}"
echo "1. Open n8n at ${N8N_URL}"
echo "2. Go to Workflows"
echo "3. Click '...' menu > Import from File"
echo "4. Select each JSON file from the 'workflows' folder:"
echo ""
for workflow in "$WORKFLOW_DIR"/*.json; do
    if [ -f "$workflow" ]; then
        echo "   - $(basename "$workflow")"
    fi
done
echo ""
echo -e "${YELLOW}After importing, don't forget to:${NC}"
echo "1. Configure credentials in each workflow"
echo "2. Update database connection settings"
echo "3. Activate the workflows"
echo ""
