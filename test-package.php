<?php

// Simple test to verify the package structure and basic functionality
require_once 'vendor/autoload.php';

echo "🧪 Testing Laravel Global Search Package...\n\n";

// Test 1: Check if classes can be loaded
echo "1. Testing class loading...\n";

$classes = [
    'LaravelGlobalSearch\\GlobalSearch\\GlobalSearchServiceProvider',
    'LaravelGlobalSearch\\GlobalSearch\\Support\\MeilisearchClient',
    'LaravelGlobalSearch\\GlobalSearch\\Support\\SearchIndexManager',
    'LaravelGlobalSearch\\GlobalSearch\\Services\\GlobalSearchService',
    'LaravelGlobalSearch\\GlobalSearch\\Traits\\Searchable',
    'LaravelGlobalSearch\\GlobalSearch\\Contracts\\SearchDocumentTransformer',
    'LaravelGlobalSearch\\GlobalSearch\\Contracts\\SearchResultLinkResolver',
    'LaravelGlobalSearch\\GlobalSearch\\Http\\Controllers\\GlobalSearchController',
    'LaravelGlobalSearch\\GlobalSearch\\Jobs\\IndexModelsJob',
    'LaravelGlobalSearch\\GlobalSearch\\Jobs\\DeleteModelsJob',
];

$allLoaded = true;
foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "   ✅ {$class}\n";
    } else {
        echo "   ❌ {$class}\n";
        $allLoaded = false;
    }
}

echo "\n2. Testing configuration file...\n";
if (file_exists('config/global-search.php')) {
    $config = include 'config/global-search.php';
    if (is_array($config) && isset($config['client'], $config['federation'], $config['mappings'])) {
        echo "   ✅ Configuration file is valid\n";
    } else {
        echo "   ❌ Configuration file is missing required sections\n";
        $allLoaded = false;
    }
} else {
    echo "   ❌ Configuration file not found\n";
    $allLoaded = false;
}

echo "\n3. Testing view component...\n";
if (file_exists('resources/views/components/global-search.blade.php')) {
    echo "   ✅ Blade component exists\n";
} else {
    echo "   ❌ Blade component not found\n";
    $allLoaded = false;
}

echo "\n4. Testing routes...\n";
if (file_exists('routes/api.php')) {
    $routes = file_get_contents('routes/api.php');
    if (strpos($routes, 'GlobalSearchController') !== false) {
        echo "   ✅ API routes configured\n";
    } else {
        echo "   ❌ API routes not properly configured\n";
        $allLoaded = false;
    }
} else {
    echo "   ❌ Routes file not found\n";
    $allLoaded = false;
}

echo "\n5. Testing composer.json...\n";
if (file_exists('composer.json')) {
    $composer = json_decode(file_get_contents('composer.json'), true);
    if (isset($composer['name']) && $composer['name'] === 'laravel-global-search/global-search') {
        echo "   ✅ Composer configuration is correct\n";
    } else {
        echo "   ❌ Composer configuration is incorrect\n";
        $allLoaded = false;
    }
} else {
    echo "   ❌ Composer.json not found\n";
    $allLoaded = false;
}

echo "\n" . str_repeat("=", 50) . "\n";

if ($allLoaded) {
    echo "🎉 SUCCESS: Package is ready to use!\n";
    echo "\nNext steps:\n";
    echo "1. Run: composer install\n";
    echo "2. Add to your Laravel app's composer.json\n";
    echo "3. Run: php artisan vendor:publish --tag=global-search-config\n";
    echo "4. Configure your models and run: php artisan search:doctor\n";
} else {
    echo "❌ FAILED: Package has issues that need to be fixed.\n";
}

echo "\n";
