<?php

namespace Selimppc\GlobalSearch\Console;

use Illuminate\Console\Command;

class SearchDoctor extends Command
{
    protected $signature = 'search:doctor';
    protected $description = 'Validate config & environment for laravel-global-search';

    public function handle(): int
    {
        $cfg = config('global-search');
        $ok = true;

        if (!($cfg['client']['host'] ?? null)) { $this->error('Missing client.host'); $ok = false; }
        if (!($cfg['mappings'] ?? [])) { $this->warn('No mappings configured.'); }
        if (!($cfg['federation']['indexes'] ?? [])) { $this->warn('No federation.indexes configured.'); }

        $this->info('Cache store: '.($cfg['cache']['store'] ?? 'default'));
        $this->info('Queue: '.($cfg['pipeline']['queue'] ?? 'default'));

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}