<?php

namespace Selimppc\GlobalSearch\Contracts;

interface ToSearchDocument
{
/**
* Build a Meilisearch document from the Eloquent model and mapping.
* @param object $model Eloquent model instance
* @param array $mapping Mapping config entry
* @return array
*/
public function __invoke(object $model, array $mapping): array;

}