<?php

namespace LaravelGlobalSearch\GlobalSearch\Tests\Unit;

use LaravelGlobalSearch\GlobalSearch\Tests\TestCase;
use LaravelGlobalSearch\GlobalSearch\Traits\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;

class SearchableTraitTest extends TestCase
{
    public function test_searchable_trait_can_be_used()
    {
        $model = new class extends Model {
            use Searchable;
        };
        
        $this->assertTrue(in_array(Searchable::class, class_uses_recursive($model)));
    }

    public function test_searchable_trait_has_reindex_all_method()
    {
        $model = new class extends Model {
            use Searchable;
        };
        
        $this->assertTrue(method_exists($model, 'reindexAll'));
    }

    public function test_reindex_all_is_static_method()
    {
        $reflection = new \ReflectionClass(Searchable::class);
        $method = $reflection->getMethod('reindexAll');
        
        $this->assertTrue($method->isStatic());
    }
}
