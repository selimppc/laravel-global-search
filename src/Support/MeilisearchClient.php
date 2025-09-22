<?php

namespace LaravelGlobalSearch\GlobalSearch\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Meilisearch HTTP client for interacting with Meilisearch API.
 */
class MeilisearchClient
{
    private Client $httpClient;

    /**
     * Create a new Meilisearch client instance.
     */
    public function __construct(
        private string $host,
        private ?string $key = null,
        private int $timeout = 5
    ) {
        $this->initializeHttpClient();
    }

    /**
     * Initialize the HTTP client with proper configuration.
     */
    private function initializeHttpClient(): void
    {
        $headers = ['Accept' => 'application/json'];
        
        if ($this->key) {
            $headers['Authorization'] = 'Bearer ' . $this->key;
        }

        $this->httpClient = new Client([
            'base_uri' => rtrim($this->host, '/') . '/',
            'timeout' => $this->timeout,
            'headers' => $headers,
        ]);
    }

    /**
     * Search documents in the specified index.
     */
    public function search(string $index, string $query, array $options = []): array
    {
        try {
            $response = $this->httpClient->post("indexes/{$index}/search", [
                'json' => array_merge(['q' => $query], $options)
            ]);
            
            return json_decode((string) $response->getBody(), true) ?? [];
        } catch (GuzzleException $e) {
            Log::error('Meilisearch search failed', [
                'index' => $index,
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException("Search failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Add documents to the specified index.
     */
    public function addDocuments(string $index, array $documents, ?string $primaryKey = null): array
    {
        try {
            $response = $this->httpClient->post("indexes/{$index}/documents", [
                'json' => $documents,
                'query' => array_filter(['primaryKey' => $primaryKey])
            ]);
            
            return json_decode((string) $response->getBody(), true) ?? [];
        } catch (GuzzleException $e) {
            Log::error('Meilisearch add documents failed', [
                'index' => $index,
                'document_count' => count($documents),
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException("Add documents failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Delete documents from the specified index by IDs.
     */
    public function deleteDocuments(string $index, array $documentIds): array
    {
        try {
            $response = $this->httpClient->post("indexes/{$index}/documents/delete-batch", [
                'json' => $documentIds
            ]);
            
            return json_decode((string) $response->getBody(), true) ?? [];
        } catch (GuzzleException $e) {
            Log::error('Meilisearch delete documents failed', [
                'index' => $index,
                'document_ids' => $documentIds,
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException("Delete documents failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Delete all documents from the specified index.
     */
    public function deleteAllDocuments(string $index): array
    {
        try {
            $response = $this->httpClient->delete("indexes/{$index}/documents");
            
            return json_decode((string) $response->getBody(), true) ?? [];
        } catch (GuzzleException $e) {
            Log::error('Meilisearch delete all documents failed', [
                'index' => $index,
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException("Delete all documents failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Update settings for the specified index.
     */
    public function updateSettings(string $index, array $settings): array
    {
        try {
            $response = $this->httpClient->patch("indexes/{$index}/settings", [
                'json' => $settings
            ]);
            
            return json_decode((string) $response->getBody(), true) ?? [];
        } catch (GuzzleException $e) {
            Log::error('Meilisearch update settings failed', [
                'index' => $index,
                'settings' => $settings,
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException("Update settings failed: {$e->getMessage()}", 0, $e);
        }
    }
}
