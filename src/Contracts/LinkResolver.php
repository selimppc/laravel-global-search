<?php

namespace Selimppc\GlobalSearch\Contracts;

interface LinkResolver
{
/**
* Return an array of link objects: [['label' => '…', 'href' => '…'], ...]
*/
public function resolve(array $hit): array;

}
