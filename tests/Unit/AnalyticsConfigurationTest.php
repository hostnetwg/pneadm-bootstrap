<?php

namespace Tests\Unit;

use Tests\TestCase;

class AnalyticsConfigurationTest extends TestCase
{
    public function test_analytics_database_connection_is_configured(): void
    {
        $connection = config('database.connections.analytics');

        $this->assertIsArray($connection);
        $this->assertSame('mysql', $connection['driver']);
        $this->assertSame('pne_analytics', $connection['database']);
    }

    public function test_analytics_queue_defaults_to_analytics_queue_name(): void
    {
        $this->assertSame('redis', config('analytics.queue.connection'));
        $this->assertSame('analytics', config('analytics.queue.name'));
    }
}
