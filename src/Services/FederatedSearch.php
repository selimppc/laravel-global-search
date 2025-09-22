<?php

namespace Selimppc\GlobalSearch\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Selimppc\GlobalSearch\Support\IndexManager;

class FederatedSearch
{
    public function __construct(private IndexManager $indexer, private CacheRepository $cache, private array $config)
    {
    }

    public function search(string $q, array $filtersByIndex = [], int $limit = 10): array
    {
        $federation = $this->config['federation'] ?? [];
        $indexes = array_keys($federation['indexes'] ?? []);
        if (empty($indexes)) return ['hits' => [], 'meta' => ['total' => 0]];

        // Build cache key using per-index versions
        $versions = [];
        $store = $this->config['cache']['store'] ?? null;
        $prefix = $this->config['cache']['version_key_prefix'] ?? 'ms:index:';
        foreach ($indexes as $i) {
            $versions[$i] = (int) cache()->store($store)->get($prefix.$i, 0);
        }
        $cacheKey = 'gs:'.implode('|', $versions).':'.sha1($q.json_encode($filtersByIndex).implode(',', $indexes)).":$limit";

        if (($this->config['cache']['enabled'] ?? false) && $this->cache->has($cacheKey)) {
            $this->cache->get($cacheKey);
        }

        // Fan-out to Meilisearch per index
        $client = app(\Selimppc\GlobalSearch\Support\MeiliClient::class);
        $allHits = [];
        $total = 0;

        foreach ($indexes as $index) {
            $options = ['limit' => $limit];
            if (!empty($filtersByIndex[$index])) {
                $options['filter'] = $filtersByIndex[$index]; // Meili expects string or array
            }
            $res = $client->search($index, $q, $options);
            $weight = (float) (($federation['indexes'][$index]['weight'] ?? 1));

            foreach (($res['hits'] ?? []) as $hit) {
                $hit['_index'] = $index;
                $hit['_score'] = ($hit['_matchesPosition'] ?? []) ? 1.0 : 0.5; // basic heuristic
                $hit['_score'] *= max(0.1, $weight);
                $allHits[] = $hit;
            }
            $total += (int) ($res['estimatedTotalHits'] ?? 0);
        }

        // Sort by score desc, then updated_at desc if present
        usort($allHits, function ($a, $b) {
            $s = ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0);
            return $s !== 0 ? $s : (strtotime($b['updated_at'] ?? '0') <=> strtotime($a['updated_at'] ?? '0'));
        });

        $out = [
            'hits' => array_slice($allHits, 0, $limit),
            'meta' => [
                'total' => $total,
                'indexes' => $indexes,
                'q' => $q,
                'limit' => $limit,
            ],
        ];

        if ($this->config['cache']['enabled'] ?? false) {
            $ttl = (int) ($this->config['cache']['ttl'] ?? 60);
            $this->cache->put($cacheKey, $out, $ttl);
        }

        return $out;
    }
}
