<?php

namespace Tests\Feature;

use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\Instructor;
use App\Models\User;
use App\Services\Certificate\CourseSeriesCertificateSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseSeriesCertificateSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const TIK_FORMAT = '{nr}/{course_id}/{year}/TIK';

    private int $outputBufferLevel = 0;

    private User $operator;

    private CourseSeries $tikSeries;

    private CourseSeries $otherSeries;

    private CertificateTemplate $tikTemplate;

    private CertificateTemplate $historicalTemplate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->outputBufferLevel = ob_get_level();

        $this->operator = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->tikTemplate = $this->createTemplate(5, 'Szablon Domyślny TIK', 'default-tik');
        $this->historicalTemplate = $this->createTemplate(6, 'Szablon Domyślny TIK przed akredytacją', 'tik-przed-akredytacja');

        $this->tikSeries = CourseSeries::create([
            'name' => 'TIK w pracy NAUCZYCIELA',
            'slug' => 'tik-w-pracy-nauczyciela',
            'is_active' => true,
            'sort_order' => 1,
            'certificate_format' => self::TIK_FORMAT,
            'certificate_template_id' => $this->tikTemplate->id,
        ]);

        $this->otherSeries = CourseSeries::create([
            'name' => 'Inna seria',
            'slug' => 'inna-seria',
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }
        parent::tearDown();
    }

    public function test_series_edit_form_shows_certificate_default_fields(): void
    {
        $this->actingAs($this->operator)
            ->get(route('courses.series.edit', $this->tikSeries))
            ->assertOk()
            ->assertSee('Domyślne zaświadczenia dla nowych szkoleń', false)
            ->assertSee(self::TIK_FORMAT, false)
            ->assertSee('Szablon Domyślny TIK', false);
    }

    public function test_series_update_saves_certificate_defaults_without_touching_existing_courses(): void
    {
        $existing = $this->createCourse(['title' => 'Już w serii']);
        $this->tikSeries->courses()->attach($existing->id, ['order_in_series' => 1]);

        $this->actingAs($this->operator)
            ->put(route('courses.series.update', $this->tikSeries), [
                'name' => $this->tikSeries->name,
                'sort_order' => $this->tikSeries->sort_order,
                'is_active' => 1,
                'certificate_format' => '{nr}/{course_id}/{year}/ADM',
                'certificate_template_id' => $this->historicalTemplate->id,
            ])
            ->assertRedirect(route('courses.series.index'));

        $this->tikSeries->refresh();
        $existing->refresh();

        $this->assertSame('{nr}/{course_id}/{year}/ADM', $this->tikSeries->certificate_format);
        $this->assertSame($this->historicalTemplate->id, $this->tikSeries->certificate_template_id);
        $this->assertSame(CourseSeriesCertificateSettings::DEFAULT_PNE_FORMAT, $existing->certificate_format);
        $this->assertNull($existing->certificate_template_id);
    }

    public function test_adding_course_with_default_pne_format_copies_series_defaults(): void
    {
        $course = $this->createCourse();

        $this->updateSeriesCourses($this->tikSeries, [$course->id]);

        $course->refresh();

        $this->assertSame(self::TIK_FORMAT, $course->certificate_format);
        $this->assertSame($this->tikTemplate->id, $course->certificate_template_id);
        $this->assertTrue($this->tikSeries->courses()->where('courses.id', $course->id)->exists());
    }

    public function test_existing_series_member_with_pne_is_not_backfilled_on_save(): void
    {
        $existing = $this->createCourse(['title' => 'Już w serii']);
        $this->tikSeries->courses()->attach($existing->id, ['order_in_series' => 1]);

        $new = $this->createCourse(['title' => 'Nowy']);

        $this->updateSeriesCourses($this->tikSeries, [$existing->id, $new->id]);

        $existing->refresh();
        $new->refresh();

        $this->assertSame(CourseSeriesCertificateSettings::DEFAULT_PNE_FORMAT, $existing->certificate_format);
        $this->assertNull($existing->certificate_template_id);
        $this->assertSame(self::TIK_FORMAT, $new->certificate_format);
        $this->assertSame($this->tikTemplate->id, $new->certificate_template_id);
    }

    public function test_custom_format_is_not_overwritten_when_adding_to_series(): void
    {
        $course = $this->createCourse([
            'certificate_format' => '{nr}/{course_id}/{year}/CUSTOM',
        ]);

        $this->updateSeriesCourses($this->tikSeries, [$course->id]);

        $course->refresh();

        $this->assertSame('{nr}/{course_id}/{year}/CUSTOM', $course->certificate_format);
        $this->assertNull($course->certificate_template_id);
    }

    public function test_historical_pre_accreditation_template_is_not_changed_on_attach_or_detach(): void
    {
        $course = $this->createCourse([
            'certificate_format' => self::TIK_FORMAT,
            'certificate_template_id' => $this->historicalTemplate->id,
        ]);

        $this->updateSeriesCourses($this->tikSeries, [$course->id]);
        $course->refresh();
        $this->assertSame(self::TIK_FORMAT, $course->certificate_format);
        $this->assertSame($this->historicalTemplate->id, $course->certificate_template_id);

        $this->updateSeriesCourses($this->tikSeries, []);
        $course->refresh();
        $this->assertSame(self::TIK_FORMAT, $course->certificate_format);
        $this->assertSame($this->historicalTemplate->id, $course->certificate_template_id);
        $this->assertFalse($this->tikSeries->courses()->where('courses.id', $course->id)->exists());
    }

    public function test_pne_format_with_other_template_is_not_changed_on_attach(): void
    {
        $course = $this->createCourse([
            'certificate_format' => CourseSeriesCertificateSettings::DEFAULT_PNE_FORMAT,
            'certificate_template_id' => $this->historicalTemplate->id,
        ]);

        $this->updateSeriesCourses($this->tikSeries, [$course->id]);
        $course->refresh();

        $this->assertSame(CourseSeriesCertificateSettings::DEFAULT_PNE_FORMAT, $course->certificate_format);
        $this->assertSame($this->historicalTemplate->id, $course->certificate_template_id);
        $this->assertTrue($this->tikSeries->courses()->where('courses.id', $course->id)->exists());
    }

    public function test_removing_from_series_reverts_auto_applied_settings(): void
    {
        $course = $this->createCourse();

        $this->updateSeriesCourses($this->tikSeries, [$course->id]);
        $course->refresh();
        $this->assertSame(self::TIK_FORMAT, $course->certificate_format);

        $this->updateSeriesCourses($this->tikSeries, []);
        $course->refresh();

        $this->assertSame(CourseSeriesCertificateSettings::DEFAULT_PNE_FORMAT, $course->certificate_format);
        $this->assertNull($course->certificate_template_id);
    }

    public function test_manual_edit_after_attach_is_kept_on_detach(): void
    {
        $course = $this->createCourse();
        $this->updateSeriesCourses($this->tikSeries, [$course->id]);

        $course->update([
            'certificate_format' => '{nr}/{course_id}/{year}/TIK-RECZNE',
        ]);

        $this->updateSeriesCourses($this->tikSeries, []);
        $course->refresh();

        $this->assertSame('{nr}/{course_id}/{year}/TIK-RECZNE', $course->certificate_format);
        $this->assertSame($this->tikTemplate->id, $course->certificate_template_id);
    }

    public function test_series_without_defaults_does_not_change_certificate_settings(): void
    {
        $course = $this->createCourse();

        $this->updateSeriesCourses($this->otherSeries, [$course->id]);

        $course->refresh();

        $this->assertSame(CourseSeriesCertificateSettings::DEFAULT_PNE_FORMAT, $course->certificate_format);
        $this->assertNull($course->certificate_template_id);
        $this->assertTrue($this->otherSeries->courses()->where('courses.id', $course->id)->exists());
    }

    public function test_reorder_only_does_not_change_certificate_settings(): void
    {
        $first = $this->createCourse(['title' => 'Pierwszy']);
        $second = $this->createCourse(['title' => 'Drugi']);

        $this->tikSeries->courses()->attach([
            $first->id => ['order_in_series' => 1],
            $second->id => ['order_in_series' => 2],
        ]);

        $this->updateSeriesCourses($this->tikSeries, [$second->id, $first->id]);

        $first->refresh();
        $second->refresh();

        $this->assertSame(CourseSeriesCertificateSettings::DEFAULT_PNE_FORMAT, $first->certificate_format);
        $this->assertSame(CourseSeriesCertificateSettings::DEFAULT_PNE_FORMAT, $second->certificate_format);
        $this->assertSame(2, (int) $this->tikSeries->courses()->where('courses.id', $first->id)->first()->pivot->order_in_series);
        $this->assertSame(1, (int) $this->tikSeries->courses()->where('courses.id', $second->id)->first()->pivot->order_in_series);
    }

    /**
     * @param  array<int>  $courseIds
     */
    private function updateSeriesCourses(CourseSeries $series, array $courseIds): void
    {
        $payload = $courseIds === []
            ? ['courses' => []]
            : ['courses' => $courseIds];

        $this->actingAs($this->operator)
            ->put(route('courses.series.update-courses', $series), $payload)
            ->assertRedirect(route('courses.series.show', $series));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createCourse(array $overrides = []): Course
    {
        $instructor = Instructor::create([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'title' => 'mgr',
            'email' => 'jan.kowalski.'.uniqid('', true).'@example.test',
            'is_active' => true,
        ]);

        return Course::create(array_merge([
            'title' => 'Szkolenie testowe',
            'description' => 'Test',
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(7)->addHours(4),
            'is_paid' => true,
            'type' => 'online',
            'category' => 'open',
            'instructor_id' => $instructor->id,
            'is_active' => true,
            'certificate_format' => CourseSeriesCertificateSettings::DEFAULT_PNE_FORMAT,
        ], $overrides));
    }

    private function createTemplate(int $id, string $name, string $slug): CertificateTemplate
    {
        $template = new CertificateTemplate;
        $template->id = $id;
        $template->forceFill([
            'name' => $name,
            'slug' => $slug,
            'config' => [],
            'is_active' => true,
        ]);
        $template->save();

        return $template->fresh();
    }
}
