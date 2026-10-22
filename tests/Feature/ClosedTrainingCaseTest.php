<?php

namespace Tests\Feature;

use App\Mail\ClosedTrainingCaseSchoolMail;
use App\Models\ClosedTrainingCase;
use App\Models\ClosedTrainingCaseEvent;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\TrainingOffer;
use App\Models\User;
use App\Services\ClosedTrainingSubmissionBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
            ->assertSee('Symbol')
            ->assertSee('Ustalenia')
            ->assertSee('Ostatni wpis')
            ->assertSee('['.$case->referenceCode().']', false)
            ->assertSee('Szkolenie rady przykładowej')
            ->assertSee('Telefon: prośba o termin w listopadzie.')
            ->assertSee('Oczekuje');
    }

    public function test_case_list_shows_negotiation_summary_compactly(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $instructor = Instructor::query()->create([
            'title' => 'dr',
            'first_name' => 'Lista',
            'last_name' => 'Trenerowska',
            'email' => 'lista.'.uniqid().'@example.com',
            'is_active' => true,
        ]);
        $case = $this->makeCase([
            'topic' => 'Sprawa z podsumowaniem na liście',
            'instructor_id' => $instructor->id,
            'client_price' => 3000,
            'client_price_confirmed_at' => now(),
            'instructor_fee' => 1500,
            'start_date' => Carbon::parse('2026-11-04 16:00:00', 'Europe/Warsaw'),
            'schedule_time_known' => true,
        ]);

        $this->actingAs($user)
            ->get(route('closed-training-cases.index', ['status' => 'all']))
            ->assertOk()
            ->assertSee('TERMIN:')
            ->assertSee('2026-11-04 16:00', false)
            ->assertSee('Trenerowska')
            ->assertSee('(1 500 PLN)', false)
            ->assertSee('CENA:')
            ->assertSee('3 000 PLN', false)
            ->assertSee('✓', false);
    }

    public function test_case_list_sort_by_symbol_id_is_remembered_in_user_preferences(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $older = $this->makeCase(['topic' => 'Sprawa starsza sort']);
        $newer = $this->makeCase(['topic' => 'Sprawa nowsza sort']);

        $this->actingAs($user)
            ->get(route('closed-training-cases.index', [
                'status' => 'all',
                'sort' => 'id',
                'dir' => 'asc',
            ]))
            ->assertOk();

        $html = $this->actingAs($user)
            ->get(route('closed-training-cases.index', ['status' => 'all']))
            ->assertOk()
            ->getContent();

        $this->assertLessThan(
            strpos($html, 'Sprawa nowsza sort'),
            strpos($html, 'Sprawa starsza sort')
        );

        $user->refresh();
        $this->assertSame('id', data_get($user->preferences, 'closed_training_cases_list.sort'));
        $this->assertSame('asc', data_get($user->preferences, 'closed_training_cases_list.dir'));
        $this->assertSame($older->id, min($older->id, $newer->id));
    }

    public function test_case_list_sort_by_last_activity(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $quiet = $this->makeCase(['topic' => 'Sprawa cicha aktywność']);
        $active = $this->makeCase(['topic' => 'Sprawa głośna aktywność']);

        $active->events()->create([
            'user_id' => $user->id,
            'event_type' => ClosedTrainingCaseEvent::TYPE_NOTE,
            'body' => 'Późniejsza notatka',
            'happened_at' => now()->addDay(),
        ]);
        $quiet->events()->create([
            'user_id' => $user->id,
            'event_type' => ClosedTrainingCaseEvent::TYPE_NOTE,
            'body' => 'Wcześniejsza notatka',
            'happened_at' => now()->subDay(),
        ]);

        $html = $this->actingAs($user)
            ->get(route('closed-training-cases.index', [
                'status' => 'all',
                'sort' => 'last_activity',
                'dir' => 'desc',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertLessThan(
            strpos($html, 'Sprawa cicha aktywność'),
            strpos($html, 'Sprawa głośna aktywność')
        );
    }

    public function test_operator_can_find_a_case_by_email_reference_symbol(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $case = ClosedTrainingCase::query()->create([
            'source' => ClosedTrainingCase::SOURCE_MANUAL,
            'status' => ClosedTrainingCase::STATUS_NEW,
            'topic' => 'Szkolenie do wyszukania po symbolu',
            'contact_name' => 'Osoba SZ',
            'contact_email' => 'sz@example.com',
            'institution' => 'Placówka SZ',
            'message' => 'Wiadomość sprawy SZ.',
        ]);
        $other = ClosedTrainingCase::query()->create([
            'source' => ClosedTrainingCase::SOURCE_MANUAL,
            'status' => ClosedTrainingCase::STATUS_NEW,
            'topic' => 'Inna sprawa bez trafienia',
            'contact_name' => 'Inna osoba',
            'contact_email' => 'inna@example.com',
            'institution' => 'Inna placówka',
            'message' => 'Wiadomość innej sprawy.',
        ]);

        $this->actingAs($user)
            ->get(route('closed-training-cases.index', ['q' => '[SZ-'.$case->id.']', 'status' => 'all']))
            ->assertOk()
            ->assertSee('['.$case->referenceCode().']', false)
            ->assertSee('Szkolenie do wyszukania po symbolu')
            ->assertDontSee('Inna sprawa bez trafienia');

        $this->actingAs($user)
            ->get(route('closed-training-cases.index', ['q' => 'SZ-'.$case->id, 'status' => 'all']))
            ->assertOk()
            ->assertSee('Szkolenie do wyszukania po symbolu')
            ->assertDontSee('Inna sprawa bez trafienia');

        $this->assertNotSame($case->id, $other->id);
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

    public function test_operator_can_create_course_from_case_without_changing_status(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $case = $this->makeCase([
            'topic' => 'Szkolenie z sprawy do kursu',
            'status' => ClosedTrainingCase::STATUS_IN_PROGRESS,
        ]);

        $this->actingAs($user)
            ->get(route('closed-training-cases.create-course', $case))
            ->assertOk()
            ->assertSee('Tworzenie szkolenia ze sprawy')
            ->assertSee('name="closed_training_case_id"', false)
            ->assertSee('value="'.$case->id.'"', false);

        $this->actingAs($user)
            ->post(route('courses.store'), [
                'title' => 'Szkolenie z sprawy do kursu',
                'description' => 'Zakres',
                'start_date' => now()->addDays(10)->format('Y-m-d\TH:i'),
                'end_date' => now()->addDays(10)->addHours(2)->format('Y-m-d\TH:i'),
                'is_paid' => '1',
                'type' => 'online',
                'category' => 'closed',
                'closed_training_case_id' => $case->id,
                'show_on_pnedu' => '0',
                'platform' => 'ClickMeeting',
                'meeting_link' => 'https://example.com/meeting',
                'save_action' => 'close',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $case->refresh();
        $this->assertSame(ClosedTrainingCase::STATUS_IN_PROGRESS, $case->status);
        $this->assertNotNull($case->course);
        $this->assertSame('Szkolenie z sprawy do kursu', $case->course->title);
        $this->assertTrue(
            $case->events()->where('body', 'like', 'Powiązano szkolenie (utworzone ze sprawy):%')->exists()
        );
    }

    public function test_operator_can_bind_and_unbind_existing_course(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $case = $this->makeCase();
        $course = $this->makeCourse(['title' => 'Istniejący termin']);

        $this->actingAs($user)
            ->post(route('closed-training-cases.course.bind', $case), [
                'course_id' => $course->id,
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $case->refresh();
        $course->refresh();
        $this->assertSame($case->id, (int) $course->closed_training_case_id);
        $this->assertSame(ClosedTrainingCase::STATUS_NEW, $case->status);

        $this->actingAs($user)
            ->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee('Sprawa szkolenia zamkniętego')
            ->assertSee('Otwórz sprawę');

        $this->actingAs($user)
            ->delete(route('closed-training-cases.course.unbind', $case))
            ->assertRedirect(route('closed-training-cases.show', $case));

        $course->refresh();
        $this->assertNull($course->closed_training_case_id);
        $this->assertTrue(
            $case->events()->where('body', 'like', 'Odłączono szkolenie%')->exists()
        );
    }

    public function test_cannot_silently_take_course_already_linked_to_another_case(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $owner = $this->makeCase(['topic' => 'Sprawa właściciela']);
        $other = $this->makeCase(['topic' => 'Inna sprawa']);
        $course = $this->makeCourse(['title' => 'Zajęty termin']);

        $this->actingAs($user)
            ->post(route('closed-training-cases.course.bind', $owner), [
                'course_id' => $course->id,
            ])
            ->assertRedirect(route('closed-training-cases.show', $owner));

        $this->actingAs($user)
            ->post(route('closed-training-cases.course.bind', $other), [
                'course_id' => $course->id,
            ])
            ->assertSessionHasErrors('course_id');

        $course->refresh();
        $this->assertSame($owner->id, (int) $course->closed_training_case_id);
        $this->assertNull($other->fresh()->course);
    }

    public function test_soft_deleting_linked_course_clears_fk_and_writes_history(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $case = $this->makeCase();
        $course = $this->makeCourse(['title' => 'Do usunięcia']);

        $this->actingAs($user)
            ->post(route('closed-training-cases.course.bind', $case), [
                'course_id' => $course->id,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->delete(route('courses.destroy', $course->id))
            ->assertRedirect(route('courses.index'));

        $this->assertSoftDeleted('courses', ['id' => $course->id]);
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'closed_training_case_id' => null,
        ]);
        $this->assertNull($case->fresh()->course);
        $this->assertTrue(
            $case->events()->where('body', 'like', '%szkolenie usunięte%')->exists()
        );
    }

    public function test_storing_course_without_case_still_works(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->post(route('courses.store'), [
                'title' => 'Szkolenie bez sprawy',
                'description' => 'Zakres',
                'start_date' => now()->addDays(3)->format('Y-m-d\TH:i'),
                'end_date' => now()->addDays(3)->addHours(2)->format('Y-m-d\TH:i'),
                'is_paid' => '1',
                'type' => 'online',
                'category' => 'open',
                'show_on_pnedu' => '0',
                'platform' => 'ClickMeeting',
                'meeting_link' => 'https://example.com/meeting-open',
                'save_action' => 'close',
            ])
            ->assertRedirect(route('courses.index'));

        $this->assertDatabaseHas('courses', [
            'title' => 'Szkolenie bez sprawy',
            'closed_training_case_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function test_operator_can_save_negotiation_fields_and_confirmations(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $instructor = Instructor::query()->create([
            'title' => 'mgr',
            'first_name' => 'Anna',
            'last_name' => 'Trener',
            'email' => 'anna.trener.'.uniqid().'@example.com',
            'is_active' => true,
        ]);
        $case = $this->makeCase();

        $this->actingAs($user)
            ->put(route('closed-training-cases.negotiation.update', $case), [
                'instructor_id' => $instructor->id,
                'client_price' => '2500.50',
                'client_price_confirmed' => '1',
                'instructor_fee' => '1200',
                'instructor_fee_confirmed' => '1',
                'start_date' => '2026-11-10',
                'end_date' => '2026-11-10',
                'schedule_time_known' => '0',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $case->refresh();
        $this->assertSame($instructor->id, (int) $case->instructor_id);
        $this->assertSame('2500.50', (string) $case->client_price);
        $this->assertNotNull($case->client_price_confirmed_at);
        $this->assertSame('1200.00', (string) $case->instructor_fee);
        $this->assertNotNull($case->instructor_fee_confirmed_at);
        $this->assertFalse((bool) $case->schedule_time_known);
        $this->assertTrue(
            $case->events()->where('body', 'like', 'Ustalenia negocjacyjne.%')->exists()
        );

        $this->actingAs($user)
            ->put(route('closed-training-cases.negotiation.update', $case), [
                'instructor_id' => $instructor->id,
                'client_price' => '2600',
                'instructor_fee' => '1200',
                'instructor_fee_confirmed' => '1',
                'start_date' => '2026-11-10',
                'end_date' => '',
                'schedule_time_known' => '0',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $case->refresh();
        $this->assertSame('2600.00', (string) $case->client_price);
        $this->assertNull($case->client_price_confirmed_at);
        $this->assertNotNull($case->instructor_fee_confirmed_at);
    }

    public function test_changing_instructor_clears_fee_confirmation_unless_reconfirmed(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $first = Instructor::query()->create([
            'first_name' => 'Jan',
            'last_name' => 'Pierwszy',
            'email' => 'jan.'.uniqid().'@example.com',
            'is_active' => true,
        ]);
        $second = Instructor::query()->create([
            'first_name' => 'Ewa',
            'last_name' => 'Druga',
            'email' => 'ewa.'.uniqid().'@example.com',
            'is_active' => true,
        ]);
        $case = $this->makeCase([
            'instructor_id' => $first->id,
            'instructor_fee' => 1000,
            'instructor_fee_confirmed_at' => now(),
        ]);

        $this->actingAs($user)
            ->put(route('closed-training-cases.negotiation.update', $case), [
                'instructor_id' => $second->id,
                'instructor_fee' => '1000',
                'start_date' => '',
                'end_date' => '',
            ])
            ->assertRedirect();

        $case->refresh();
        $this->assertSame($second->id, (int) $case->instructor_id);
        $this->assertNull($case->instructor_fee_confirmed_at);
    }

    public function test_linked_case_blocks_schedule_and_instructor_edits_but_allows_prices(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $instructor = Instructor::query()->create([
            'first_name' => 'Ola',
            'last_name' => 'Locked',
            'email' => 'ola.'.uniqid().'@example.com',
            'is_active' => true,
        ]);
        $case = $this->makeCase([
            'instructor_id' => $instructor->id,
            'client_price' => 2000,
            'start_date' => '2026-12-01 09:00:00',
            'end_date' => '2026-12-01 11:00:00',
            'schedule_time_known' => true,
        ]);
        $course = $this->makeCourse([
            'instructor_id' => $instructor->id,
            'title' => 'Powiązany termin',
        ]);

        $this->actingAs($user)
            ->post(route('closed-training-cases.course.bind', $case), [
                'course_id' => $course->id,
                'confirm_mismatch' => '1',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $other = Instructor::query()->create([
            'first_name' => 'Inny',
            'last_name' => 'Trener',
            'email' => 'inny.'.uniqid().'@example.com',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->put(route('closed-training-cases.negotiation.update', $case), [
                'instructor_id' => $other->id,
                'client_price' => '2000',
                'start_date' => '2026-12-02T09:00',
                'end_date' => '2026-12-02T11:00',
                'schedule_time_known' => '1',
            ])
            ->assertSessionHasErrors(['instructor_id', 'start_date']);

        $this->actingAs($user)
            ->put(route('closed-training-cases.negotiation.update', $case), [
                'instructor_id' => $instructor->id,
                'client_price' => '2100',
                'client_price_confirmed' => '1',
                'instructor_fee' => '900',
                'start_date' => '2026-12-01T09:00',
                'end_date' => '2026-12-01T11:00',
                'schedule_time_known' => '1',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $case->refresh();
        $this->assertSame($instructor->id, (int) $case->instructor_id);
        $this->assertSame('2100.00', (string) $case->client_price);
        $this->assertNotNull($case->client_price_confirmed_at);
    }

    public function test_create_course_form_prefills_instructor_and_dates_from_case(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $instructor = Instructor::query()->create([
            'first_name' => 'Prefill',
            'last_name' => 'Trener',
            'email' => 'prefill.'.uniqid().'@example.com',
            'is_active' => true,
        ]);
        $case = $this->makeCase([
            'topic' => 'Temat z datami ze sprawy',
            'instructor_id' => $instructor->id,
            'start_date' => Carbon::parse('2026-11-20 10:00:00', 'Europe/Warsaw'),
            'end_date' => Carbon::parse('2026-11-20 12:00:00', 'Europe/Warsaw'),
            'schedule_time_known' => true,
        ]);

        $this->actingAs($user)
            ->get(route('closed-training-cases.create-course', $case))
            ->assertOk()
            ->assertSee('value="'.$instructor->id.'"', false)
            ->assertSee('2026-11-20T10:00', false);
    }

    public function test_unbind_clears_fee_confirmation_and_keeps_case_fields(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $instructor = Instructor::query()->create([
            'first_name' => 'Unbind',
            'last_name' => 'Test',
            'email' => 'unbind.'.uniqid().'@example.com',
            'is_active' => true,
        ]);
        $case = $this->makeCase([
            'instructor_id' => $instructor->id,
            'instructor_fee' => 800,
            'instructor_fee_confirmed_at' => now(),
            'client_price' => 3000,
            'client_price_confirmed_at' => now(),
            'start_date' => '2026-11-01 09:00:00',
        ]);
        $course = $this->makeCourse(['instructor_id' => $instructor->id]);

        $this->actingAs($user)
            ->post(route('closed-training-cases.course.bind', $case), [
                'course_id' => $course->id,
                'confirm_mismatch' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->delete(route('closed-training-cases.course.unbind', $case))
            ->assertRedirect(route('closed-training-cases.show', $case));

        $case->refresh();
        $this->assertSame($instructor->id, (int) $case->instructor_id);
        $this->assertSame('800.00', (string) $case->instructor_fee);
        $this->assertNull($case->instructor_fee_confirmed_at);
        $this->assertNotNull($case->client_price_confirmed_at);
        $this->assertNotNull($case->start_date);
    }

    private function makeCase(array $overrides = []): ClosedTrainingCase
    {
        return ClosedTrainingCase::query()->create(array_merge([
            'source' => ClosedTrainingCase::SOURCE_MANUAL,
            'status' => ClosedTrainingCase::STATUS_NEW,
            'topic' => 'Sprawa testowa',
            'contact_name' => 'Osoba',
            'message' => 'Treść zapytania.',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeCourse(array $overrides = []): Course
    {
        $start = now()->addDays(5);

        return Course::query()->create(array_merge([
            'title' => 'Kurs testowy',
            'description' => 'Opis',
            'start_date' => $start,
            'end_date' => $start->copy()->addHours(2),
            'is_paid' => true,
            'type' => 'online',
            'category' => 'closed',
            'is_active' => true,
            'certificate_format' => '{nr}/PNE',
        ], $overrides));
    }
}
