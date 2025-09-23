<?php

namespace LaravelGlobalSearch\GlobalSearch\Contracts;

/**
 * Contract for data transformation strategies.
 * Allows custom transformation logic for different model types.
 */
interface DataTransformer
{
    /**
     * Transform a model instance into searchable document data.
     */
    public function transform($model, ?string $tenant = null): array;

    /**
     * Get the model class this transformer handles.
     */
    public function getModelClass(): string;

    /**
     * Get the searchable fields for this model.
     */
    public function getSearchableFields(): array;

    /**
     * Get the filterable fields for this model.
     */
    public function getFilterableFields(): array;

    /**
     * Get the sortable fields for this model.
     */
    public function getSortableFields(): array;
}
