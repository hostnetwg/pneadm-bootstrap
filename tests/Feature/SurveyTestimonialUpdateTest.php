<?php

namespace Tests\Feature;

use App\Models\SurveyTestimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyTestimonialUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_testimonial_quote_and_author_fields(): void
    {
        $admin = User::factory()->create();
        $testimonial = SurveyTestimonial::query()->create([
            'author_name' => 'Anna Nowak',
            'author_role' => 'Nauczycielka',
            'author_city' => 'Kraków',
            'quote' => 'Szkolenie bylo swietne.',
            'rating' => 5,
            'publish_consent' => true,
            'is_published' => false,
        ]);

        $response = $this->actingAs($admin)->put(route('surveys.testimonials.update', $testimonial), [
            'quote' => 'Szkolenie było świetne.',
            'author_name' => 'Anna Nowak',
            'author_role' => 'Nauczycielka',
            'author_city' => 'Kraków',
            'rating' => 5,
        ]);

        $response->assertRedirect(route('surveys.testimonials.index'));
        $this->assertDatabaseHas('survey_testimonials', [
            'id' => $testimonial->id,
            'quote' => 'Szkolenie było świetne.',
            'author_name' => 'Anna Nowak',
        ]);
    }

    public function test_publish_redirects_to_testimonials_index_not_back(): void
    {
        $admin = User::factory()->create();
        $testimonial = SurveyTestimonial::query()->create([
            'author_name' => 'Anna Nowak',
            'quote' => 'Opinia',
            'publish_consent' => true,
            'is_published' => false,
        ]);

        $response = $this->actingAs($admin)
            ->from('https://adm.pnedu.pl/certificates/pdf-generation-status-any')
            ->post(route('surveys.testimonials.publish', $testimonial));

        $response->assertRedirect(route('surveys.testimonials.index'));
        $this->assertTrue($testimonial->fresh()->is_published);
    }

    public function test_quote_is_required_when_updating_testimonial(): void
    {
        $admin = User::factory()->create();
        $testimonial = SurveyTestimonial::query()->create([
            'author_name' => 'Anna Nowak',
            'quote' => 'Oryginalna treść',
            'publish_consent' => true,
            'is_published' => false,
        ]);

        $response = $this->actingAs($admin)->from(route('surveys.testimonials.index'))
            ->put(route('surveys.testimonials.update', $testimonial), [
                'quote' => '',
                'author_name' => 'Anna Nowak',
            ]);

        $response->assertRedirect(route('surveys.testimonials.index'));
        $response->assertSessionHasErrors('quote');
        $this->assertDatabaseHas('survey_testimonials', [
            'id' => $testimonial->id,
            'quote' => 'Oryginalna treść',
        ]);
    }
}
