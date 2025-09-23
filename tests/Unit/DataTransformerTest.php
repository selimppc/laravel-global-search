<?php

namespace LaravelGlobalSearch\GlobalSearch\Tests\Unit;

use LaravelGlobalSearch\GlobalSearch\Tests\TestCase;
use LaravelGlobalSearch\GlobalSearch\Support\DefaultDataTransformer;
use LaravelGlobalSearch\GlobalSearch\Support\DataTransformerManager;
use Illuminate\Database\Eloquent\Model;

class DataTransformerTest extends TestCase
{
    private function createMockModel(array $attributes = [])
    {
        return new class($attributes) extends Model {
            protected $attributes;
            
            public function __construct($attributes = [])
            {
                $this->attributes = $attributes;
            }
            
            public function toArray()
            {
                return $this->attributes;
            }
            
            public function getKey()
            {
                return $this->attributes['id'] ?? null;
            }
            
            public function offsetExists($offset): bool
            {
                return isset($this->attributes[$offset]);
            }
            
            public function getAttribute($key)
            {
                return $this->attributes[$key] ?? null;
            }
            
            public function getFillable()
            {
                return array_keys($this->attributes);
            }
        };
    }
    public function test_default_data_transformer_transforms_model()
    {
        // Create a simple mock model
        $model = $this->createMockModel([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '123-456-7890',
        ]);

        $config = [
            'fields' => ['id', 'name', 'email', 'phone'],
            'transformations' => [
                'email' => 'email',
                'phone' => 'phone',
            ],
        ];

        $transformer = new DefaultDataTransformer(get_class($model), $config);
        $result = $transformer->transform($model, 'tenant1');

        $this->assertEquals(1, $result['id']);
        $this->assertEquals('John Doe', $result['name']);
        $this->assertEquals('john@example.com', $result['email']);
        $this->assertEquals('1234567890', $result['phone']); // Phone formatted
        $this->assertEquals('tenant1', $result['tenant_id']);
        $this->assertArrayHasKey('_search_metadata', $result);
    }

    public function test_data_transformer_manager_creates_default_transformer()
    {
        $config = [
            'mappings' => [
                [
                    'model' => 'TestModel',
                    'fields' => ['id', 'name'],
                ],
            ],
        ];

        $manager = new DataTransformerManager($config);
        $transformer = $manager->getTransformer('TestModel');

        $this->assertInstanceOf(DefaultDataTransformer::class, $transformer);
        $this->assertEquals('TestModel', $transformer->getModelClass());
    }

    public function test_data_transformer_manager_registers_custom_transformer()
    {
        $config = [];
        $manager = new DataTransformerManager($config);

        $customTransformer = new class implements \LaravelGlobalSearch\GlobalSearch\Contracts\DataTransformer {
            public function transform($model, ?string $tenant = null): array
            {
                return ['custom' => 'data'];
            }

            public function getModelClass(): string
            {
                return 'CustomModel';
            }

            public function getSearchableFields(): array
            {
                return ['custom'];
            }

            public function getFilterableFields(): array
            {
                return ['custom'];
            }

            public function getSortableFields(): array
            {
                return ['custom'];
            }
        };

        $manager->registerTransformer('CustomModel', $customTransformer);
        $transformer = $manager->getTransformer('CustomModel');

        $this->assertSame($customTransformer, $transformer);
    }

    public function test_field_transformations()
    {
        $model = $this->createMockModel([
            'id' => 1,
            'email' => 'test@example.com',
            'phone' => '123-456-7890',
            'created_at' => '2023-01-01 12:00:00',
            'price' => 99.99,
        ]);

        $config = [
            'transformations' => [
                'email' => 'email',
                'phone' => 'phone',
                'created_at' => 'date',
                'price' => 'currency',
            ],
        ];

        $transformer = new DefaultDataTransformer(get_class($model), $config);
        $result = $transformer->transform($model);

        $this->assertEquals('test@example.com', $result['email']);
        $this->assertEquals('1234567890', $result['phone']);
        $this->assertStringContainsString('2023-01-01', $result['created_at']);
        $this->assertEquals('99.99', $result['price']);
    }

    public function test_computed_fields()
    {
        $model = $this->createMockModel([
            'id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $config = [
            'computed' => [
                'full_name' => function($model) {
                    return $model->first_name . ' ' . $model->last_name;
                },
                'initials' => function($model) {
                    return strtoupper(substr($model->first_name, 0, 1) . substr($model->last_name, 0, 1));
                },
            ],
        ];

        $transformer = new DefaultDataTransformer(get_class($model), $config);
        $result = $transformer->transform($model);

        $this->assertEquals('John Doe', $result['full_name']);
        $this->assertEquals('JD', $result['initials']);
    }

    public function test_handles_model_with_toSearchableArray()
    {
        $model = new class extends Model {
            public $id = 1;
            public $name = 'Test Model';
            
            public function getKey()
            {
                return $this->id;
            }
            
            public function offsetExists($offset): bool
            {
                return isset($this->$offset);
            }
            
            public function getAttribute($key)
            {
                return $this->$key ?? null;
            }

            public function toArray()
            {
                return [
                    'id' => $this->id,
                    'name' => $this->name,
                ];
            }

            public function toSearchableArray(): array
            {
                return [
                    'id' => $this->id,
                    'name' => $this->name,
                    'custom_field' => 'custom_value',
                ];
            }
        };

        $config = [];
        $transformer = new DefaultDataTransformer(get_class($model), $config);
        $result = $transformer->transform($model);

        $this->assertEquals(1, $result['id']);
        $this->assertEquals('Test Model', $result['name']);
        $this->assertEquals('custom_value', $result['custom_field']);
    }

    public function test_cleans_data_properly()
    {
        $model = $this->createMockModel([
            'id' => 1,
            'name' => 'Test',
            'null_field' => null,
            'empty_field' => '',
            'valid_field' => 'valid',
        ]);

        $config = [];
        $transformer = new DefaultDataTransformer(get_class($model), $config);
        $result = $transformer->transform($model);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('valid_field', $result);
        $this->assertArrayNotHasKey('null_field', $result);
        $this->assertArrayNotHasKey('empty_field', $result);
    }
}
