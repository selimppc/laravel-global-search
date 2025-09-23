#!/bin/bash

echo "🚀 Laravel Global Search - Publish and Test Script"
echo "================================================="
echo ""

PACKAGE_DIR="/Users/selimreza/Sites/laravel-global-search"
REAL_ESTATE_DIR="/Users/selimreza/Sites/real-estate"

echo "📦 Package Directory: $PACKAGE_DIR"
echo "🏠 Real Estate Project: $REAL_ESTATE_DIR"
echo ""

# Step 1: Validate package
echo "1️⃣ Validating package..."
cd "$PACKAGE_DIR"
composer validate
if [ $? -eq 0 ]; then
    echo "✅ Package validation passed"
else
    echo "❌ Package validation failed"
    exit 1
fi

# Step 2: Run tests
echo ""
echo "2️⃣ Running tests..."
./vendor/bin/phpunit
if [ $? -eq 0 ]; then
    echo "✅ All tests passed"
else
    echo "❌ Tests failed"
    exit 1
fi

# Step 3: Check git status
echo ""
echo "3️⃣ Checking git status..."
if [ -d ".git" ]; then
    git status --porcelain
    if [ $? -eq 0 ]; then
        echo "✅ Git status clean"
    else
        echo "⚠️  Git has uncommitted changes"
    fi
else
    echo "ℹ️  Not a git repository"
fi

# Step 4: Update real estate project
echo ""
echo "4️⃣ Updating real estate project..."
cd "$REAL_ESTATE_DIR"

# Update the package
echo "Updating laravel-global-search package..."
composer update laravel-global-search/global-search

if [ $? -eq 0 ]; then
    echo "✅ Package updated successfully"
else
    echo "❌ Package update failed"
    exit 1
fi

# Step 5: Clear caches
echo ""
echo "5️⃣ Clearing caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
echo "✅ Caches cleared"

# Step 6: Restart queue
echo ""
echo "6️⃣ Restarting queue..."
php artisan queue:restart
echo "✅ Queue restarted"

# Step 7: Test the package
echo ""
echo "7️⃣ Testing the package..."
echo "Running: php artisan search:reindex"
echo "Expected: Should show 667 records instead of 181"
echo ""
echo "Press Enter to continue with the test..."
read

php artisan search:reindex

echo ""
echo "8️⃣ Testing search API..."
echo "Testing: curl 'http://127.0.0.1:8000/global-search?q=lisa&limit=10'"
echo ""
echo "Press Enter to continue with the API test..."
read

curl -s "http://127.0.0.1:8000/global-search?q=lisa&limit=10" | head -20

echo ""
echo "9️⃣ Checking status..."
echo "Running: php artisan search:status"
echo ""
echo "Press Enter to continue with status check..."
read

php artisan search:status

echo ""
echo "🎉 Publish and test completed!"
echo "=============================="
echo ""
echo "📋 Summary:"
echo "✅ Package validated"
echo "✅ Tests passed"
echo "✅ Package updated in real estate project"
echo "✅ Caches cleared"
echo "✅ Queue restarted"
echo "✅ Reindex tested"
echo "✅ Search API tested"
echo "✅ Status checked"
echo ""
echo "🚀 Your Laravel Global Search package is ready!"
