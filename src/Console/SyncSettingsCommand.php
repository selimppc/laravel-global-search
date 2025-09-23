<?php

namespace LaravelGlobalSearch\GlobalSearch\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use LaravelGlobalSearch\GlobalSearch\Support\TenantResolver;
use Meilisearch\Client;

class SyncSettingsCommand extends Command
{
    protected $signature = 'search:sync-settings {tenant?} {--all}';
    protected $description = 'Sync Meilisearch index settings';

    public function handle(TenantResolver $tenantResolver): int
    {
        $tenant = $this->argument('tenant');
        $all = $this->option('all');
        
        try {
            $client = App::make(Client::class);
            $config = App::make('config')->get('global-search');
            
            if ($all) {
                // Sync settings for all tenants
                $tenants = $tenantResolver->getAllTenants();
                if (empty($tenants)) {
                    $this->info('No tenants found. Syncing default index...');
                    $this->syncIndexSettings($client, $config, null, $tenantResolver);
                } else {
                    foreach ($tenants as $tenantId) {
                        $this->info("Syncing settings for tenant: {$tenantId}");
                        $this->syncIndexSettings($client, $config, $tenantId, $tenantResolver);
                    }
                }
            } else {
                $this->syncIndexSettings($client, $config, $tenant, $tenantResolver);
            }
            
            $this->info('Settings synced successfully!');
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Failed to sync settings: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
    
    private function syncIndexSettings(Client $client, array $config, ?string $tenant, TenantResolver $tenantResolver): void
    {
        $indexes = array_keys($config['federation']['indexes'] ?? []);
        
        foreach ($indexes as $indexName) {
            $tenantIndexName = $tenantResolver->getTenantIndexName($indexName);
            
            // Get settings for this specific index from mappings
            $settings = $this->getIndexSettings($config, $indexName);
            if (!empty($settings)) {
                $client->index($tenantIndexName)->updateSettings($settings);
                $this->line("✓ Synced settings for index: {$tenantIndexName}");
            } else {
                $this->line("⚠ No settings found for index: {$indexName}");
            }
        }
    }
    
    private function getIndexSettings(array $config, string $indexName): array
    {
        $mappings = $config['mappings'] ?? [];
        foreach ($mappings as $mapping) {
            if (strtolower(class_basename($mapping['model'])) === $indexName) {
                return $mapping['index_settings'] ?? [];
            }
        }
        return [];
    }
}
