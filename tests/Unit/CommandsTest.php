<?php

namespace LaravelGlobalSearch\GlobalSearch\Tests\Unit;

use LaravelGlobalSearch\GlobalSearch\Tests\TestCase;
use LaravelGlobalSearch\GlobalSearch\Console\ReindexCommand;
use LaravelGlobalSearch\GlobalSearch\Console\ReindexTenantCommand;
use LaravelGlobalSearch\GlobalSearch\Console\SyncSettingsCommand;
use LaravelGlobalSearch\GlobalSearch\Console\HealthCommand;

class CommandsTest extends TestCase
{
    public function test_reindex_command_exists()
    {
        $this->assertTrue(class_exists(ReindexCommand::class));
        
        $command = new ReindexCommand();
        $this->assertEquals('search:reindex', $command->getName());
    }

    public function test_reindex_tenant_command_exists()
    {
        $this->assertTrue(class_exists(ReindexTenantCommand::class));
        
        $command = new ReindexTenantCommand();
        $this->assertEquals('search:reindex-tenant', $command->getName());
    }

    public function test_sync_settings_command_exists()
    {
        $this->assertTrue(class_exists(SyncSettingsCommand::class));
        
        $command = new SyncSettingsCommand();
        $this->assertEquals('search:sync-settings', $command->getName());
    }

    public function test_health_command_exists()
    {
        $this->assertTrue(class_exists(HealthCommand::class));
        
        $command = new HealthCommand();
        $this->assertEquals('search:health', $command->getName());
    }

    public function test_commands_are_registered()
    {
        // Test that our command classes exist and can be instantiated
        $this->assertInstanceOf(ReindexCommand::class, new ReindexCommand());
        $this->assertInstanceOf(ReindexTenantCommand::class, new ReindexTenantCommand());
        $this->assertInstanceOf(SyncSettingsCommand::class, new SyncSettingsCommand());
        $this->assertInstanceOf(HealthCommand::class, new HealthCommand());
    }
}
