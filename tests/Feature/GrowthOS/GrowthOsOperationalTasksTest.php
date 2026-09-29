<?php

namespace Tests\Feature\GrowthOS;

use App\Models\GrowthOS\GrowthCampaign;
use App\Models\GrowthOS\GrowthDecision;
use App\Models\GrowthOS\GrowthTask;
use App\Models\Role;
use App\Models\User;
use App\Services\GrowthOS\GrowthOperationalTasks;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GrowthOsOperationalTasksTest extends TestCase
{
    use RefreshDatabase;

    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->outputBufferLevel = ob_get_level();
        config()->set('growth_os.enabled', true);
        config()->set('growth_ai.enabled', false);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }

        parent::tearDown();
    }

    public function test_new_campaign_gets_nine_operational_tasks_once(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload());

        $tasks = GrowthTask::query()->orderBy('id')->get();
        $this->assertCount(9, $tasks);
        $this->assertSame(GrowthOperationalTasks::keys(), $tasks->pluck('key')->all());
        $this->assertTrue($tasks->every(fn (GrowthTask $task): bool => $task->assignee_user_id === null && $task->growth_artifact_id === null));

        app(GrowthOperationalTasks::class)->ensureForCampaign(GrowthCampaign::query()->first());

        $this->assertSame(9, GrowthTask::query()->count());
    }

    public function test_due_dates_follow_live_at_and_stay_empty_without_it(): void
    {
        $user = User::factory()->create();
        $liveAt = Carbon::parse('2026-10-06 20:00:00');
        $campaign = $this->campaign($user, $liveAt);

        app(GrowthOperationalTasks::class)->ensureForCampaign($campaign);

        $tasks = GrowthTask::query()->where('growth_campaign_id', $campaign->id)->get()->keyBy('key');
        $this->assertSame('2026-10-01 20:00', $tasks[GrowthOperationalTasks::KEY_YOUTUBE_LIVE]->due_at?->format('Y-m-d H:i'));
        $this->assertSame('2026-10-05 20:00', $tasks[GrowthOperationalTasks::KEY_TECHNICAL_TEST]->due_at?->format('Y-m-d H:i'));
        $this->assertSame('2026-10-06 17:00', $tasks[GrowthOperationalTasks::KEY_SOCIAL_REMINDER]->due_at?->format('Y-m-d H:i'));
        $this->assertSame('2026-10-06 20:00', $tasks[GrowthOperationalTasks::KEY_LIVE_WEBINAR]->due_at?->format('Y-m-d H:i'));
        $this->assertSame('2026-10-07 20:00', $tasks[GrowthOperationalTasks::KEY_RECORDING]->due_at?->format('Y-m-d H:i'));
        $this->assertSame('2026-10-09 20:00', $tasks[GrowthOperationalTasks::KEY_CONTENT_REPURPOSING]->due_at?->format('Y-m-d H:i'));

        $undated = $this->campaign($user, null);
        app(GrowthOperationalTasks::class)->ensureForCampaign($undated);

        $this->assertTrue(
            GrowthTask::query()->where('growth_campaign_id', $undated->id)->get()->every(
                fn (GrowthTask $task): bool => $task->due_at === null
            )
        );
    }

    public function test_task_key_is_unique_inside_campaign(): void
    {
        $user = User::factory()->create();
        $campaign = $this->campaign($user, Carbon::parse('2026-10-06 20:00:00'));
        app(GrowthOperationalTasks::class)->ensureForCampaign($campaign);

        $this->expectException(QueryException::class);

        GrowthTask::query()->create([
            'growth_campaign_id' => $campaign->id,
            'key' => GrowthOperationalTasks::KEY_YOUTUBE_LIVE,
            'title' => 'Duplikat',
            'status' => GrowthTask::STATUS_TODO,
        ]);
    }

    public function test_checkbox_toggles_completion_without_a_decision_and_survives_a_new_session(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), $this->projectPayload());

        $this->actingAs($user)
            ->post(route('growth.projects.tasks.update', [DemoTikWebinarProject::PROJECT_ID, GrowthOperationalTasks::KEY_YOUTUBE_LIVE]), [
                'done' => '1',
            ])
            ->assertRedirect(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID).'#timeline');

        $task = GrowthTask::query()->where('key', GrowthOperationalTasks::KEY_YOUTUBE_LIVE)->first();
        $this->assertNotNull($task);
        $this->assertSame(GrowthTask::STATUS_DONE, $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertSame(0, GrowthDecision::query()->count());

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('Następny krok: zatwierdź kierunek')
            ->assertSee('grafika')
            ->assertSee('YouTube Live');

        $this->actingAs($user)
            ->post(route('growth.projects.tasks.update', [DemoTikWebinarProject::PROJECT_ID, GrowthOperationalTasks::KEY_YOUTUBE_LIVE]), [
                'done' => '0',
            ]);

        $this->assertSame(GrowthTask::STATUS_TODO, $task->fresh()->status);
        $this->assertNull($task->fresh()->completed_at);

        $this->actingAs($user)
            ->post(route('growth.projects.tasks.update', [DemoTikWebinarProject::PROJECT_ID, GrowthOperationalTasks::KEY_RECORDING]), [
                'done' => '1',
            ]);

        session()->forget(DemoTikWebinarProject::SESSION_PROJECT);

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('Nagranie');

        $this->assertSame(GrowthTask::STATUS_DONE, GrowthTask::query()->where('key', GrowthOperationalTasks::KEY_RECORDING)->value('status'));
        $this->assertSame(0, GrowthDecision::query()->count());
    }

    public function test_opening_the_project_does_not_create_tasks(): void
    {
        $user = $this->superAdmin();
        $this->campaign($user, Carbon::parse('2026-10-06 20:00:00'));

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('temat')
            ->assertSee('grafika');

        $this->assertSame(0, GrowthTask::query()->count());
    }

    private function campaign(User $owner, ?Carbon $liveAt): GrowthCampaign
    {
        return GrowthCampaign::query()->create([
            'name' => 'Webinar TIK',
            'type' => 'Webinar TIK',
            'status' => GrowthCampaign::STATUS_PLANNING,
            'owner_user_id' => $owner->id,
            'working_topic' => 'Canva AI w pracy nauczyciela',
            'live_at' => $liveAt,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function projectPayload(): array
    {
        return [
            'type' => 'Webinar TIK',
            'live_date' => '2026-10-06',
            'live_time' => '20:00',
            'host' => 'Waldemar Grabowski',
            'goal' => 'education',
            'topic' => 'Canva AI w pracy nauczyciela',
        ];
    }

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'super_admin'],
            [
                'display_name' => 'super_admin',
                'description' => 'Rola testowa Growth OS',
                'is_system' => true,
                'level' => 100,
            ]
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
