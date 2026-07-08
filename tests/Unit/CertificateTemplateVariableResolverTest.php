<?php

namespace Tests\Unit;

use App\Services\Certificate\CertificateTemplateVariableResolver;
use PHPUnit\Framework\TestCase;

class CertificateTemplateVariableResolverTest extends TestCase
{
    private CertificateTemplateVariableResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new CertificateTemplateVariableResolver;
    }

    public function test_null_event_text_uses_default_template_with_variables(): void
    {
        $course = (object) [
            'start_date' => '2026-01-15',
            'end_date' => null,
        ];

        $result = $this->resolver->resolveEventText(null, [
            'course' => $course,
            'effective_completion_date' => '2026-07-01',
            'duration_minutes' => 90,
            'show_duration' => true,
        ]);

        $this->assertSame('zorganizowanym w dniu 01.07.2026 r. w wymiarze 90 minut, przez', $result);
    }

    public function test_empty_event_text_returns_null(): void
    {
        $result = $this->resolver->resolveEventText('', [
            'course' => (object) ['start_date' => '2026-01-15'],
        ]);

        $this->assertNull($result);
    }

    public function test_plain_event_text_without_variables_is_returned_as_is(): void
    {
        $result = $this->resolver->resolveEventText('zorganizowanym w dniu', [
            'course' => (object) ['start_date' => '2026-07-01'],
            'duration_minutes' => 60,
            'show_duration' => true,
        ]);

        $this->assertSame('zorganizowanym w dniu', $result);
    }

    public function test_variable_template_uses_only_user_defined_text(): void
    {
        $course = (object) [
            'start_date' => '2026-01-10',
            'end_date' => '2026-01-12',
        ];

        $result = $this->resolver->resolveEventText(
            'które odbyło się {data_rozpoczecia}–{data_konca}',
            [
                'course' => $course,
                'effective_completion_date' => '2026-07-01',
                'duration_minutes' => 0,
                'show_duration' => false,
            ]
        );

        $this->assertSame('które odbyło się 10.01.2026–12.01.2026', $result);
    }

    public function test_duration_variable_is_empty_when_show_duration_disabled(): void
    {
        $result = $this->resolver->substitute('czas: {czas_trwania}koniec', [
            'course' => (object) ['start_date' => '2026-07-01'],
            'duration_minutes' => 45,
            'show_duration' => false,
        ]);

        $this->assertSame('czas: koniec', $result);
    }

    public function test_form_event_text_value_for_null_and_missing_key(): void
    {
        $this->assertSame(
            CertificateTemplateVariableResolver::DEFAULT_EVENT_TEXT,
            CertificateTemplateVariableResolver::formEventTextValue([])
        );

        $this->assertSame(
            CertificateTemplateVariableResolver::DEFAULT_EVENT_TEXT,
            CertificateTemplateVariableResolver::formEventTextValue(['event_text' => null])
        );

        $this->assertSame(
            'zorganizowane w dniu',
            CertificateTemplateVariableResolver::formEventTextValue(['event_text' => 'zorganizowane w dniu'])
        );

        $this->assertSame(
            '',
            CertificateTemplateVariableResolver::formEventTextValue(['event_text' => ''])
        );
    }
}
