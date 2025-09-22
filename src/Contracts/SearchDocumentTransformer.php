<?php

namespace LaravelGlobalSearch\GlobalSearch\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Contract for transforming Eloquent models into search documents.
 */
interface SearchDocumentTransformer
{
    /**
     * Transform an Eloquent model into a search document.
     *
     * @param Model $model The Eloquent model instance
     * @param array $mapping The mapping configuration for this model
     * @return array The search document data
     */
    public function __invoke(Model $model, array $mapping): array;
}