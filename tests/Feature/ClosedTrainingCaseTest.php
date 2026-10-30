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
                'contact_phone' => '501 234 567',
                'institution' => 'Przykładowa placówka',
                'institution_type' => ClosedTrainingCase::INSTITUTION_PRIMARY,
                'institution_type_other' => 'Poradnia, która nie powinna zostać',
                'preferred_format' => ClosedTrainingCase::FORMAT_ONLINE,
                'message' => 'Telefon: prośba o termin w listopadzie.',
                'inquired_at' => '2026-10-15T14:30',
                'next_action' => 'Oddzwonić i potwierdzić potrzeby.',
                'next_action_at' => '2026-10-21 09:00',
            ])
            ->assertRedirect();

        $case = ClosedTrainingCase::query()->first();
        $this->assertNotNull($case);
        $this->assertSame(ClosedTrainingCase::SOURCE_MANUAL, $case->source);
        $this->assertSame(ClosedTrainingCase::STATUS_NEW, $case->status);
        $this->assertNull($case->consent_at);
        $this->assertNotNull($case->inquired_at);
        $this->assertSame('2026-10-15 14:30', $case->inquired_at->format('Y-m-d H:i'));
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
                'status' => ClosedTrainingCase::STATUS_IN_PROGRESS,
                'assignee_user_id' => $user->id,
                'waiting_on' => ClosedTrainingCase::WAITING_ON_SCHOOL,
                'next_action' => 'Jeżeli nie będzie odpowiedzi, zadzwonić ponownie.',
                'next_action_at' => '2026-10-21 09:00',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $case->refresh();
        $this->assertSame(ClosedTrainingCase::STATUS_IN_PROGRESS, $case->status);
        $this->assertSame(ClosedTrainingCase::WAITING_ON_SCHOOL, $case->waiting_on);
        $this->assertSame($user->id, $case->assignee_user_id);

        $this->actingAs($user)
            ->post(route('closed-training-cases.notes.store', $case), [
                'body' => 'Ustalono, że szkoła oddzwoni po radzie.',
                'note_channel' => ClosedTrainingCaseEvent::NOTE_CHANNEL_PHONE,
                'note_party' => ClosedTrainingCaseEvent::NOTE_PARTY_CLIENT,
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $note = $case->events()->where('event_type', ClosedTrainingCaseEvent::TYPE_NOTE)->first();
        $this->assertNotNull($note);
        $this->assertSame(ClosedTrainingCaseEvent::NOTE_CHANNEL_PHONE, $note->note_channel);
        $this->assertSame(ClosedTrainingCaseEvent::NOTE_PARTY_CLIENT, $note->note_party);
        $this->assertSame('Osoba kontaktowa', $note->note_party_name);

        $this->actingAs($user)
            ->put(route('closed-training-cases.contact.update', $case), [
                'contact_name' => 'Osoba poprawiona',
                'contact_email' => 'poprawiony@example.com',
                'contact_phone' => '500600700',
                'institution' => 'Poprawiona nazwa placówki',
                'pedagogical_council_size' => '48',
                'institution_www' => 'szkola.edu.pl',
                'inquired_at' => '2026-10-15T14:30',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case))
            ->assertSessionHas('success', 'Zapisano dane zapytania.');

        $case->refresh();
        $this->assertSame('Osoba poprawiona', $case->contact_name);
        $this->assertSame('poprawiony@example.com', $case->contact_email);
        $this->assertSame(48, (int) $case->pedagogical_council_size);
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
        $this->assertSame(ClosedTrainingCase::STATUS_IN_PROGRESS, $case->status);

        $contactEvent = $case->events()->where('body', 'like', 'Dane zapytania.%')->first();
        $this->assertNotNull($contactEvent);
        $this->assertStringContainsString('szkola@example.com → poprawiony@example.com', $contactEvent->body);
        $this->assertStringContainsString('Osoba: Osoba kontaktowa → Osoba poprawiona', $contactEvent->body);

        $this->actingAs($user)
            ->put(route('closed-training-cases.contact.update', $case), [
                'contact_name' => '',
                'contact_email' => 'nie-email',
                'inquired_at' => '2026-10-15T14:30',
            ])
            ->assertSessionHasErrors(['contact_name', 'contact_email']);

        $this->actingAs($user)
            ->put(route('closed-training-cases.contact.update', $case), [
                'contact_name' => 'Osoba poprawiona',
                'contact_email' => '',
                'contact_phone' => '',
                'institution' => '',
                'institution_www' => '',
                'inquired_at' => '2026-10-15T14:30',
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
                'inquired_at' => '2026-10-15T14:30',
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
                'inquired_at' => '2026-10-15T14:30',
            ])
            ->assertSessionHasErrors(['institution_type_other']);

        $this->actingAs($user)
            ->put(route('closed-training-cases.contact.update', $case), [
                'contact_name' => 'Osoba poprawiona',
                'institution_type' => ClosedTrainingCase::INSTITUTION_OTHER,
                'institution_type_other' => 'Poradnia psychologiczno-pedagogiczna',
                'preferred_format' => '',
                'training_offer_id' => '',
                'inquired_at' => '2026-10-15T14:30',
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
            ->assertSee('Kontakt')
            ->assertSee('Ustalenia')
            ->assertSee('Ostatni wpis')
            ->assertSee('['.$case->referenceCode().']', false)
            ->assertSee('Szkolenie rady przykładowej')
            ->assertSee('Osoba poprawiona')
            ->assertSee('Operator Test')
            ->assertDontSee('>Opiekun<', false)
            ->assertSee('Telefon: prośba o termin w listopadzie.')
            ->assertSee('W toku')
            ->assertSee('(7)', false)
            ->assertSee('title="Liczba działań w historii sprawy"', false);
    }

    public function test_note_can_store_optional_channel_and_party_with_history_context(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $instructor = Instructor::query()->create([
            'title' => 'mgr',
            'first_name' => 'Jan',
            'last_name' => 'Trenerski',
            'email' => 'trener.'.uniqid().'@example.com',
            'is_active' => true,
        ]);
        $case = $this->makeCase([
            'topic' => 'Sprawa z notatką kontekstową',
            'contact_name' => 'Anna Nowak',
            'instructor_id' => $instructor->id,
        ]);

        $this->actingAs($user)
            ->post(route('closed-training-cases.notes.store', $case), [
                'body' => 'Ustaliliśmy termin szkolenia.',
                'note_channel' => ClosedTrainingCaseEvent::NOTE_CHANNEL_SMS,
                'note_party' => ClosedTrainingCaseEvent::NOTE_PARTY_INSTRUCTOR,
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $note = $case->events()->where('event_type', ClosedTrainingCaseEvent::TYPE_NOTE)->first();
        $this->assertNotNull($note);
        $this->assertSame(ClosedTrainingCaseEvent::NOTE_CHANNEL_SMS, $note->note_channel);
        $this->assertSame(ClosedTrainingCaseEvent::NOTE_PARTY_INSTRUCTOR, $note->note_party);
        $this->assertStringContainsString('Trenerski', (string) $note->note_party_name);
        $this->assertSame('bg-info-subtle', $note->historyItemClass());
        $this->assertSame('bi-chat-left-text', $note->historyIconClass());
        $this->assertSame('bi-pencil', (new ClosedTrainingCaseEvent(['event_type' => ClosedTrainingCaseEvent::TYPE_UPDATED]))->historyIconClass());
        $this->assertSame('bi-envelope', (new ClosedTrainingCaseEvent(['event_type' => ClosedTrainingCaseEvent::TYPE_EMAIL_SENT]))->historyIconClass());
        $this->assertSame('bi-telephone', (new ClosedTrainingCaseEvent([
            'event_type' => ClosedTrainingCaseEvent::TYPE_NOTE,
            'note_channel' => ClosedTrainingCaseEvent::NOTE_CHANNEL_PHONE,
        ]))->historyIconClass());
        $this->assertSame('bi-messenger', (new ClosedTrainingCaseEvent([
            'event_type' => ClosedTrainingCaseEvent::TYPE_NOTE,
            'note_channel' => ClosedTrainingCaseEvent::NOTE_CHANNEL_MESSENGER,
        ]))->historyIconClass());
        $this->assertSame('bg-secondary-subtle', (new ClosedTrainingCaseEvent(['event_type' => ClosedTrainingCaseEvent::TYPE_UPDATED]))->historyItemClass());
        $this->assertSame('bg-success-subtle', (new ClosedTrainingCaseEvent(['event_type' => ClosedTrainingCaseEvent::TYPE_EMAIL_SENT]))->historyItemClass());
        $this->assertSame('SMS · Trener: '.$note->note_party_name, $note->noteContextLabel());

        $this->actingAs($user)
            ->get(route('closed-training-cases.show', $case))
            ->assertOk()
            ->assertSee('SMS · Trener:', false)
            ->assertSee('bi-chat-left-text', false)
            ->assertSee('bg-info-subtle', false)
            ->assertSee('Ustaliliśmy termin szkolenia.')
            ->assertSee('>Messenger<', false)
            ->assertSee('>SMS<', false);
    }

    public function test_operator_can_edit_note_channel_and_party(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $case = $this->makeCase([
            'topic' => 'Sprawa z edycją notatki',
            'contact_name' => 'Ewa Klient',
        ]);

        $this->actingAs($user)
            ->post(route('closed-training-cases.notes.store', $case), [
                'body' => 'Pierwsza wersja notatki.',
                'note_channel' => ClosedTrainingCaseEvent::NOTE_CHANNEL_PHONE,
                'note_party' => ClosedTrainingCaseEvent::NOTE_PARTY_CLIENT,
            ])
            ->assertRedirect();

        $note = $case->events()->where('event_type', ClosedTrainingCaseEvent::TYPE_NOTE)->first();
        $this->assertNotNull($note);

        $this->actingAs($user)
            ->put(route('closed-training-cases.events.update', [$case, $note]), [
                'body' => 'Poprawiona notatka.',
                'note_channel' => ClosedTrainingCaseEvent::NOTE_CHANNEL_EMAIL,
                'note_party' => '',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $note->refresh();
        $this->assertSame('Poprawiona notatka.', $note->body);
        $this->assertSame(ClosedTrainingCaseEvent::NOTE_CHANNEL_EMAIL, $note->note_channel);
        $this->assertNull($note->note_party);
        $this->assertNull($note->note_party_name);
        $this->assertSame('E-mail', $note->noteContextLabel());
    }

    public function test_operator_can_set_main_status_and_closed_outcome(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $case = $this->makeCase(['topic' => 'Sprawa do zaplanowania']);

        $this->actingAs($user)
            ->get(route('closed-training-cases.show', $case))
            ->assertOk()
            ->assertSee('Nowa')
            ->assertSee('W toku')
            ->assertSee('Zakończona')
            ->assertSee('Przeprowadzone')
            ->assertSee('Anulowane')
            ->assertDontSee('value="scheduled"', false)
            ->assertDontSee('value="waiting"', false);

        $this->actingAs($user)
            ->put(route('closed-training-cases.update', $case), [
                'status' => ClosedTrainingCase::STATUS_IN_PROGRESS,
                'assignee_user_id' => $user->id,
                'next_action' => 'Potwierdzić salę.',
                'next_action_at' => '2026-11-01 10:00',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $case->refresh();
        $this->assertSame(ClosedTrainingCase::STATUS_IN_PROGRESS, $case->status);
        $this->assertNull($case->closed_outcome);
        $this->assertNull($case->closed_at);

        $this->actingAs($user)
            ->put(route('closed-training-cases.update', $case), [
                'status' => ClosedTrainingCase::STATUS_CLOSED,
                'closed_outcome' => ClosedTrainingCase::CLOSED_OUTCOME_DELIVERED,
                'assignee_user_id' => $user->id,
                'next_action' => 'Wysłać ankietę.',
                'next_action_at' => null,
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $case->refresh();
        $this->assertSame(ClosedTrainingCase::STATUS_CLOSED, $case->status);
        $this->assertSame(ClosedTrainingCase::CLOSED_OUTCOME_DELIVERED, $case->closed_outcome);
        $this->assertNotNull($case->closed_at);
        $this->assertTrue(
            $case->events()->where('body', 'like', '%Status: W toku → Zakończona%')->exists()
        );
        $this->assertTrue(
            $case->events()->where('body', 'like', '%Wynik zakończenia: brak → Przeprowadzone%')->exists()
        );

        $this->actingAs($user)
            ->get(route('closed-training-cases.index', ['status' => 'all']))
            ->assertOk()
            ->assertSee('Zakończona')
            ->assertSee('Przeprowadzone');
    }

    public function test_closed_cancelled_outcome_sets_closed_at_and_is_hidden_from_open_list(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $case = $this->makeCase(['topic' => 'Sprawa niedogadana']);

        $this->actingAs($user)
            ->put(route('closed-training-cases.update', $case), [
                'status' => ClosedTrainingCase::STATUS_CLOSED,
                'closed_outcome' => ClosedTrainingCase::CLOSED_OUTCOME_CANCELLED,
                'assignee_user_id' => $user->id,
                'next_action' => 'Szkoła zrezygnowała.',
                'next_action_at' => now()->subDay()->format('Y-m-d H:i'),
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $case->refresh();
        $this->assertSame(ClosedTrainingCase::STATUS_CLOSED, $case->status);
        $this->assertSame(ClosedTrainingCase::CLOSED_OUTCOME_CANCELLED, $case->closed_outcome);
        $this->assertNotNull($case->closed_at);
        $this->assertFalse($case->nextActionIsOverdue());

        $this->actingAs($user)
            ->get(route('closed-training-cases.index'))
            ->assertOk()
            ->assertDontSee('Sprawa niedogadana');

        $this->actingAs($user)
            ->get(route('closed-training-cases.index', ['status' => 'closed']))
            ->assertOk()
            ->assertSee('Sprawa niedogadana')
            ->assertSee('Anulowane');
    }

    public function test_case_list_shows_contact_person_phone_and_email_prominently(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $offer = TrainingOffer::query()->create([
            'title' => 'Oferta z info na liście',
            'slug' => 'oferta-z-info-na-liscie-'.uniqid(),
        ]);
        $this->makeCase([
            'topic' => 'Sprawa z wyróżnionym kontaktem',
            'contact_name' => 'Anna Kowalska',
            'contact_phone' => '501 234 567',
            'contact_email' => 'anna.kontakt@example.com',
            'institution' => 'SP nr 7 Testowa',
            'institution_type' => ClosedTrainingCase::INSTITUTION_PRIMARY,
            'institution_www' => 'https://sp7.example.edu.pl',
            'preferred_format' => ClosedTrainingCase::FORMAT_ONLINE,
            'training_offer_id' => $offer->id,
        ]);

        $this->actingAs($user)
            ->get(route('closed-training-cases.index', ['status' => 'all']))
            ->assertOk()
            ->assertSee('Kontakt')
            ->assertSee('Anna Kowalska')
            ->assertSee('501 234 567')
            ->assertSee('anna.kontakt@example.com')
            ->assertSee('tel:501234567', false)
            ->assertSee('mailto:anna.kontakt@example.com', false)
            ->assertSee('bi-info-circle', false)
            ->assertSee('caseInquiryDetailsModal', false)
            ->assertSee('data-institution="SP nr 7 Testowa"', false)
            ->assertSee('data-www="https://sp7.example.edu.pl"', false)
            ->assertSee('data-offer="Oferta z info na liście"', false)
            ->assertSee('Ruch');
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
            ->assertSee('1 500 PLN brutto', false)
            ->assertDontSee('(1 500 PLN)', false)
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
                'inquired_at' => '2026-10-15T10:00',
            ])
            ->assertSessionHasErrors(['institution_type']);

        $this->actingAs($user)
            ->post(route('closed-training-cases.store'), [
                'topic' => 'Szkolenie dla poradni',
                'contact_name' => 'Osoba kontaktowa',
                'message' => 'Telefon w sprawie poradni.',
                'institution_type' => ClosedTrainingCase::INSTITUTION_OTHER,
                'inquired_at' => '2026-10-15T10:00',
            ])
            ->assertSessionHasErrors(['institution_type_other']);

        $this->actingAs($user)
            ->post(route('closed-training-cases.store'), [
                'topic' => 'Szkolenie bez daty zapytania',
                'contact_name' => 'Osoba kontaktowa',
                'message' => 'Telefon.',
                'institution_type' => ClosedTrainingCase::INSTITUTION_PRIMARY,
            ])
            ->assertSessionHasErrors(['inquired_at']);

        $this->actingAs($user)
            ->post(route('closed-training-cases.store'), [
                'topic' => 'Szkolenie dla poradni',
                'contact_name' => 'Osoba kontaktowa',
                'message' => 'Telefon w sprawie poradni.',
                'institution_type' => ClosedTrainingCase::INSTITUTION_OTHER,
                'institution_type_other' => 'Poradnia psychologiczno-pedagogiczna',
                'inquired_at' => '2026-10-15T10:00',
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
                'send_mode' => 'school',
                'email_recipients' => ['szkola@example.com'],
                'email_intent' => 'general',
                'update_waiting_on' => '1',
                'suggested_waiting_on' => ClosedTrainingCase::WAITING_ON_SCHOOL,
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
        $this->assertSame(ClosedTrainingCase::WAITING_ON_SCHOOL, $case->waiting_on);
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
            'status' => ClosedTrainingCase::STATUS_IN_PROGRESS,
            'topic' => 'Temat bez wysyłki',
            'contact_name' => 'Osoba szkoły',
            'contact_email' => 'szkola@example.com',
            'message' => 'Treść.',
            'next_action' => 'Czekamy na odpowiedź.',
        ]);

        $pending = Mockery::mock();
        $pending->shouldReceive('send')->once()->andThrow(new \RuntimeException('smtp down'));
        Mail::shouldReceive('to')->once()->with(['szkola@example.com'])->andReturn($pending);

        $this->actingAs($user)
            ->post(route('closed-training-cases.email.send', $case), [
                'email_subject' => 'Próba',
                'email_body' => 'Treść próby.',
                'send_mode' => 'school',
                'email_recipients' => ['szkola@example.com'],
                'email_intent' => 'general',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case))
            ->assertSessionHas('error');

        $case->refresh();
        $this->assertSame(ClosedTrainingCase::STATUS_IN_PROGRESS, $case->status);
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
            ->assertSee('Brak e-maila szkoły i prowadzącego')
            ->assertDontSee('name="email_body"', false);

        $this->actingAs($user)
            ->post(route('closed-training-cases.email.send', $case), [
                'email_subject' => 'Nie powinno wyjść',
                'email_body' => 'Treść.',
                'send_mode' => 'school',
                'email_recipients' => ['szkola@example.com'],
                'email_intent' => 'general',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case))
            ->assertSessionHasErrors('email_recipients.0');

        Mail::assertNothingSent();
        $this->assertFalse($case->events()->where('event_type', ClosedTrainingCaseEvent::TYPE_EMAIL_SENT)->exists());
    }

    public function test_operator_can_save_extra_contact_emails_and_send_to_several(): void
    {
        Mail::fake();

        $user = User::factory()->create(['is_active' => true]);
        $case = $this->makeCase([
            'contact_email' => 'sekretariat@example.com',
            'status' => ClosedTrainingCase::STATUS_IN_PROGRESS,
        ]);

        $this->actingAs($user)
            ->put(route('closed-training-cases.contact.update', $case), [
                'contact_name' => 'Osoba',
                'contact_email' => 'sekretariat@example.com',
                'contact_email_alt1' => 'dyrektor@example.com',
                'contact_email_alt2' => 'zastepca@example.com',
                'inquired_at' => $case->inquired_at->timezone('Europe/Warsaw')->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $case->refresh();
        $this->assertSame('dyrektor@example.com', $case->contact_email_alt1);
        $this->assertSame('zastepca@example.com', $case->contact_email_alt2);

        $this->actingAs($user)
            ->post(route('closed-training-cases.email.send', $case), [
                'email_subject' => 'Do dwóch osób',
                'email_body' => '<p>Treść do szkoły.</p>',
                'send_mode' => 'school',
                'email_recipients' => ['sekretariat@example.com', 'dyrektor@example.com'],
                'email_intent' => 'general',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case))
            ->assertSessionHas('success');

        Mail::assertSent(ClosedTrainingCaseSchoolMail::class, function (ClosedTrainingCaseSchoolMail $mail) {
            return $mail->hasTo('sekretariat@example.com') && $mail->hasTo('dyrektor@example.com');
        });

        $event = $case->events()->where('event_type', ClosedTrainingCaseEvent::TYPE_EMAIL_SENT)->first();
        $this->assertNotNull($event);
        $this->assertStringContainsString('Do: sekretariat@example.com, dyrektor@example.com', $event->body);
        $this->assertSame(ClosedTrainingCase::STATUS_IN_PROGRESS, $case->fresh()->status);
    }

    public function test_scheduled_notice_email_sets_status_and_test_does_not(): void
    {
        Mail::fake();

        $user = User::factory()->create(['is_active' => true]);
        $instructor = Instructor::query()->create([
            'first_name' => 'Roman',
            'last_name' => 'Lorens',
            'title' => 'dr',
            'email' => 'roman.lorens.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $case = $this->makeCase([
            'contact_email' => 'szkola@example.com',
            'status' => ClosedTrainingCase::STATUS_IN_PROGRESS,
            'topic' => 'Odpowiedzialność prawna',
        ]);
        $start = Carbon::parse('2026-10-29 17:00:00', 'Europe/Warsaw');
        $course = $this->makeCourse([
            'title' => 'Odpowiedzialność prawna nauczyciela',
            'type' => 'online',
            'category' => 'closed',
            'instructor_id' => $instructor->id,
            'start_date' => $start,
            'end_date' => $start->copy()->addHours(2),
            'closed_training_case_id' => $case->id,
        ]);
        $course->onlineDetails()->create([
            'platform' => 'ClickMeeting',
            'meeting_link' => 'https://pnedu.clickmeeting.com/przyklad-link',
            'clickmeeting_event_id' => '12345',
        ]);

        $this->actingAs($user)
            ->post(route('closed-training-cases.email.prepare-scheduled', $case))
            ->assertRedirect(route('closed-training-cases.show', $case).'#closed-training-case-email')
            ->assertSessionHas('email_draft');

        $draft = session('email_draft');
        $this->assertIsArray($draft);
        $this->assertSame('scheduled_notice', $draft['intent']);
        $this->assertStringContainsString('Szkolenie zaplanowane', $draft['subject']);
        $this->assertStringContainsString('https://pnedu.clickmeeting.com/przyklad-link', $draft['body']);
        $this->assertStringContainsString('29.10.2026', $draft['body']);

        $this->actingAs($user)
            ->post(route('closed-training-cases.email.send', $case), [
                'email_subject' => $draft['subject'],
                'email_body' => $draft['body'],
                'send_mode' => 'test',
                'test_recipient' => 'luman0599@gmail.com',
                'email_intent' => 'scheduled_notice',
                'email_recipients' => ['szkola@example.com'],
            ])
            ->assertRedirect(route('closed-training-cases.show', $case))
            ->assertSessionHas('success');

        Mail::assertSent(ClosedTrainingCaseSchoolMail::class, function (ClosedTrainingCaseSchoolMail $mail) {
            return $mail->hasTo('luman0599@gmail.com') && ! $mail->hasTo('szkola@example.com');
        });
        $this->assertSame(ClosedTrainingCase::STATUS_IN_PROGRESS, $case->fresh()->status);
        $testEvent = $case->events()->where('event_type', ClosedTrainingCaseEvent::TYPE_EMAIL_SENT)->latest('id')->first();
        $this->assertStringContainsString('E-mail testowy', (string) $testEvent?->body);

        $this->actingAs($user)
            ->post(route('closed-training-cases.email.send', $case), [
                'email_subject' => $draft['subject'],
                'email_body' => $draft['body'],
                'send_mode' => 'school',
                'email_recipients' => ['szkola@example.com'],
                'email_intent' => 'scheduled_notice',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case))
            ->assertSessionHas('success');

        $case->refresh();
        $this->assertSame(ClosedTrainingCase::STATUS_IN_PROGRESS, $case->status);
        $this->assertTrue(
            $case->events()
                ->where('event_type', ClosedTrainingCaseEvent::TYPE_EMAIL_SENT)
                ->where('body', 'like', 'E-mail: info o terminie szkolenia%')
                ->exists()
        );
    }

    public function test_operator_can_email_instructor_and_notify_on_school_send(): void
    {
        Mail::fake();

        $user = User::factory()->create(['is_active' => true]);
        $instructor = Instructor::query()->create([
            'title' => 'mgr',
            'first_name' => 'Jacek',
            'last_name' => 'Krzyzak',
            'email' => 'jacek.trener.'.uniqid('', true).'@example.com',
            'phone' => '502 145 752',
            'is_active' => true,
        ]);
        $case = $this->makeCase([
            'contact_name' => 'Anna Dyrektor',
            'contact_email' => 'szkola@example.com',
            'contact_phone' => '501 111 222',
            'institution' => 'SP Przykład',
            'status' => ClosedTrainingCase::STATUS_IN_PROGRESS,
            'instructor_id' => $instructor->id,
            'client_price' => '2500.00',
            'topic' => 'Zagrożenie przemocą i atak w szkole',
            'message' => 'Proszę o kontakt w sprawie terminu.',
            'preferred_format' => ClosedTrainingCase::FORMAT_ONLINE,
        ]);

        $this->actingAs($user)
            ->post(route('closed-training-cases.email.prepare-instructor', $case), [
                'share_contacts' => '0',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case).'#closed-training-case-email')
            ->assertSessionHas('email_draft');

        $draft = session('email_draft');
        $this->assertSame('instructor', $draft['send_mode']);
        $this->assertStringContainsString('Prośba o termin', $draft['subject']);
        $this->assertStringNotContainsString('2500', $draft['body']);
        $this->assertStringNotContainsString('szkola@example.com', $draft['body']);
        $this->assertStringContainsString('nie kontaktować się bezpośrednio ze szkołą', $draft['body']);

        $this->actingAs($user)
            ->post(route('closed-training-cases.email.send', $case), [
                'email_subject' => $draft['subject'],
                'email_body' => $draft['body'],
                'send_mode' => 'instructor',
                'email_intent' => 'instructor_brief',
                'share_contacts' => '0',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case))
            ->assertSessionHas('success');

        Mail::assertSent(ClosedTrainingCaseSchoolMail::class, function (ClosedTrainingCaseSchoolMail $mail) use ($instructor) {
            return $mail->hasTo(strtolower($instructor->email))
                && ! $mail->hasTo('szkola@example.com')
                && ! str_contains($mail->htmlBody, '2500');
        });

        $this->assertTrue(
            $case->events()
                ->where('event_type', ClosedTrainingCaseEvent::TYPE_EMAIL_SENT)
                ->where('body', 'like', 'E-mail do prowadzącego%')
                ->exists()
        );
        $this->assertSame(ClosedTrainingCase::STATUS_IN_PROGRESS, $case->fresh()->status);

        Mail::fake();

        $this->actingAs($user)
            ->post(route('closed-training-cases.email.send', $case), [
                'email_subject' => 'Propozycja dla szkoły',
                'email_body' => '<p>Koszt 2500 zł brutto.</p>',
                'send_mode' => 'school',
                'email_recipients' => ['szkola@example.com'],
                'email_intent' => 'general',
                'notify_instructor' => '1',
                'share_contacts' => '1',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case))
            ->assertSessionHas('success');

        Mail::assertSent(ClosedTrainingCaseSchoolMail::class, 2);
        Mail::assertSent(ClosedTrainingCaseSchoolMail::class, function (ClosedTrainingCaseSchoolMail $mail) {
            return $mail->hasTo('szkola@example.com') && str_contains($mail->htmlBody, '2500');
        });
        Mail::assertSent(ClosedTrainingCaseSchoolMail::class, function (ClosedTrainingCaseSchoolMail $mail) use ($instructor) {
            return $mail->hasTo(strtolower($instructor->email))
                && ! str_contains($mail->htmlBody, '2500')
                && str_contains($mail->htmlBody, 'szkola@example.com')
                && str_contains($mail->htmlBody, 'Anna Dyrektor');
        });
    }

    public function test_scheduled_onsite_notice_does_not_require_meeting_link(): void
    {
        Mail::fake();

        $user = User::factory()->create(['is_active' => true]);
        $case = $this->makeCase([
            'contact_email' => 'szkola@example.com',
            'status' => ClosedTrainingCase::STATUS_IN_PROGRESS,
        ]);
        $start = Carbon::parse('2026-11-05 09:00:00', 'Europe/Warsaw');
        $course = $this->makeCourse([
            'title' => 'Szkolenie stacjonarne RP',
            'type' => 'offline',
            'category' => 'closed',
            'start_date' => $start,
            'end_date' => $start->copy()->addHours(3),
            'closed_training_case_id' => $case->id,
        ]);
        $course->location()->create([
            'location_name' => 'Sala konferencyjna',
            'address' => 'ul. Szkolna 1',
            'postal_code' => '00-001',
            'post_office' => 'Warszawa',
            'country' => 'PL',
        ]);

        $this->actingAs($user)
            ->post(route('closed-training-cases.email.prepare-scheduled', $case))
            ->assertRedirect(route('closed-training-cases.show', $case).'#closed-training-case-email')
            ->assertSessionHas('email_draft');

        $draft = session('email_draft');
        $this->assertStringContainsString('potwierdzenie terminu', $draft['subject']);
        $this->assertStringContainsString('stacjonarnie', $draft['body']);
        $this->assertStringContainsString('Sala konferencyjna', $draft['body']);

        $this->actingAs($user)
            ->post(route('closed-training-cases.email.send', $case), [
                'email_subject' => $draft['subject'],
                'email_body' => $draft['body'],
                'send_mode' => 'school',
                'email_recipients' => ['szkola@example.com'],
                'email_intent' => 'scheduled_notice',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $this->assertSame(ClosedTrainingCase::STATUS_IN_PROGRESS, $case->fresh()->status);
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
                'instructor_fee_basis' => ClosedTrainingCase::FEE_BASIS_GROSS,
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
        $this->assertSame(ClosedTrainingCase::FEE_BASIS_GROSS, $case->instructor_fee_basis);
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
                'instructor_fee_basis' => ClosedTrainingCase::FEE_BASIS_NET,
                'instructor_fee_confirmed' => '1',
                'start_date' => '2026-11-10',
                'end_date' => '',
                'schedule_time_known' => '0',
            ])
            ->assertRedirect(route('closed-training-cases.show', $case));

        $case->refresh();
        $this->assertSame('2600.00', (string) $case->client_price);
        $this->assertNull($case->client_price_confirmed_at);
        $this->assertSame(ClosedTrainingCase::FEE_BASIS_NET, $case->instructor_fee_basis);
        $this->assertNotNull($case->instructor_fee_confirmed_at);
        $this->assertTrue(
            $case->events()->where('body', 'like', '%1 200 PLN brutto → 1 200 PLN netto%')->exists()
        );
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
                'instructor_fee_basis' => ClosedTrainingCase::FEE_BASIS_GROSS,
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
                'instructor_fee_basis' => ClosedTrainingCase::FEE_BASIS_GROSS,
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
                'instructor_fee_basis' => ClosedTrainingCase::FEE_BASIS_GROSS,
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

    public function test_sync_negotiation_from_linked_course_copies_schedule_and_instructor(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $caseInstructor = Instructor::query()->create([
            'first_name' => 'Sprawa',
            'last_name' => 'Trener',
            'email' => 'sprawa.'.uniqid().'@example.com',
            'is_active' => true,
        ]);
        $courseInstructor = Instructor::query()->create([
            'first_name' => 'Kurs',
            'last_name' => 'Trener',
            'email' => 'kurs.'.uniqid().'@example.com',
            'is_active' => true,
        ]);
        $case = $this->makeCase([
            'instructor_id' => $caseInstructor->id,
            'instructor_fee' => 900,
            'instructor_fee_confirmed_at' => now(),
            'start_date' => '2026-11-01 09:00:00',
            'end_date' => '2026-11-01 11:00:00',
            'schedule_time_known' => true,
        ]);
        $course = $this->makeCourse([
            'instructor_id' => $courseInstructor->id,
            'start_date' => '2026-12-15 14:00:00',
            'end_date' => '2026-12-15 16:00:00',
        ]);

        $this->actingAs($user)
            ->post(route('closed-training-cases.course.bind', $case), [
                'course_id' => $course->id,
                'confirm_mismatch' => '1',
            ])
            ->assertRedirect();

        $case->refresh();
        $this->assertTrue($case->linkedCourseScheduleMismatch());
        $this->assertTrue($case->linkedCourseInstructorMismatch());

        $this->actingAs($user)
            ->get(route('closed-training-cases.show', $case))
            ->assertOk()
            ->assertSee('Synchronizuj');

        $this->actingAs($user)
            ->post(route('closed-training-cases.course.sync-negotiation', $case))
            ->assertRedirect(route('closed-training-cases.show', $case));

        $case->refresh();
        $this->assertSame($courseInstructor->id, (int) $case->instructor_id);
        $this->assertSame('2026-12-15 14:00', $case->start_date?->format('Y-m-d H:i'));
        $this->assertSame('2026-12-15 16:00', $case->end_date?->format('Y-m-d H:i'));
        $this->assertTrue((bool) $case->schedule_time_known);
        $this->assertNull($case->instructor_fee_confirmed_at);
        $this->assertFalse($case->linkedCourseScheduleMismatch());
        $this->assertFalse($case->linkedCourseInstructorMismatch());
        $this->assertTrue(
            $case->events()->where('body', 'like', 'Zsynchronizowano ustalenia ze szkoleniem%')->exists()
        );
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
            'inquired_at' => now()->subDay(),
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
