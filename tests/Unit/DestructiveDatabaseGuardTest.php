<?php

namespace Tests\Unit;

use App\Support\DestructiveDatabaseGuard;
use Illuminate\Console\Events\CommandStarting;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class DestructiveDatabaseGuardTest extends TestCase
{
    public function test_blocks_wipe_commands_and_live_database_names(): void
    {
        $this->assertTrue(DestructiveDatabaseGuard::isBlockedCommand('migrate:fresh'));
        $this->assertTrue(DestructiveDatabaseGuard::isBlockedCommand('migrate:refresh'));
        $this->assertTrue(DestructiveDatabaseGuard::isBlockedCommand('migrate:reset'));
        $this->assertTrue(DestructiveDatabaseGuard::isBlockedCommand('db:wipe'));
        $this->assertTrue(DestructiveDatabaseGuard::isBlockedCommand(
            'Illuminate\\Database\\Console\\Migrations\\FreshCommand'
        ));
        $this->assertFalse(DestructiveDatabaseGuard::isBlockedCommand('migrate'));
        $this->assertFalse(DestructiveDatabaseGuard::isBlockedCommand('migrate --force'));

        $this->assertTrue(DestructiveDatabaseGuard::isProtectedDatabase('pneadm'));
        $this->assertTrue(DestructiveDatabaseGuard::isProtectedDatabase('srv66127_pneduadm'));
        $this->assertTrue(DestructiveDatabaseGuard::isProtectedDatabase('pnedu'));
        $this->assertFalse(DestructiveDatabaseGuard::isProtectedDatabase('testing'));
        $this->assertFalse(DestructiveDatabaseGuard::isProtectedDatabase(':memory:'));
    }

    public function test_handle_blocks_fresh_command_class_against_pneadm(): void
    {
        config(['database.connections.mysql.database' => 'pneadm']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ZABLOKOWANE');

        DestructiveDatabaseGuard::handle(new CommandStarting(
            'Illuminate\\Database\\Console\\Migrations\\FreshCommand',
            new ArrayInput([]),
            new BufferedOutput()
        ));
    }

    public function test_target_database_uses_database_option(): void
    {
        config(['database.connections.analytics.database' => 'pne_analytics']);

        $input = new ArrayInput(['--database' => 'analytics']);
        $this->assertSame('pne_analytics', DestructiveDatabaseGuard::targetDatabaseName($input));
    }
}
