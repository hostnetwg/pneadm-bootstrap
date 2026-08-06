<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\DebtCase;
use App\Models\DebtCollectionSetting;
use App\Models\FormOrder;
use App\Models\Instructor;
use App\Services\DebtReminderTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtReminderTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_reminder_and_dunning_templates_with_case_data(): void
    {
        $instructor = Instructor::create([
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'email' => 'anna.nowak@example.test',
            'is_active' => true,
        ]);
        $start = now()->setTimezone(config('app.timezone'))->setTime(9, 30);
        $course = Course::create([
            'title' => 'AI w edukacji',
            'description' => 'Test',
            'start_date' => $start,
            'end_date' => $start->copy()->addHours(4),
            'instructor_id' => $instructor->id,
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'is_active' => true,
            'certificate_format' => '{nr}/{course_id}/{year}/PNE',
        ]);

        $order = FormOrder::create([
            'product_id' => $course->id,
            'product_name' => 'AI w edukacji',
            'product_price' => 499.5,
            'order_date' => now()->subDays(30),
            'invoice_number' => '88/8/2026',
            'orderer_email' => 'a@example.test',
        ]);
        $order->setRelation('course', $course->setRelation('instructor', $instructor));

        $case = DebtCase::create([
            'form_order_id' => $order->id,
            'status' => DebtCase::STATUS_OPEN,
            'invoice_number' => '88/8/2026',
            'amount_gross' => 499.5,
            'due_date' => now()->subDays(5),
            'opened_at' => now(),
        ]);
        $case->setRelation('formOrder', $order);

        config(['services.ifirma.bank_account' => '12 3456 7890 1234 5678 9012 3456']);
        DebtCollectionSetting::query()->updateOrCreate(
            ['id' => DebtCollectionSetting::SINGLETON_ID],
            ['contact_phone' => '+48 600 100 200']
        );
        DebtCollectionSetting::forgetSettingsCache();

        $service = app(DebtReminderTemplateService::class);
        $reminder = $service->build($case, DebtReminderTemplateService::TEMPLATE_REMINDER);
        $dunning = $service->build($case, DebtReminderTemplateService::TEMPLATE_DUNNING);

        $this->assertStringContainsString('88/8/2026', $reminder['subject']);
        $this->assertStringContainsString('SZKOLENIE: AI w edukacji', $reminder['subject']);
        $this->assertStringContainsString('499,50', $reminder['body']);
        $this->assertStringContainsString('Temat: AI w edukacji', $reminder['body']);
        $this->assertStringContainsString('Data startu: '.$start->format('d.m.Y H:i'), $reminder['body']);
        $this->assertStringContainsString('Prowadzący: Anna Nowak', $reminder['body']);
        $this->assertStringContainsString('12 3456 7890 1234 5678 9012 3456', $reminder['body']);
        $this->assertStringContainsString('tel. +48 600 100 200', $reminder['body']);
        $this->assertStringContainsString('Ponaglenie', $dunning['subject']);
        $this->assertStringContainsString('SZKOLENIE: AI w edukacji', $dunning['subject']);
        $this->assertStringContainsString('nieuregulowana', $dunning['body']);
        $this->assertStringContainsString('Prowadzący: Anna Nowak', $dunning['body']);
        $this->assertStringContainsString('tel. +48 600 100 200', $dunning['body']);
        $this->assertTrue($service->canAttachIfirmaPdf($case));
        $this->assertSame('88_8_2026', $service->ifirmaPdfLookupKey($case));
    }
}
