<?php

namespace LaravelGlobalSearch\GlobalSearch\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use LaravelGlobalSearch\GlobalSearch\Support\TenantResolver;
use Meilisearch\Client;

class StatusCommand extends Command
{
    protected $signature = 'search:status {tenant?} {--detailed}';
    protected $description = 'Show search index status and statistics';

    public function handle(TenantResolver $tenantResolver): int
    {
        $tenant = $this->argument('tenant');
        $detailed = $this->option('detailed');
        
        $this->info('🔍 Global Search Status Report');
        $this->line('================================');
        $this->newLine();
        
        try {
            $client = App::make(Client::class);
            $config = App::make('config')->get('global-search');
            $indexes = array_keys($config['federation']['indexes'] ?? []);
            
            if (empty($indexes)) {
                $this->warn('No indexes configured in federation.indexes');
                return Command::SUCCESS;
            }
            
            $totalDocuments = 0;
            $totalSize = 0;
            
            foreach ($indexes as $indexName) {
                $tenantIndexName = $tenantResolver->getTenantIndexName($indexName);
                
                try {
                    $stats = $client->index($tenantIndexName)->getStats();
                    $documents = $stats['numberOfDocuments'] ?? 0;
                    $size = $stats['databaseSize'] ?? 0;
                    
                    $totalDocuments += $documents;
                    $totalSize += $size;
                    
                    $this->info("📊 Index: {$indexName}");
                    $this->line("   Tenant Index: {$tenantIndexName}");
                    $this->line("   Documents: " . number_format($documents));
                    $this->line("   Size: " . $this->formatBytes($size));
                    
                    if ($detailed) {
                        $this->line("   Last Update: " . ($stats['lastUpdate'] ?? 'Unknown'));
                        $this->line("   Is Indexing: " . ($stats['isIndexing'] ? 'Yes' : 'No'));
                    }
                    
                    $this->newLine();
                    
                } catch (\Exception $e) {
                    $this->error("❌ Failed to get stats for {$indexName}: {$e->getMessage()}");
                }
            }
            
            // Summary
            $this->info('📈 Summary');
            $this->line('Total Documents: ' . number_format($totalDocuments));
            $this->line('Total Size: ' . $this->formatBytes($totalSize));
            $this->line('Indexes: ' . count($indexes));
            
            if ($totalDocuments === 0) {
                $this->newLine();
                $this->warn('⚠️  No documents found in any index.');
                $this->line('Run: php artisan search:reindex');
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Failed to get status: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
    
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
