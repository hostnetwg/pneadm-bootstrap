<?php

namespace Tests\Feature\GrowthOS;

use App\Models\Instructor;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GrowthOsV01SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_v0_1_tables_exist_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('growth_campaigns'));
        $this->assertTrue(Schema::hasTable('growth_artifacts'));
        $this->assertTrue(Schema::hasTable('growth_tasks'));
        $this->assertTrue(Schema::hasTable('growth_decisions'));
        $this->assertFalse(Schema::hasTable('growth_topics'));
        $this->assertFalse(Schema::hasTable('growth_experts'));

        $this->assertTrue(Schema::hasColumns('growth_campaigns', [
            'name',
            'type',
            'status',
            'goal',
            'host_name',
            'owner_user_id',
            'primary_instructor_id',
            'working_topic',
            'live_at',
        ]));
        $this->assertTrue(Schema::hasColumns('growth_artifacts', [
            'growth_campaign_id',
            'key',
            'type',
            'schema_version',
            'version',
            'payload',
        ]));
        $this->assertTrue(Schema::hasColumns('growth_tasks', [
            'growth_campaign_id',
            'growth_artifact_id',
            'status',
            'due_at',
        ]));
        $this->assertTrue(Schema::hasColumns('growth_decisions', [
            'growth_campaign_id',
            'growth_artifact_id',
            'growth_task_id',
            'status',
            'meta',
        ]));
    }

    public function test_artifact_key_is_unique_within_campaign_and_children_reference_campaign(): void
    {
        $owner = User::factory()->create();
        $instructor = Instructor::query()->create([
            'first_name' => 'Roman',
            'last_name' => 'Lorens',
            'email' => 'roman.lorens@example.test',
            'is_active' => true,
        ]);

        $campaignId = DB::table('growth_campaigns')->insertGetId([
            'name' => 'Webinar TIK',
            'type' => 'webinar',
            'status' => 'planning',
            'goal' => 'education',
            'owner_user_id' => $owner->id,
            'primary_instructor_id' => $instructor->id,
            'working_topic' => 'Canva AI w pracy nauczyciela',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('growth_artifacts')->insert([
            'growth_campaign_id' => $campaignId,
            'key' => 'concept',
            'type' => 'concept',
            'status' => 'draft',
            'schema_version' => 1,
            'version' => 1,
            'payload' => json_encode(['title' => 'Canva AI'], JSON_THROW_ON_ERROR),
            'created_by_user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('growth_artifacts')->insert([
            'growth_campaign_id' => $campaignId,
            'key' => 'concept',
            'type' => 'concept',
            'status' => 'draft',
            'schema_version' => 1,
            'version' => 2,
            'payload' => json_encode(['title' => 'Duplikat'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
