<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseSurveyLink;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveySetting;
use App\Models\SurveyTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tworzy natywną ankietę (Survey + pytania ze szablonu) i wiąże z CourseSurveyLink.
 */
class NativeSurveyProvisioner
{
    public function provisionForLink(CourseSurveyLink $link, ?SurveyTemplate $template = null): Survey
    {
        if ($link->survey_id) {
            $existing = Survey::query()->with('questions')->find($link->survey_id);
            if ($existing) {
                return $existing;
            }
        }

        $template ??= $this->resolveTemplate($link);
        if (! $template) {
            throw new \RuntimeException('Brak aktywnego szablonu ankiety. Ustaw szablon domyślny w Ustawienia → Ankiety.');
        }

        $template->loadMissing('questions');
        $course = $link->course ?? Course::query()->findOrFail($link->course_id);
        $settings = SurveySetting::getSettings();

        return DB::transaction(function () use ($link, $template, $course, $settings) {
            $autoTitle = $this->defaultNativeSurveyTitle($course);
            // Wzorzec jak przy imporcie CSV; opcjonalny tytuł z formularza nadpisuje tylko gdy podany.
            $surveyTitle = filled($link->title) ? (string) $link->title : $autoTitle;

            $survey = Survey::create([
                'course_id' => $course->id,
                'instructor_id' => $course->instructor_id,
                'survey_template_id' => $template->id,
                'title' => $surveyTitle,
                'description' => 'Ankieta natywna (pnedu.pl)',
                'imported_at' => now(),
                'imported_by' => Auth::id() ?? 1,
                'source' => 'pnedu',
                'channel' => SurveySetting::CHANNEL_NATIVE,
                'is_anonymous' => (bool) $link->is_anonymous,
                'allow_multiple_responses' => (bool) $link->allow_multiple_responses,
                'total_responses' => 0,
                'metadata' => [
                    'provisioned_from_template' => $template->id,
                    'course_survey_link_id' => $link->id,
                ],
            ]);

            foreach ($template->questions as $tq) {
                SurveyQuestion::create([
                    'survey_id' => $survey->id,
                    'question_text' => $tq->question_text,
                    'question_type' => $tq->question_type,
                    'question_order' => $tq->question_order,
                    'options' => $tq->options,
                ]);
            }

            $window = $this->resolveAvailabilityWindow($course, $settings, $link);

            $link->forceFill([
                'survey_id' => $survey->id,
                'survey_template_id' => $template->id,
                'channel' => SurveySetting::CHANNEL_NATIVE,
                'provider' => 'pnedu',
                'url' => null,
                'title' => filled($link->title) ? $link->title : $autoTitle,
                'opens_at' => $link->opens_at ?? $window['opens_at'],
                'closes_at' => $link->closes_at ?? $window['closes_at'],
            ])->save();

            return $survey->load('questions');
        });
    }

    /**
     * Tytuł: ANKIETA: {tytuł szkolenia bez HTML/&nbsp;} (YYYY-MM-DD).
     * Data = start_date szkolenia (fallback: end_date).
     */
    public function defaultNativeSurveyTitle(Course $course): string
    {
        $plainTitle = $course->plainTitle('Szkolenie');

        $datePart = $course->start_date
            ? $course->start_date->format('Y-m-d')
            : ($course->end_date ? $course->end_date->format('Y-m-d') : 'brak daty');

        return Str::limit('ANKIETA: '.$plainTitle.' ('.$datePart.')', 255, '');
    }

    /**
     * @return array{opens_at: ?Carbon, closes_at: ?Carbon}
     */
    public function resolveAvailabilityWindow(Course $course, SurveySetting $settings, ?CourseSurveyLink $link = null): array
    {
        $opensAt = $link?->opens_at;
        $closesAt = $link?->closes_at;

        if ($settings->isAutoOpenMode() && $opensAt === null) {
            $base = $course->end_date
                ? Carbon::parse($course->end_date, 'Europe/Warsaw')
                : ($course->start_date ? Carbon::parse($course->start_date, 'Europe/Warsaw') : now('Europe/Warsaw'));
            $opensAt = $base->copy()->addHours((int) $settings->auto_open_offset_hours);
        }

        if ($settings->isAutoOpenMode() && $closesAt === null && $opensAt && $settings->auto_close_after_days) {
            $closesAt = $opensAt->copy()->addDays((int) $settings->auto_close_after_days);
        }

        return [
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
        ];
    }

    private function resolveTemplate(CourseSurveyLink $link): ?SurveyTemplate
    {
        if ($link->survey_template_id) {
            $t = SurveyTemplate::query()
                ->where('id', $link->survey_template_id)
                ->where('is_active', true)
                ->with('questions')
                ->first();
            if ($t) {
                return $t;
            }
        }

        $settings = SurveySetting::getSettings();
        if ($settings->default_template_id) {
            $t = SurveyTemplate::query()
                ->where('id', $settings->default_template_id)
                ->where('is_active', true)
                ->with('questions')
                ->first();
            if ($t) {
                return $t;
            }
        }

        return SurveyTemplate::defaultActive();
    }
}
