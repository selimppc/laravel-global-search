<?php

namespace LaravelGlobalSearch\GlobalSearch\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use LaravelGlobalSearch\GlobalSearch\GlobalSearchServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            GlobalSearchServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('global-search', [
            'client' => [
                'host' => 'http://localhost:7700',
                'key' => 'test-key',
                'timeout' => 5,
            ],
            'index_name' => 'test_index',
            'models' => [],
            'fields' => ['id', 'name', 'email'],
            'tenant' => [
                'enabled' => false,
            ],
        ]);
    }
}
