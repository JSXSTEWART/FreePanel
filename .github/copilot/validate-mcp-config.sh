#!/usr/bin/env bash
#
# MCP Configuration Validator
# 
# This script validates the FreePanel MCP configuration files to ensure they are properly formatted
# and contain all required fields.
#

set -e

echo "🔍 Validating FreePanel MCP Configuration..."
echo

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Counter for errors
ERRORS=0

# Function to check file exists
check_file_exists() {
    local file="$1"
    if [ -f "$file" ]; then
        echo -e "${GREEN}✓${NC} File exists: $file"
        return 0
    else
        echo -e "${RED}✗${NC} File missing: $file"
        ((ERRORS++))
        return 1
    fi
}

# Function to validate JSON
validate_json() {
    local file="$1"
    if command -v jq &> /dev/null; then
        if jq empty "$file" 2>/dev/null; then
            echo -e "${GREEN}✓${NC} Valid JSON: $file"
            return 0
        else
            echo -e "${RED}✗${NC} Invalid JSON: $file"
            jq empty "$file" 2>&1 | head -5
            ((ERRORS++))
            return 1
        fi
    elif command -v python3 &> /dev/null; then
        if python3 -m json.tool "$file" > /dev/null 2>&1; then
            echo -e "${GREEN}✓${NC} Valid JSON: $file"
            return 0
        else
            echo -e "${RED}✗${NC} Invalid JSON: $file"
            python3 -m json.tool "$file" 2>&1 | head -5
            ((ERRORS++))
            return 1
        fi
    else
        echo -e "${YELLOW}⚠${NC}  Cannot validate JSON (jq and python3 not found): $file"
        return 0
    fi
}

# Function to check required fields in MCP JSON
check_mcp_fields() {
    local file="$1"
    
    if ! command -v jq &> /dev/null; then
        echo -e "${YELLOW}⚠${NC}  Skipping field validation (jq not found)"
        return 0
    fi
    
    echo
    echo "Checking required MCP fields..."
    
    # Check mcpVersion
    if jq -e '.mcpVersion' "$file" > /dev/null 2>&1; then
        local version=$(jq -r '.mcpVersion' "$file")
        echo -e "${GREEN}✓${NC} mcpVersion: $version"
    else
        echo -e "${RED}✗${NC} Missing field: mcpVersion"
        ((ERRORS++))
    fi
    
    # Check name
    if jq -e '.name' "$file" > /dev/null 2>&1; then
        local name=$(jq -r '.name' "$file")
        echo -e "${GREEN}✓${NC} name: $name"
    else
        echo -e "${RED}✗${NC} Missing field: name"
        ((ERRORS++))
    fi
    
    # Check description
    if jq -e '.description' "$file" > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} description present"
    else
        echo -e "${RED}✗${NC} Missing field: description"
        ((ERRORS++))
    fi
    
    # Check servers array
    if jq -e '.servers | type == "array"' "$file" > /dev/null 2>&1; then
        local server_count=$(jq '.servers | length' "$file")
        echo -e "${GREEN}✓${NC} servers: $server_count server(s) defined"
        
        # Check each server has required fields
        for i in $(seq 0 $((server_count - 1))); do
            local server_name=$(jq -r ".servers[$i].name" "$file")
            if [[ -n "$server_name" && "$server_name" != "null" ]]; then
                echo -e "  ${GREEN}✓${NC} Server '$server_name' has name"
            else
                echo -e "  ${RED}✗${NC} Server $i missing name"
                ((ERRORS++))
            fi
        done
    else
        echo -e "${RED}✗${NC} Missing or invalid field: servers (must be array)"
        ((ERRORS++))
    fi
    
    # Check tools
    local api_server_tools=$(jq '[.servers[] | select(.name == "freepanel-api") | .tools] | length' "$file")
    if [ "$api_server_tools" -gt 0 ]; then
        local tool_count=$(jq '[.servers[] | select(.name == "freepanel-api") | .tools[]] | length' "$file")
        echo -e "${GREEN}✓${NC} API tools: $tool_count tool(s) defined"
    else
        echo -e "${YELLOW}⚠${NC}  No API tools found in freepanel-api server"
    fi
    
    # Check events
    local webhook_server_events=$(jq '[.servers[] | select(.name == "freepanel-webhooks") | .events] | length' "$file")
    if [ "$webhook_server_events" -gt 0 ]; then
        local event_count=$(jq '[.servers[] | select(.name == "freepanel-webhooks") | .events[]] | length' "$file")
        echo -e "${GREEN}✓${NC} Webhook events: $event_count event(s) defined"
    else
        echo -e "${YELLOW}⚠${NC}  No webhook events found in freepanel-webhooks server"
    fi
}

# Main validation
echo "=== File Existence Check ==="
check_file_exists ".github/copilot/mcp.json"
check_file_exists ".github/copilot/README.md"
check_file_exists ".github/copilot/EXAMPLES.md"
check_file_exists ".github/copilot/QUICK_REFERENCE.md"
check_file_exists ".vscode/settings.json"

echo
echo "=== JSON Syntax Validation ==="
validate_json ".github/copilot/mcp.json"
validate_json ".vscode/settings.json"

echo
echo "=== MCP Configuration Validation ==="
check_mcp_fields ".github/copilot/mcp.json"

# Check environment variables documentation
echo
echo "=== Environment Variables Check ==="
if grep -q "FREEPANEL_API_URL" .github/copilot/README.md; then
    echo -e "${GREEN}✓${NC} FREEPANEL_API_URL documented in README"
else
    echo -e "${RED}✗${NC} FREEPANEL_API_URL not documented"
    ((ERRORS++))
fi

if grep -q "FREEPANEL_API_TOKEN" .github/copilot/README.md; then
    echo -e "${GREEN}✓${NC} FREEPANEL_API_TOKEN documented in README"
else
    echo -e "${RED}✗${NC} FREEPANEL_API_TOKEN not documented"
    ((ERRORS++))
fi

# Check documentation links
echo
echo "=== Documentation Links Check ==="
if grep -q "mcp.json" README.md; then
    echo -e "${GREEN}✓${NC} mcp.json referenced in main README"
else
    echo -e "${YELLOW}⚠${NC}  mcp.json not referenced in main README"
fi

# Summary
echo
echo "========================================="
if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✓ All validations passed!${NC}"
    echo "========================================="
    exit 0
else
    echo -e "${RED}✗ Validation failed with $ERRORS error(s)${NC}"
    echo "========================================="
    exit 1
fi
