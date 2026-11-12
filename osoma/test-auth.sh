#!/bin/bash

# Test script for Osoma authentication

echo "🔐 Osoma Authentication Test Script"
echo "======================================"
echo ""

# Check if Keycloak is running
echo "1. Checking Keycloak status..."
if docker compose ps keycloak | grep -q "Up"; then
    echo "   ✅ Keycloak is running"
else
    echo "   ❌ Keycloak is not running"
    echo "   Run: docker compose up -d keycloak"
    exit 1
fi

# Check if API is running
echo ""
echo "2. Checking API status..."
if docker compose ps php | grep -q "Up"; then
    echo "   ✅ API (php) is running"
else
    echo "   ❌ API is not running"
    echo "   Run: docker compose up -d"
    exit 1
fi

# Restart Keycloak to load new client config
echo ""
echo "3. Restarting Keycloak to load Osoma client..."
docker compose restart keycloak keycloak-config-cli
sleep 5
echo "   ✅ Keycloak restarted"

# Check Osoma dev server
echo ""
echo "4. Checking Osoma dev server..."
if curl -s http://localhost:5173 > /dev/null; then
    echo "   ✅ Osoma dev server is running on http://localhost:5173"
else
    echo "   ⚠️  Osoma dev server is not running"
    echo "   Run: cd osoma && npm run dev"
fi

echo ""
echo "======================================"
echo "✨ Setup complete!"
echo ""
echo "📝 Next steps:"
echo "1. Open http://localhost:5173 in your browser"
echo "2. By default, you're in Mock Mode (no login required)"
echo "3. To test real authentication:"
echo "   - Open browser console"
echo "   - Run: localStorage.setItem('use_msw', 'false')"
echo "   - Refresh the page"
echo "   - Click the 'Login' button in the top-right"
echo ""
echo "🔑 Test credentials:"
echo "   Username: admin"
echo "   Password: metadatapp-local-dev-password"
echo ""
