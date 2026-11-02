<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\CoursePriceVariant;
use App\Services\ClosedTrainingCaseScheduledEmailBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClosedTrainingCaseScheduledEmailBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_form_notice_is_inserted_after_the_planned_sentence_and_removed_again(): void
    {
        $builder = app(ClosedTrainingCaseScheduledEmailBuilder::class);
        $html = '<p>Dzień dobry,</p>'
            .'<p>szkolenie dla Państwa placówki jest już zaplanowane.<br>'
            .'Poniżej przesyłam potwierdzenie ustaleń.</p>';
        $block = '<div data-order-form-notice="1"><p>Formularz zamówienia.</p></div>';

        $withNotice = $builder->insertOrderFormNotice($html, $block);

        $this->assertStringContainsString(
            'szkolenie dla Państwa placówki jest już zaplanowane.</p>'.$block.'<p><br></p><p>Poniżej przesyłam potwierdzenie ustaleń.</p>',
            $withNotice
        );
        $this->assertSame($withNotice, $builder->insertOrderFormNotice($withNotice, $block));
        $this->assertSame($html, $builder->removeOrderFormNotice($withNotice));
    }

    public function test_order_form_notice_uses_the_only_active_price_variant(): void
    {
        config(['services.pnedu_frontend_url' => 'https://pnedu.pl']);

        $course = Course::query()->create([
            'title' => 'Szkolenie rady',
            'description' => 'Opis',
            'start_date' => now()->addDay(),
            'end_date' => now()->addDay()->addHours(2),
            'is_paid' => true,
            'type' => 'online',
            'category' => 'closed',
            'is_active' => true,
            'certificate_format' => '{nr}/PNE',
        ]);
        CoursePriceVariant::query()->create([
            'course_id' => $course->id,
            'name' => 'Ukryty',
            'is_active' => false,
            'price' => 100,
            'promotion_type' => 'disabled',
            'access_type' => '1',
        ]);
        $active = CoursePriceVariant::query()->create([
            'course_id' => $course->id,
            'name' => 'Rada pedagogiczna',
            'is_active' => true,
            'price' => 2500,
            'promotion_type' => 'disabled',
            'access_type' => '1',
        ]);

        $builder = app(ClosedTrainingCaseScheduledEmailBuilder::class);
        $course->load('activePriceVariants');

        $this->assertTrue($builder->hasActivePriceVariant($course));
        $html = $builder->orderFormNoticeHtml($course);
        $this->assertStringContainsString(
            'https://pnedu.pl/courses/'.$course->id.'/order-form?price_variant_id='.$active->id,
            $html
        );
        $this->assertStringContainsString('W celu oficjalnego potwierdzenia terminu', $html);
        $this->assertStringContainsString('listę obecności', $html);
        $this->assertStringContainsString('14-dniowym terminem płatności', $html);
    }
}
