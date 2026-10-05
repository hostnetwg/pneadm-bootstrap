<?php

namespace Tests\Unit\GrowthOS;

use App\Models\Course;
use App\Models\CoursePriceVariant;
use App\Models\Instructor;
use App\Services\GrowthOS\PaidCourseOfferBuilder;
use App\Services\PriceOmnibusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PaidCourseOfferBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_filters_sorts_limits_and_formats_prices(): void
    {
        config()->set('marketing.pnedu_public_url', 'https://pnedu.pl');

        $instructor = Instructor::query()->create([
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'email' => 'anna.nowak.offer-test@example.com',
            'is_active' => true,
        ]);

        $eligible = $this->course('A — najbliższy', now()->addDays(2), true, true, true, $instructor->id);
        $this->variant($eligible, 299, null);
        $this->variant($eligible, 199, 149);

        $second = $this->course('B — drugi', now()->addDays(5), true, true, true, $instructor->id);
        $this->variant($second, 399, null);

        $this->course('C — ukryty', now()->addDays(3), true, false, true, $instructor->id);
        $this->course('D — darmowy', now()->addDays(3), false, true, true, $instructor->id);
        $this->course('E — nieaktywny', now()->addDays(3), true, true, false, $instructor->id);
        $past = $this->course('F — przeszły', now()->subDays(3), true, true, true, $instructor->id);
        $past->end_date = now()->subDay();
        $past->save();

        $noPrice = $this->course('G — bez ceny', now()->addDays(4), true, true, true, $instructor->id);

        for ($i = 1; $i <= 6; $i++) {
            $course = $this->course("H{$i}", now()->addDays(10 + $i), true, true, true, $instructor->id);
            $this->variant($course, 100 + $i, null);
        }

        $omnibus = Mockery::mock(PriceOmnibusService::class);
        $omnibus->shouldReceive('lowestFor')->andReturnUsing(function (CoursePriceVariant $variant): ?string {
            return $variant->isPromotionActive() ? '180.00' : null;
        });
        $this->app->instance(PriceOmnibusService::class, $omnibus);

        $snapshot = app(PaidCourseOfferBuilder::class)->snapshot(42);

        $this->assertCount(5, $snapshot['courses']);
        $this->assertSame('A — najbliższy', $snapshot['courses'][0]['title']);
        $this->assertStringStartsWith('od ', $snapshot['courses'][0]['price_label']);
        $this->assertStringContainsString('149,00 zł', $snapshot['courses'][0]['price_label']);
        $this->assertSame('Najniższa cena z 30 dni przed obniżką: 180,00 zł brutto', $snapshot['courses'][0]['omnibus_label']);
        $this->assertStringContainsString('utm_campaign=growth-42', $snapshot['courses'][0]['url']);
        $this->assertStringContainsString('utm_content=paid-course-'.$eligible->id, $snapshot['courses'][0]['url']);
        $this->assertSame('B — drugi', $snapshot['courses'][1]['title']);
        $this->assertStringStartsWith('399,00 zł', $snapshot['courses'][1]['price_label']);
        $this->assertStringNotContainsString('od ', $snapshot['courses'][1]['price_label']);

        $titles = array_column($snapshot['courses'], 'title');
        $this->assertNotContains('C — ukryty', $titles);
        $this->assertNotContains('D — darmowy', $titles);
        $this->assertNotContains('E — nieaktywny', $titles);
        $this->assertNotContains('F — przeszły', $titles);
        $this->assertNotContains('G — bez ceny', $titles);
        $this->assertNotContains('H6', $titles);
    }

    private function course(
        string $title,
        mixed $start,
        bool $paid,
        bool $show,
        bool $active,
        int $instructorId,
    ): Course {
        return Course::query()->create([
            'title' => $title,
            'description' => 'Test',
            'start_date' => $start,
            'end_date' => now()->parse($start)->addHours(3),
            'is_paid' => $paid,
            'type' => 'online',
            'category' => 'open',
            'instructor_id' => $instructorId,
            'is_active' => $active,
            'show_on_pnedu' => $show,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
        ]);
    }

    private function variant(Course $course, float $price, ?float $promo): CoursePriceVariant
    {
        return CoursePriceVariant::query()->create([
            'course_id' => $course->id,
            'name' => 'Standard',
            'is_active' => true,
            'price' => $price,
            'is_promotion' => $promo !== null,
            'promotion_price' => $promo,
            'promotion_type' => $promo !== null ? 'unlimited' : 'disabled',
            'access_type' => '1',
        ]);
    }
}
