<?php

namespace Tests\Feature;

use App\Models\TrainingOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrainingOfferAdminTest extends TestCase
{
    use RefreshDatabase;

    private ?TrainingOffer $offer = null;

    protected function tearDown(): void
    {
        if ($this->offer) {
            $this->offer->forceDelete();
        }

        parent::tearDown();
    }

    public function test_admin_can_view_training_offer_index_and_show_page(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $slug = 'test-oferta-'.Str::lower(Str::random(8));

        $this->offer = TrainingOffer::create([
            'title' => 'Testowa oferta rady pedagogicznej',
            'slug' => $slug,
            'summary' => 'Krótki opis testowej oferty.',
            'price_mode' => TrainingOffer::PRICE_MODE_INDIVIDUAL,
            'default_course_category' => TrainingOffer::COURSE_CATEGORY_CLOSED,
            'is_active' => true,
            'show_on_pnedu' => true,
            'featured_on_homepage' => true,
        ]);

        $this->actingAs($user)
            ->get(route('training-offers.index'))
            ->assertOk()
            ->assertSee('Testowa oferta rady pedagogicznej')
            ->assertSee('Strona główna');

        $this->actingAs($user)
            ->get(route('training-offers.show', $this->offer))
            ->assertOk()
            ->assertSee('Cena ustalana indywidualnie')
            ->assertSee('Strona główna');

        $this->actingAs($user)
            ->get(route('training-offers.edit', $this->offer))
            ->assertOk()
            ->assertSee('name="featured_on_homepage"', false)
            ->assertSee('Wyróżnij na stronie głównej');
    }

    public function test_admin_can_open_create_course_form_prefilled_from_offer(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->offer = TrainingOffer::create([
            'title' => 'Oferta do utworzenia szkolenia',
            'slug' => 'oferta-do-utworzenia-'.Str::lower(Str::random(6)),
            'summary' => 'Podsumowanie oferty do szkolenia.',
            'description_html' => '<p>Pełny opis oferty.</p>',
            'scope' => "1. Temat pierwszy\n2. Temat drugi",
            'price_mode' => TrainingOffer::PRICE_MODE_INDIVIDUAL,
            'default_course_category' => TrainingOffer::COURSE_CATEGORY_CLOSED,
            'internal_notes' => 'Notatka wewnętrzna z oferty.',
            'is_active' => true,
            'show_on_pnedu' => true,
        ]);

        $this->actingAs($user)
            ->get(route('training-offers.create-course', $this->offer))
            ->assertOk()
            ->assertSee('Tworzenie szkolenia z oferty')
            ->assertSee('Oferta do utworzenia szkolenia')
            ->assertSee('Podsumowanie oferty do szkolenia.')
            ->assertSee('Notatka wewnętrzna z oferty.')
            ->assertSee('name="training_offer_id"', false)
            ->assertSee('value="'.$this->offer->id.'"', false);
    }

    public function test_storing_course_from_offer_keeps_training_offer_id(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->offer = TrainingOffer::create([
            'title' => 'Oferta źródłowa szkolenia',
            'slug' => 'oferta-zrodlowa-'.Str::lower(Str::random(6)),
            'summary' => 'Podsumowanie źródłowe.',
            'price_mode' => TrainingOffer::PRICE_MODE_INDIVIDUAL,
            'default_course_category' => TrainingOffer::COURSE_CATEGORY_CLOSED,
            'is_active' => true,
            'show_on_pnedu' => true,
        ]);

        $response = $this->actingAs($user)->post(route('courses.store'), [
            'title' => 'Szkolenie z oferty',
            'description' => 'Zakres ze szkolenia',
            'offer_summary' => 'Podsumowanie źródłowe.',
            'offer_description_html' => '<p>Opis</p>',
            'start_date' => now()->addDays(7)->format('Y-m-d\TH:i'),
            'end_date' => now()->addDays(7)->addHours(2)->format('Y-m-d\TH:i'),
            'is_paid' => '1',
            'type' => 'online',
            'category' => 'closed',
            'training_offer_id' => $this->offer->id,
            'show_on_pnedu' => '0',
            'platform' => 'ClickMeeting',
            'meeting_link' => 'https://example.com/meeting',
            'save_action' => 'close',
        ]);

        $response->assertRedirect(route('courses.index'));

        $this->assertDatabaseHas('courses', [
            'title' => 'Szkolenie z oferty',
            'training_offer_id' => $this->offer->id,
            'category' => 'closed',
            'is_paid' => 1,
            'show_on_pnedu' => 0,
        ]);
    }
}
