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
        // Get all indexes from mappings
        $mappings = $config['mappings'] ?? [];
        $indexSettings = $config['index_settings'] ?? [];
        
        foreach ($mappings as $mapping) {
            $indexName = $mapping['index'];
            $tenantIndexName = $tenantResolver->getTenantIndexName($indexName, $tenant);
            
            // Get settings from index_settings config
            $settings = $this->getIndexSettings($indexSettings, $mapping, $indexName);
            
            if (!empty($settings)) {
                try {
                    $client->index($tenantIndexName)->updateSettings($settings);
                    $this->line("✓ Synced settings for index: {$tenantIndexName}");
                } catch (\Exception $e) {
                    $this->line("⚠ Failed to sync settings for index: {$tenantIndexName} - {$e->getMessage()}");
                }
            } else {
                $this->line("⚠ No settings found for index: {$indexName}");
            }
        }
    }
    
    private function getIndexSettings(array $indexSettings, array $mapping, string $indexName): array
    {
        // Priority 1: Check index_settings array for this index
        if (isset($indexSettings[$indexName])) {
            return $indexSettings[$indexName];
        }
        
        // Priority 2: Build settings from mapping configuration
        $settings = [];
        
        if (!empty($mapping['filterable'])) {
            $settings['filterableAttributes'] = $mapping['filterable'];
        }
        
        if (!empty($mapping['sortable'])) {
            $settings['sortableAttributes'] = $mapping['sortable'];
        }
        
        if (!empty($mapping['fields'])) {
            $settings['searchableAttributes'] = $mapping['fields'];
        }
        
        return $settings;
    }
}
