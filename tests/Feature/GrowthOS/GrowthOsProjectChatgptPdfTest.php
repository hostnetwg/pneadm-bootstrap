<?php

namespace Tests\Feature\GrowthOS;

use App\Models\Role;
use App\Models\User;
use App\Support\GrowthOS\DemoTikWebinarProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthOsProjectChatgptPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_page_offers_chatgpt_pdf_export(): void
    {
        $user = $this->readyProject();

        $this->actingAs($user)
            ->get(route('growth.projects.show', DemoTikWebinarProject::PROJECT_ID))
            ->assertOk()
            ->assertSee('Zapisz do PDF aktualny stan projektu')
            ->assertSee(route('growth.projects.chatgpt-pdf', DemoTikWebinarProject::PROJECT_ID), false);
    }

    public function test_project_chatgpt_pdf_contains_only_approved_elements(): void
    {
        $user = $this->readyProject();
        $this->saveMaterial($user, 'youtube-description', 'APPROVED', 'Zatwierdzony opis YouTube o Canva AI.');
        $this->saveMaterial($user, 'facebook-post', 'DRAFT', 'Szkic posta — nie powinien wejść do PDF jako zatwierdzony.');

        $response = $this->actingAs($user)
            ->get(route('growth.projects.chatgpt-pdf', DemoTikWebinarProject::PROJECT_ID));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('growth_projekt_zatwierdzone_', (string) $response->headers->get('content-disposition'));

        $project = DemoTikWebinarProject::requireProject(DemoTikWebinarProject::PROJECT_ID);
        $snapshot = DemoTikWebinarProject::chatgptProjectSnapshot($project);

        $this->assertTrue($snapshot['direction']['approved']);
        $this->assertTrue($snapshot['concept']['approved']);
        $this->assertNotNull($snapshot['direction']['fields']);
        $this->assertNotNull($snapshot['concept']['fields']);
        $this->assertSame('Canva AI w pracy nauczyciela', $snapshot['campaign']['topic']);
        $this->assertCount(1, $snapshot['materials_approved']);
        $this->assertSame('youtube-description', $snapshot['materials_approved'][0]['key']);
        $this->assertStringContainsString('Zatwierdzony opis YouTube', $snapshot['materials_approved'][0]['draft']);
        $this->assertTrue(
            collect($snapshot['materials_pending'])->contains(fn (array $row): bool => $row['key'] === 'facebook-post')
        );
        $this->assertFalse(
            collect($snapshot['materials_approved'])->contains(fn (array $row): bool => $row['key'] === 'facebook-post')
        );
    }

    public function test_snapshot_omits_unapproved_direction_and_concept_content(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), [
            'type' => 'Webinar TIK',
            'live_date' => now()->addDays(7)->toDateString(),
            'live_time' => '20:00',
            'host' => 'Waldemar Grabowski',
            'goal' => 'education',
            'topic' => 'NotebookLM dla nauczycieli',
        ])->assertRedirect();

        $project = DemoTikWebinarProject::requireProject(DemoTikWebinarProject::PROJECT_ID);
        $snapshot = DemoTikWebinarProject::chatgptProjectSnapshot($project);

        $this->assertFalse($snapshot['direction']['approved']);
        $this->assertNull($snapshot['direction']['fields']);
        $this->assertFalse($snapshot['concept']['approved']);
        $this->assertNull($snapshot['concept']['fields']);
        $this->assertSame('NotebookLM dla nauczycieli', $snapshot['campaign']['topic']);
    }

    private function readyProject(): User
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->post(route('growth.projects.store'), [
            'type' => 'Webinar TIK',
            'live_date' => now()->addDays(7)->toDateString(),
            'live_time' => '20:00',
            'host' => 'Waldemar Grabowski',
            'goal' => 'education',
            'topic' => 'Canva AI w pracy nauczyciela',
        ]);
        foreach (['direction', 'concept'] as $step) {
            $this->actingAs($user)
                ->post(route('growth.projects.steps.complete', [DemoTikWebinarProject::PROJECT_ID, $step]))
                ->assertRedirect();
        }

        return $user;
    }

    private function saveMaterial(User $user, string $key, string $status, string $draft): void
    {
        $this->actingAs($user)
            ->post(route('growth.projects.materials.status', [DemoTikWebinarProject::PROJECT_ID, $key]), [
                'status' => $status,
                'draft' => $draft,
            ])
            ->assertRedirect();
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
            ],
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
