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

    public function test_publish_via_ajax_returns_json_without_redirect(): void
    {
        $admin = User::factory()->create();
        $testimonial = SurveyTestimonial::query()->create([
            'author_name' => 'Anna Nowak',
            'quote' => 'Opinia',
            'publish_consent' => true,
            'is_published' => false,
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('surveys.testimonials.publish', $testimonial));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('testimonial.is_published', true);
        $this->assertTrue($testimonial->fresh()->is_published);
    }

    public function test_feature_via_ajax_returns_json(): void
    {
        $admin = User::factory()->create();
        $testimonial = SurveyTestimonial::query()->create([
            'author_name' => 'Anna Nowak',
            'quote' => 'Opinia',
            'publish_consent' => true,
            'is_published' => true,
            'is_featured' => false,
            'display_order' => SurveyTestimonial::DISPLAY_ORDER_UNFEATURED,
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('surveys.testimonials.feature', $testimonial));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('testimonial.is_featured', true);
        $this->assertTrue($testimonial->fresh()->is_featured);
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

    public function test_admin_can_feature_published_testimonial(): void
    {
        $admin = User::factory()->create();
        $testimonial = SurveyTestimonial::query()->create([
            'author_name' => 'Anna Nowak',
            'quote' => 'Opinia',
            'publish_consent' => true,
            'is_published' => true,
            'is_featured' => false,
            'display_order' => SurveyTestimonial::DISPLAY_ORDER_UNFEATURED,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('surveys.testimonials.feature', $testimonial));

        $response->assertRedirect(route('surveys.testimonials.index'));
        $fresh = $testimonial->fresh();
        $this->assertTrue($fresh->is_featured);
        $this->assertLessThan(SurveyTestimonial::DISPLAY_ORDER_UNFEATURED, (int) $fresh->display_order);
    }

    public function test_feature_requires_published_testimonial(): void
    {
        $admin = User::factory()->create();
        $testimonial = SurveyTestimonial::query()->create([
            'author_name' => 'Anna Nowak',
            'quote' => 'Opinia',
            'publish_consent' => true,
            'is_published' => false,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('surveys.testimonials.feature', $testimonial));

        $response->assertRedirect(route('surveys.testimonials.index'));
        $response->assertSessionHas('error');
        $this->assertFalse($testimonial->fresh()->is_featured);
    }

    public function test_feature_respects_soft_limit(): void
    {
        $admin = User::factory()->create();

        for ($i = 1; $i <= SurveyTestimonial::FEATURED_SOFT_LIMIT; $i++) {
            SurveyTestimonial::query()->create([
                'author_name' => "Autor {$i}",
                'quote' => "Opinia {$i}",
                'publish_consent' => true,
                'is_published' => true,
                'is_featured' => true,
                'display_order' => $i * 10,
            ]);
        }

        $extra = SurveyTestimonial::query()->create([
            'author_name' => 'Extra',
            'quote' => 'Nadmiar',
            'publish_consent' => true,
            'is_published' => true,
            'is_featured' => false,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('surveys.testimonials.feature', $extra));

        $response->assertRedirect(route('surveys.testimonials.index'));
        $response->assertSessionHas('error');
        $this->assertFalse($extra->fresh()->is_featured);
    }

    public function test_unpublish_clears_featured_flag(): void
    {
        $admin = User::factory()->create();
        $testimonial = SurveyTestimonial::query()->create([
            'author_name' => 'Anna Nowak',
            'quote' => 'Opinia',
            'publish_consent' => true,
            'is_published' => true,
            'is_featured' => true,
            'display_order' => 10,
        ]);

        $this->actingAs($admin)
            ->post(route('surveys.testimonials.unpublish', $testimonial));

        $fresh = $testimonial->fresh();
        $this->assertFalse($fresh->is_published);
        $this->assertFalse($fresh->is_featured);
        $this->assertSame(SurveyTestimonial::DISPLAY_ORDER_UNFEATURED, (int) $fresh->display_order);
    }

    public function test_destroy_via_ajax_returns_json_and_deletes_testimonial(): void
    {
        $admin = User::factory()->create();
        $testimonial = SurveyTestimonial::query()->create([
            'author_name' => 'Anna Nowak',
            'quote' => 'Opinia',
            'publish_consent' => true,
            'is_published' => true,
            'is_featured' => true,
            'display_order' => 10,
        ]);

        $response = $this->actingAs($admin)
            ->deleteJson(route('surveys.testimonials.destroy', $testimonial));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Rekomendacja usunięta.')
            ->assertJsonPath('featured_count', 0);
        $this->assertDatabaseMissing('survey_testimonials', ['id' => $testimonial->id]);
    }
}
