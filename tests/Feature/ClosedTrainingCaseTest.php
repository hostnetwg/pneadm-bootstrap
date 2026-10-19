<?php

namespace Tests\Feature;

use App\Mail\ClosedTrainingCaseSchoolMail;
use App\Models\ClosedTrainingCase;
use App\Models\ClosedTrainingCaseEvent;
use App\Models\TrainingOffer;
use App\Models\User;
use App\Services\ClosedTrainingSubmissionBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
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
                'institution_type' => ClosedTrainingCase::INSTITUTION_PRIMARY,
                'institution_type_other' => 'Poradnia, która nie powinna zostać',
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
        $this->assertSame(ClosedTrainingCase::INSTITUTION_PRIMARY, $case->institution_type);
        $this->assertNull($case->institution_type_other);

        $this->actingAs($user)
            ->get(route('closed-training-cases.show', $case))
            ->assertOk()
            ->assertSee('Temat szkolenia')
            ->assertSee('Rodzaj placówki')
            ->assertSee('Szkoła podstawowa')
            ->assertSee('Nazwa szkoły / placówki')
            ->assertSee('Przykładowa placówka')
            ->assertDontSee('Poradnia, która nie powinna zostać')
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

        $this->actingAs($user)
            ->put(route('closed-training-cases.contact.update', $case), [
                'contact_name' => 'Osoba poprawiona',
                'contact_email' => 'poprawiony@example.com',
                'contact_phone' => '500600700',
                'institution' => 'Poprawiona nazwa placówki',
                'institution_www' => 'szkola.edu.pl',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case))
            ->assertSessionHas('success', 'Zapisano dane zapytania.');

        $case->refresh();
        $this->assertSame('Osoba poprawiona', $case->contact_name);
        $this->assertSame('poprawiony@example.com', $case->contact_email);
        $this->assertSame('szkola@example.com', $case->submitted_payload['contact_email']);
        $this->assertSame('Osoba kontaktowa', $case->submitted_payload['contact_name']);

        $this->actingAs($user)
            ->get(route('closed-training-cases.show', $case))
            ->assertOk()
            ->assertSee('Oryginał wpisu')
            ->assertSee('szkola@example.com')
            ->assertSee('poprawiony@example.com');
        $this->assertSame('500600700', $case->contact_phone);
        $this->assertSame('Poprawiona nazwa placówki', $case->institution);
        $this->assertSame('https://szkola.edu.pl', $case->institution_www);
        $this->assertSame(ClosedTrainingCase::STATUS_WAITING, $case->status);

        $contactEvent = $case->events()->where('body', 'like', 'Dane zapytania.%')->first();
        $this->assertNotNull($contactEvent);
        $this->assertStringContainsString('szkola@example.com → poprawiony@example.com', $contactEvent->body);
        $this->assertStringContainsString('Osoba: Osoba kontaktowa → Osoba poprawiona', $contactEvent->body);

        $this->actingAs($user)
            ->put(route('closed-training-cases.contact.update', $case), [
                'contact_name' => '',
                'contact_email' => 'nie-email',
            ])
            ->assertSessionHasErrors(['contact_name', 'contact_email']);

        $this->actingAs($user)
            ->put(route('closed-training-cases.contact.update', $case), [
                'contact_name' => 'Osoba poprawiona',
                'contact_email' => '',
                'contact_phone' => '',
                'institution' => '',
                'institution_www' => '',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $case->refresh();
        $this->assertNull($case->contact_email);
        $this->assertNull($case->contact_phone);
        $this->assertNull($case->institution);
        $this->assertNull($case->institution_www);
        $this->assertSame(ClosedTrainingCase::INSTITUTION_PRIMARY, $case->institution_type);
        $this->assertSame(ClosedTrainingCase::FORMAT_ONLINE, $case->preferred_format);
        $this->assertSame('Szkolenie rady przykładowej', $case->topic);

        $offer = TrainingOffer::query()->create([
            'title' => 'Oferta do powiązania',
            'slug' => 'oferta-do-powiazania',
        ]);

        $this->actingAs($user)
            ->put(route('closed-training-cases.contact.update', $case), [
                'contact_name' => 'Osoba poprawiona',
                'institution_type' => ClosedTrainingCase::INSTITUTION_PRIMARY,
                'institution_type_other' => 'Ten tekst nie może zostać',
                'preferred_format' => ClosedTrainingCase::FORMAT_ONSITE,
                'training_offer_id' => $offer->id,
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $case->refresh();
        $this->assertSame(ClosedTrainingCase::INSTITUTION_PRIMARY, $case->institution_type);
        $this->assertNull($case->institution_type_other);
        $this->assertSame(ClosedTrainingCase::FORMAT_ONSITE, $case->preferred_format);
        $this->assertSame($offer->id, $case->training_offer_id);
        $this->assertSame('Szkolenie rady przykładowej', $case->topic);

        $offerEvent = $case->events()->reorder()->where('body', 'like', '%Oferta: brak → Oferta do powiązania%')->first();
        $this->assertNotNull($offerEvent);
        $this->assertStringContainsString('Forma: Online → Stacjonarnie', $offerEvent->body);
        $this->assertStringContainsString('Oferta: brak → Oferta do powiązania', $offerEvent->body);
        $this->assertStringNotContainsString('Ten tekst nie może zostać', $offerEvent->body);

        $this->actingAs($user)
            ->put(route('closed-training-cases.contact.update', $case), [
                'contact_name' => 'Osoba poprawiona',
                'institution_type' => ClosedTrainingCase::INSTITUTION_OTHER,
                'preferred_format' => ClosedTrainingCase::FORMAT_ONSITE,
                'training_offer_id' => $offer->id,
            ])
            ->assertSessionHasErrors(['institution_type_other']);

        $this->actingAs($user)
            ->put(route('closed-training-cases.contact.update', $case), [
                'contact_name' => 'Osoba poprawiona',
                'institution_type' => ClosedTrainingCase::INSTITUTION_OTHER,
                'institution_type_other' => 'Poradnia psychologiczno-pedagogiczna',
                'preferred_format' => '',
                'training_offer_id' => '',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $case->refresh();
        $this->assertSame(ClosedTrainingCase::INSTITUTION_OTHER, $case->institution_type);
        $this->assertSame('Poradnia psychologiczno-pedagogiczna', $case->institution_type_other);
        $this->assertNull($case->preferred_format);
        $this->assertNull($case->training_offer_id);
        $this->assertNull($case->offer_url);
        $this->assertSame('Szkolenie rady przykładowej', $case->topic);

        $this->assertSame(7, $case->events()->count());
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
            ->assertSee('Rodzaj placówki')
            ->assertSee('Oryginał z formularza')
            ->assertSee('Oryginał nie został zapisany.')
            ->assertDontSee('(temat nie określony)');

        $this->actingAs($user)
            ->get(route('closed-training-cases.index'))
            ->assertOk()
            ->assertSee('Nie podano')
            ->assertDontSee('(temat nie określony)');
    }

    public function test_manual_case_stores_other_institution_type_only_with_detail(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->post(route('closed-training-cases.store'), [
                'topic' => 'Szkolenie bez rodzaju',
                'contact_name' => 'Osoba kontaktowa',
                'message' => 'Telefon.',
            ])
            ->assertSessionHasErrors(['institution_type']);

        $this->actingAs($user)
            ->post(route('closed-training-cases.store'), [
                'topic' => 'Szkolenie dla poradni',
                'contact_name' => 'Osoba kontaktowa',
                'message' => 'Telefon w sprawie poradni.',
                'institution_type' => ClosedTrainingCase::INSTITUTION_OTHER,
            ])
            ->assertSessionHasErrors(['institution_type_other']);

        $this->actingAs($user)
            ->post(route('closed-training-cases.store'), [
                'topic' => 'Szkolenie dla poradni',
                'contact_name' => 'Osoba kontaktowa',
                'message' => 'Telefon w sprawie poradni.',
                'institution_type' => ClosedTrainingCase::INSTITUTION_OTHER,
                'institution_type_other' => 'Poradnia psychologiczno-pedagogiczna',
            ])
            ->assertRedirect();

        $case = ClosedTrainingCase::query()->where('topic', 'Szkolenie dla poradni')->first();
        $this->assertNotNull($case);
        $this->assertNull($case->institution);

        $this->actingAs($user)
            ->get(route('closed-training-cases.show', $case))
            ->assertOk()
            ->assertSee('Poradnia psychologiczno-pedagogiczna')
            ->assertSee('Inna')
            ->assertSee('Oryginał wpisu');
    }

    public function test_backfill_copies_untouched_cases_and_skips_already_corrected_ones(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $untouched = ClosedTrainingCase::query()->create([
            'source' => ClosedTrainingCase::SOURCE_GENERAL_FORM,
            'status' => ClosedTrainingCase::STATUS_NEW,
            'topic' => 'Temat sprzed kopii',
            'contact_name' => 'Osoba niepoprawiana',
            'contact_email' => 'oryginal@example.com',
            'message' => 'Treść z formularza.',
            'preferred_format' => ClosedTrainingCase::FORMAT_ONLINE,
        ]);

        $corrected = ClosedTrainingCase::query()->create([
            'source' => ClosedTrainingCase::SOURCE_OFFER_FORM,
            'status' => ClosedTrainingCase::STATUS_NEW,
            'topic' => 'Temat już poprawiany',
            'contact_name' => 'Osoba po poprawce',
            'contact_email' => 'nowy@example.com',
            'message' => 'Treść po poprawce.',
        ]);
        $corrected->events()->create([
            'user_id' => $user->id,
            'event_type' => ClosedTrainingCaseEvent::TYPE_UPDATED,
            'body' => 'Dane zapytania. E-mail: stary@example.com → nowy@example.com.',
            'happened_at' => now(),
        ]);

        $copied = app(ClosedTrainingSubmissionBackfill::class)->backfillUntouched();

        $untouched->refresh();
        $corrected->refresh();

        $this->assertSame(1, $copied);
        $this->assertSame('oryginal@example.com', $untouched->submitted_payload['contact_email']);
        $this->assertSame('Temat sprzed kopii', $untouched->submitted_payload['topic']);
        $this->assertSame(ClosedTrainingCase::FORMAT_ONLINE, $untouched->submitted_payload['preferred_format']);
        $this->assertNull($corrected->submitted_payload);
    }

    public function test_operator_can_email_the_school_from_the_case_card(): void
    {
        Mail::fake();

        $user = User::factory()->create(['is_active' => true, 'name' => 'Operator Mail']);
        $case = ClosedTrainingCase::query()->create([
            'source' => ClosedTrainingCase::SOURCE_MANUAL,
            'status' => ClosedTrainingCase::STATUS_IN_PROGRESS,
            'topic' => 'Temat do kontaktu',
            'contact_name' => 'Osoba szkoły',
            'contact_email' => 'szkola@example.com',
            'message' => 'Prośba o kontakt.',
            'next_action' => 'Oddzwonić jutro.',
            'assignee_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('closed-training-cases.show', $case))
            ->assertOk()
            ->assertSee('Wyślij e-mail')
            ->assertSee('szkola@example.com')
            ->assertSee($case->defaultSchoolEmailSubject())
            ->assertSee('Z wyrazami szacunku:')
            ->assertSee('Waldemar Grabowski')
            ->assertSee('pnedu.pl')
            ->assertSee('tinymce', false);

        $htmlBody = '<p>Dzień dobry,<br>proszę o propozycję terminu.</p>'.ClosedTrainingCase::defaultSchoolEmailBodyHtml();

        $this->actingAs($user)
            ->post(route('closed-training-cases.email.send', $case), [
                'email_subject' => 'Ustalenie terminu',
                'email_body' => $htmlBody,
            ])
            ->assertRedirect(route('closed-training-cases.show', $case))
            ->assertSessionHas('success');

        Mail::assertSent(ClosedTrainingCaseSchoolMail::class, function (ClosedTrainingCaseSchoolMail $mail) use ($case) {
            $mail->build();

            return $mail->hasTo('szkola@example.com')
                && $mail->subjectLine === '[SZ-'.$case->id.'] Ustalenie terminu'
                && str_contains($mail->htmlBody, 'proszę o propozycję terminu.')
                && str_contains($mail->htmlBody, 'href="https://pnedu.pl"')
                && $mail->from[0]['address'] === config('mail.system.from_address')
                && $mail->replyTo[0]['address'] === config('mail.system.reply_to_address');
        });

        $case->refresh();
        $this->assertSame(ClosedTrainingCase::STATUS_IN_PROGRESS, $case->status);
        $this->assertSame('Oddzwonić jutro.', $case->next_action);
        $this->assertSame($user->id, $case->assignee_user_id);

        $event = $case->events()->where('event_type', ClosedTrainingCaseEvent::TYPE_EMAIL_SENT)->first();
        $this->assertNotNull($event);
        $this->assertSame($user->id, $event->user_id);
        $this->assertStringContainsString('E-mail wysłany do szkoły', $event->body);
        $this->assertStringContainsString('Do: szkola@example.com', $event->body);
        $this->assertStringContainsString('[SZ-'.$case->id.'] Ustalenie terminu', $event->body);
        $this->assertStringContainsString('proszę o propozycję terminu.', $event->body);
        $this->assertStringContainsString('href="https://pnedu.pl"', $event->body);

        $this->actingAs($user)
            ->get(route('closed-training-cases.show', $case))
            ->assertOk()
            ->assertSee('E-mail do szkoły')
            ->assertSee('E-mail wysłany do szkoły')
            ->assertSee('proszę o propozycję terminu.', false);
    }

    public function test_school_email_failure_does_not_write_history_or_change_handling(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $case = ClosedTrainingCase::query()->create([
            'source' => ClosedTrainingCase::SOURCE_MANUAL,
            'status' => ClosedTrainingCase::STATUS_WAITING,
            'topic' => 'Temat bez wysyłki',
            'contact_name' => 'Osoba szkoły',
            'contact_email' => 'szkola@example.com',
            'message' => 'Treść.',
            'next_action' => 'Czekamy na odpowiedź.',
        ]);

        $pending = Mockery::mock();
        $pending->shouldReceive('send')->once()->andThrow(new \RuntimeException('smtp down'));
        Mail::shouldReceive('to')->once()->with('szkola@example.com')->andReturn($pending);

        $this->actingAs($user)
            ->post(route('closed-training-cases.email.send', $case), [
                'email_subject' => 'Próba',
                'email_body' => 'Treść próby.',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case))
            ->assertSessionHas('error');

        $case->refresh();
        $this->assertSame(ClosedTrainingCase::STATUS_WAITING, $case->status);
        $this->assertSame('Czekamy na odpowiedź.', $case->next_action);
        $this->assertFalse($case->events()->where('event_type', ClosedTrainingCaseEvent::TYPE_EMAIL_SENT)->exists());
        $this->assertSame(0, $case->events()->count());
    }

    public function test_case_without_email_cannot_send_school_mail(): void
    {
        Mail::fake();

        $user = User::factory()->create(['is_active' => true]);
        $case = ClosedTrainingCase::query()->create([
            'source' => ClosedTrainingCase::SOURCE_MANUAL,
            'status' => ClosedTrainingCase::STATUS_NEW,
            'topic' => 'Bez e-maila',
            'contact_name' => 'Osoba telefon',
            'message' => 'Tylko telefon.',
        ]);

        $this->actingAs($user)
            ->get(route('closed-training-cases.show', $case))
            ->assertOk()
            ->assertSee('Brak poprawnego adresu e-mail')
            ->assertDontSee('name="email_body"', false);

        $this->actingAs($user)
            ->post(route('closed-training-cases.email.send', $case), [
                'email_subject' => 'Nie powinno wyjść',
                'email_body' => 'Treść.',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
        $this->assertFalse($case->events()->where('event_type', ClosedTrainingCaseEvent::TYPE_EMAIL_SENT)->exists());
    }

    public function test_operator_can_edit_and_delete_history_events(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $case = ClosedTrainingCase::query()->create([
            'source' => ClosedTrainingCase::SOURCE_MANUAL,
            'status' => ClosedTrainingCase::STATUS_NEW,
            'topic' => 'Sprawa z historią',
            'contact_name' => 'Osoba',
            'contact_email' => 'szkola@example.com',
            'message' => 'Treść.',
        ]);

        $note = $case->events()->create([
            'user_id' => $user->id,
            'event_type' => ClosedTrainingCaseEvent::TYPE_NOTE,
            'body' => 'Stara notatka.',
            'happened_at' => now(),
        ]);

        $otherCase = ClosedTrainingCase::query()->create([
            'source' => ClosedTrainingCase::SOURCE_MANUAL,
            'status' => ClosedTrainingCase::STATUS_NEW,
            'topic' => 'Inna sprawa',
            'contact_name' => 'Inna osoba',
            'message' => 'Inna treść.',
        ]);
        $foreignNote = $otherCase->events()->create([
            'user_id' => $user->id,
            'event_type' => ClosedTrainingCaseEvent::TYPE_NOTE,
            'body' => 'Cudzy wpis.',
            'happened_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('closed-training-cases.show', $case))
            ->assertOk()
            ->assertSee('Edytuj')
            ->assertSee('Usuń')
            ->assertSee('Stara notatka.');

        $this->actingAs($user)
            ->put(route('closed-training-cases.events.update', [$case, $note]), [
                'body' => 'Poprawiona notatka.',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case))
            ->assertSessionHas('success', 'Wpis historii został zapisany.');

        $note->refresh();
        $this->assertSame('Poprawiona notatka.', $note->body);

        $this->actingAs($user)
            ->put(route('closed-training-cases.events.update', [$case, $foreignNote]), [
                'body' => 'Nie powinno przejść.',
            ])
            ->assertNotFound();

        $this->actingAs($user)
            ->delete(route('closed-training-cases.events.destroy', [$case, $note]))
            ->assertRedirect(route('closed-training-cases.show', $case))
            ->assertSessionHas('success', 'Wpis historii został usunięty.');

        $this->assertDatabaseMissing('closed_training_case_events', ['id' => $note->id]);
        $this->assertDatabaseHas('closed_training_case_events', ['id' => $foreignNote->id]);
    }
}
