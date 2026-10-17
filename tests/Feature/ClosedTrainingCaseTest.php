<?php

namespace Tests\Feature;

use App\Models\ClosedTrainingCase;
use App\Models\ClosedTrainingCaseEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClosedTrainingCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_the_case_list(): void
    {
        $this->get(route('closed-training-cases.index'))
            ->assertRedirect(route('login'));
    }

    public function test_operator_can_create_a_case_and_record_the_next_action(): void
    {
        $user = User::factory()->create(['is_active' => true, 'name' => 'Operator Test']);

        $this->actingAs($user)
            ->post(route('closed-training-cases.store'), [
                'topic' => 'Szkolenie rady przykładowej',
                'contact_name' => 'Osoba kontaktowa',
                'contact_email' => 'szkola@example.com',
                'institution' => 'Przykładowa placówka',
                'preferred_format' => ClosedTrainingCase::FORMAT_ONLINE,
                'message' => 'Telefon: prośba o termin w listopadzie.',
                'next_action' => 'Oddzwonić i potwierdzić potrzeby.',
                'next_action_at' => '2026-10-21 09:00',
            ])
            ->assertRedirect();

        $case = ClosedTrainingCase::query()->first();
        $this->assertNotNull($case);
        $this->assertSame(ClosedTrainingCase::SOURCE_MANUAL, $case->source);
        $this->assertSame(ClosedTrainingCase::STATUS_NEW, $case->status);
        $this->assertNull($case->consent_at);
        $this->assertSame('Oddzwonić i potwierdzić potrzeby.', $case->next_action);

        $this->actingAs($user)
            ->get(route('closed-training-cases.show', $case))
            ->assertOk()
            ->assertSee('Temat szkolenia')
            ->assertSee('Szkolenie rady przykładowej')
            ->assertSee('Telefon: prośba o termin w listopadzie.')
            ->assertSee('Oddzwonić i potwierdzić potrzeby.')
            ->assertSee('Sprawa wpisana ręcznie w panelu.');

        $this->actingAs($user)
            ->put(route('closed-training-cases.update', $case), [
                'status' => ClosedTrainingCase::STATUS_WAITING,
                'assignee_user_id' => $user->id,
                'next_action' => 'Jeżeli nie będzie odpowiedzi, zadzwonić ponownie.',
                'next_action_at' => '2026-10-21 09:00',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $case->refresh();
        $this->assertSame(ClosedTrainingCase::STATUS_WAITING, $case->status);
        $this->assertSame($user->id, $case->assignee_user_id);

        $this->actingAs($user)
            ->post(route('closed-training-cases.notes.store', $case), [
                'body' => 'Ustalono, że szkoła oddzwoni po radzie.',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $this->assertSame(3, $case->events()->count());
        $this->assertTrue($case->events()->where('event_type', ClosedTrainingCaseEvent::TYPE_NOTE)->exists());

        $this->actingAs($user)
            ->get(route('closed-training-cases.index'))
            ->assertOk()
            ->assertSee('Szkolenie rady przykładowej')
            ->assertSee('Oczekuje');
    }

    public function test_case_without_a_given_topic_shows_a_neutral_label(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $case = ClosedTrainingCase::query()->create([
            'source' => ClosedTrainingCase::SOURCE_GENERAL_FORM,
            'status' => ClosedTrainingCase::STATUS_NEW,
            'topic' => '(temat nie określony)',
            'contact_name' => 'Osoba bez tematu',
            'message' => 'Proszę o kontakt, temat doprecyzujemy telefonicznie.',
            'consent_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('closed-training-cases.show', $case))
            ->assertOk()
            ->assertSee('Temat szkolenia')
            ->assertSee('Nie podano')
            ->assertSee('Proszę o kontakt, temat doprecyzujemy telefonicznie.')
            ->assertDontSee('(temat nie określony)');

        $this->actingAs($user)
            ->get(route('closed-training-cases.index'))
            ->assertOk()
            ->assertSee('Nie podano')
            ->assertDontSee('(temat nie określony)');
    }
}
