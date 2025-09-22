<?php

namespace LaravelGlobalSearch\GlobalSearch\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SetupLaravel12Command extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'global-search:setup-laravel12 
                            {--force : Force update bootstrap/app.php even if API routes are already configured}';

    /**
     * The console command description.
     */
    protected $description = 'Setup Laravel 12 compatibility for global search package';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔧 Setting up Laravel 12 compatibility for Global Search...');
        $this->newLine();

        // Check Laravel version
        if (version_compare(app()->version(), '12.0.0', '<')) {
            $this->warn('⚠️  This command is designed for Laravel 12+. Your version: ' . app()->version());
            $this->warn('   The package should work normally with your Laravel version.');
            return self::SUCCESS;
        }

        $this->info('✅ Laravel 12+ detected: ' . app()->version());

        // Step 1: Create routes/api.php if it doesn't exist
        $this->createApiRoutesFile();

        // Step 2: Update bootstrap/app.php
        $this->updateBootstrapConfiguration();

        // Step 3: Verify setup
        $this->verifySetup();

        $this->newLine();
        $this->info('🎉 Laravel 12 setup complete! Your global search routes should now work.');
        $this->info('   Test with: php artisan route:list | grep global-search');

        return self::SUCCESS;
    }

    /**
     * Create the API routes file.
     */
    protected function createApiRoutesFile(): void
    {
        $apiRoutesPath = base_path('routes/api.php');
        
        if (file_exists($apiRoutesPath)) {
            $this->info('✅ routes/api.php already exists');
            return;
        }

        $content = "<?php\n\nuse Illuminate\Support\Facades\Route;\n\n/*\n|--------------------------------------------------------------------------\n| API Routes\n|--------------------------------------------------------------------------\n|\n| Here is where you can register API routes for your application.\n| The global search package routes are automatically loaded here.\n|\n*/\n\n// Global search routes are loaded by the service provider\n";
        
        file_put_contents($apiRoutesPath, $content);
        $this->info('✅ Created routes/api.php');
    }

    /**
     * Update bootstrap/app.php configuration.
     */
    protected function updateBootstrapConfiguration(): void
    {
        $bootstrapPath = base_path('bootstrap/app.php');
        
        if (!file_exists($bootstrapPath)) {
            $this->error('❌ bootstrap/app.php not found!');
            return;
        }

        $content = file_get_contents($bootstrapPath);
        
        // Check if API routes are already configured
        if (str_contains($content, "api: __DIR__.'/../routes/api.php'")) {
            if (!$this->option('force')) {
                $this->info('✅ API routes already configured in bootstrap/app.php');
                return;
            }
            $this->warn('⚠️  API routes already configured, but --force flag used');
        }

        // Find the withRouting section
        if (!preg_match('/->withRouting\s*\(\s*([^)]+)\s*\)/', $content, $matches)) {
            $this->error('❌ Could not find withRouting() section in bootstrap/app.php');
            $this->warn('   Please manually add: api: __DIR__.\'/../routes/api.php\' to withRouting()');
            return;
        }

        $routingSection = $matches[1];
        
        // Check if api is already there
        if (str_contains($routingSection, 'api:')) {
            $this->info('✅ API routes already configured in bootstrap/app.php');
            return;
        }

        // Add api line to withRouting
        $newRoutingSection = $routingSection;
        
        // Add api line after web line
        if (preg_match('/(web:\s*__DIR__\.\'\/\.\.\/routes\/web\.php\',)/', $newRoutingSection, $webMatches)) {
            $newRoutingSection = str_replace(
                $webMatches[1],
                $webMatches[1] . "\n        api: __DIR__.'/../routes/api.php',",
                $newRoutingSection
            );
        } else {
            // Fallback: add at the beginning
            $newRoutingSection = "api: __DIR__.'/../routes/api.php',\n        " . $newRoutingSection;
        }

        $newContent = str_replace($routingSection, $newRoutingSection, $content);
        
        if (file_put_contents($bootstrapPath, $newContent)) {
            $this->info('✅ Updated bootstrap/app.php with API routes configuration');
        } else {
            $this->error('❌ Failed to update bootstrap/app.php');
            $this->warn('   Please manually add: api: __DIR__.\'/../routes/api.php\' to withRouting()');
        }
    }

    /**
     * Verify the setup is working.
     */
    protected function verifySetup(): void
    {
        $this->newLine();
        $this->info('🔍 Verifying setup...');

        // Check if routes/api.php exists
        if (!file_exists(base_path('routes/api.php'))) {
            $this->error('❌ routes/api.php not found');
            return;
        }

        // Check if bootstrap/app.php has API routes
        $bootstrapPath = base_path('bootstrap/app.php');
        if (file_exists($bootstrapPath)) {
            $content = file_get_contents($bootstrapPath);
            if (str_contains($content, "api: __DIR__.'/../routes/api.php'")) {
                $this->info('✅ bootstrap/app.php configured correctly');
            } else {
                $this->warn('⚠️  bootstrap/app.php may need manual configuration');
            }
        }

        // Try to get route list
        try {
            $routes = \Illuminate\Support\Facades\Route::getRoutes();
            $globalSearchRoutes = collect($routes)->filter(function ($route) {
                return Str::contains($route->uri(), 'global-search');
            });

            if ($globalSearchRoutes->count() > 0) {
                $this->info('✅ Global search routes are registered');
                $this->table(
                    ['Method', 'URI', 'Name'],
                    $globalSearchRoutes->map(function ($route) {
                        return [
                            implode('|', $route->methods()),
                            $route->uri(),
                            $route->getName() ?? 'N/A'
                        ];
                    })->toArray()
                );
            } else {
                $this->warn('⚠️  Global search routes not found. Try running: php artisan config:clear');
            }
        } catch (\Exception $e) {
            $this->warn('⚠️  Could not verify routes: ' . $e->getMessage());
        }
    }
}
