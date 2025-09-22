<?php

// Simple test to verify the package structure
echo "🧪 Testing Laravel Global Search Package Structure...\n\n";

// Test 1: Check if all required files exist
echo "1. Testing file structure...\n";

$requiredFiles = [
    'composer.json',
    'config/global-search.php',
    'routes/api.php',
    'README.md',
    'src/GlobalSearchServiceProvider.php',
    'src/Support/MeilisearchClient.php',
    'src/Support/SearchIndexManager.php',
    'src/Services/GlobalSearchService.php',
    'src/Traits/Searchable.php',
    'src/Contracts/SearchDocumentTransformer.php',
    'src/Contracts/SearchResultLinkResolver.php',
    'src/Http/Controllers/GlobalSearchController.php',
    'src/Jobs/IndexModelsJob.php',
    'src/Jobs/DeleteModelsJob.php',
    'src/Console/SearchReindexCommand.php',
    'src/Console/SearchFlushCommand.php',
    'src/Console/SearchSyncSettingsCommand.php',
    'src/Console/SearchWarmCacheCommand.php',
    'src/Console/SearchDoctorCommand.php',
    'resources/views/components/global-search.blade.php',
];

$allFilesExist = true;
foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "   ✅ {$file}\n";
    } else {
        echo "   ❌ {$file}\n";
        $allFilesExist = false;
    }
}

echo "\n2. Testing PHP syntax...\n";
$phpFiles = glob('src/**/*.php');
$syntaxErrors = 0;

foreach ($phpFiles as $file) {
    $output = [];
    $returnCode = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $returnCode);
    
    if ($returnCode === 0) {
        echo "   ✅ " . basename($file) . "\n";
    } else {
        echo "   ❌ " . basename($file) . " - " . implode(' ', $output) . "\n";
        $syntaxErrors++;
    }
}

echo "\n3. Testing configuration file...\n";
if (file_exists('config/global-search.php')) {
    $config = include 'config/global-search.php';
    if (is_array($config) && isset($config['client'], $config['federation'], $config['mappings'])) {
        echo "   ✅ Configuration file is valid\n";
    } else {
        echo "   ❌ Configuration file is missing required sections\n";
        $allFilesExist = false;
    }
} else {
    echo "   ❌ Configuration file not found\n";
    $allFilesExist = false;
}

echo "\n4. Testing composer.json...\n";
if (file_exists('composer.json')) {
    $composer = json_decode(file_get_contents('composer.json'), true);
    if (json_last_error() === JSON_ERROR_NONE && isset($composer['name'])) {
        echo "   ✅ Composer.json is valid JSON\n";
        echo "   📦 Package name: " . $composer['name'] . "\n";
        echo "   📝 Description: " . ($composer['description'] ?? 'No description') . "\n";
    } else {
        echo "   ❌ Composer.json is invalid JSON\n";
        $allFilesExist = false;
    }
} else {
    echo "   ❌ Composer.json not found\n";
    $allFilesExist = false;
}

echo "\n5. Testing namespace consistency...\n";
$namespaceFiles = [
    'src/GlobalSearchServiceProvider.php',
    'src/Support/MeilisearchClient.php',
    'src/Support/SearchIndexManager.php',
    'src/Services/GlobalSearchService.php',
    'src/Traits/Searchable.php',
];

$namespaceCorrect = true;
foreach ($namespaceFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'namespace LaravelGlobalSearch\\GlobalSearch') !== false) {
            echo "   ✅ " . basename($file) . " has correct namespace\n";
        } else {
            echo "   ❌ " . basename($file) . " has incorrect namespace\n";
            $namespaceCorrect = false;
        }
    }
}

echo "\n" . str_repeat("=", 60) . "\n";

if ($allFilesExist && $syntaxErrors === 0 && $namespaceCorrect) {
    echo "🎉 SUCCESS: Package structure is correct and ready to use!\n\n";
    echo "📋 Package Summary:\n";
    echo "   • All required files are present\n";
    echo "   • PHP syntax is valid\n";
    echo "   • Namespaces are consistent\n";
    echo "   • Configuration is properly structured\n\n";
    echo "🚀 Next steps to use this package:\n";
    echo "   1. Run: composer install\n";
    echo "   2. Add to your Laravel app's composer.json:\n";
    echo "      \"laravel-global-search/global-search\": \"dev-master\"\n";
    echo "   3. Run: php artisan vendor:publish --tag=global-search-config\n";
    echo "   4. Configure your models and run: php artisan search:doctor\n";
    echo "   5. Add the Searchable trait to your models\n";
    echo "   6. Run: php artisan search:sync-settings\n";
    echo "   7. Run: php artisan search:reindex\n";
} else {
    echo "❌ FAILED: Package has issues that need to be fixed.\n";
    if (!$allFilesExist) echo "   • Missing required files\n";
    if ($syntaxErrors > 0) echo "   • PHP syntax errors found\n";
    if (!$namespaceCorrect) echo "   • Namespace inconsistencies found\n";
}

echo "\n";
