<?php

namespace Selimppc\GlobalSearch\Support;

use GuzzleHttp\Client;

class MeiliClient
{
    private Client $http;
    public function __construct(private string $host, private ?string $key = null, private int $timeout = 5)
    {
        $headers = ['Accept' => 'application/json'];
        if ($key) $headers['Authorization'] = 'Bearer '.$key;
        $this->http = new Client([
            'base_uri' => rtrim($host, '/').'/',
            'timeout' => $timeout,
            'headers' => $headers,
        ]);
    }

    public function search(string $index, string $q, array $options = []): array
    {
        $resp = $this->http->post("indexes/{$index}/search", ['json' => array_merge(['q' => $q], $options)]);
        return json_decode((string) $resp->getBody(), true);
    }

    public function addDocuments(string $index, array $docs, ?string $primaryKey = null): array
    {
        $resp = $this->http->post("indexes/{$index}/documents", ['json' => $docs, 'query' => array_filter(['primaryKey' => $primaryKey])]);
        return json_decode((string) $resp->getBody(), true);
    }

    public function deleteDocuments(string $index, array $ids): array
    {
        $resp = $this->http->post("indexes/{$index}/documents/delete-batch", ['json' => $ids]);
        return json_decode((string) $resp->getBody(), true);
    }

    public function deleteAllDocuments(string $index): array
    {
        $resp = $this->http->delete("indexes/{$index}/documents");
        return json_decode((string) $resp->getBody(), true);
    }

    public function updateSettings(string $index, array $settings): array
    {
        $resp = $this->http->patch("indexes/{$index}/settings", ['json' => $settings]);
        return json_decode((string) $resp->getBody(), true);
    }
}
