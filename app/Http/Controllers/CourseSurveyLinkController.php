<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseSurveyLink;
use App\Models\SurveySetting;
use App\Models\SurveyTemplate;
use App\Services\NativeSurveyProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CourseSurveyLinkController extends Controller
{
    public function __construct(
        private readonly NativeSurveyProvisioner $provisioner,
    ) {}

    public function index(Course $course)
    {
        $settings = SurveySetting::getSettings();
        $templates = SurveyTemplate::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'is_default']);

        $links = $course->surveyLinks()->orderBy('order')->get()->map(function (CourseSurveyLink $link) {
            return $this->serializeLink($link);
        });

        return response()->json([
            'success' => true,
            'survey_links' => $links,
            'defaults' => [
                'channel' => $settings->default_channel,
                'is_anonymous' => (bool) $settings->default_is_anonymous,
                'open_mode' => $settings->open_mode,
                'default_template_id' => $settings->default_template_id,
            ],
            'templates' => $templates,
        ]);
    }

    public function store(Request $request, Course $course)
    {
        $validator = $this->validator($request);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Wystąpiły błędy walidacji',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $this->payload($request);
            $data['course_id'] = $course->id;

            $link = CourseSurveyLink::create($data);

            if ($link->isNative()) {
                $template = null;
                if ($request->filled('survey_template_id')) {
                    $template = SurveyTemplate::query()->find($request->input('survey_template_id'));
                }
                $this->provisioner->provisionForLink($link->fresh(['course']), $template);
                $link->refresh();
            }

            return response()->json([
                'success' => true,
                'message' => 'Ankieta została dodana pomyślnie.',
                'survey_link' => $this->serializeLink($link->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Nie udało się dodać ankiety: '.$e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Course $course, CourseSurveyLink $surveyLink)
    {
        if ((int) $surveyLink->course_id !== (int) $course->id) {
            abort(404);
        }

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Wystąpiły błędy walidacji',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $wasNative = $surveyLink->isNative();
            $surveyLink->update($this->payload($request));

            if ($surveyLink->fresh()->isNative() && ! $surveyLink->survey_id) {
                $template = null;
                if ($request->filled('survey_template_id')) {
                    $template = SurveyTemplate::query()->find($request->input('survey_template_id'));
                }
                $this->provisioner->provisionForLink($surveyLink->fresh(['course']), $template);
            } elseif ($wasNative && $surveyLink->fresh()->isExternal()) {
                // Przełączenie na zewnętrzną — zostawiamy Survey w bazie (historia), odpinamy powiązanie
                $surveyLink->update(['survey_id' => $surveyLink->survey_id]);
            }

            // Aktualizacja anonimowości na powiązanej ankiecie
            if ($surveyLink->survey_id) {
                $surveyLink->survey()?->update([
                    'is_anonymous' => (bool) $surveyLink->is_anonymous,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Ankieta została zaktualizowana pomyślnie.',
                'survey_link' => $this->serializeLink($surveyLink->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Nie udało się zaktualizować ankiety: '.$e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Course $course, CourseSurveyLink $surveyLink)
    {
        if ((int) $surveyLink->course_id !== (int) $course->id) {
            abort(404);
        }

        try {
            $surveyLink->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ankieta została usunięta pomyślnie.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Nie udało się usunąć ankiety: '.$e->getMessage(),
            ], 500);
        }
    }

    private function validator(Request $request)
    {
        $channel = $request->input('channel', CourseSurveyLink::CHANNEL_EXTERNAL);

        return Validator::make($request->all(), [
            'channel' => ['nullable', Rule::in([CourseSurveyLink::CHANNEL_EXTERNAL, CourseSurveyLink::CHANNEL_NATIVE])],
            'url' => [
                Rule::requiredIf($channel !== CourseSurveyLink::CHANNEL_NATIVE),
                'nullable',
                'url',
                'max:2048',
            ],
            'title' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'is_anonymous' => 'nullable|boolean',
            'survey_template_id' => 'nullable|exists:survey_templates,id',
            'opens_at' => 'nullable|date',
            'closes_at' => 'nullable|date|after_or_equal:opens_at',
            'order' => 'nullable|integer|min:0',
        ], [
            'url.required' => 'Adres ankiety jest wymagany dla formularza zewnętrznego (Google Forms itp.).',
            'url.url' => 'Podaj prawidłowy adres URL.',
            'closes_at.after_or_equal' => 'Data zamknięcia musi być późniejsza lub równa dacie otwarcia.',
        ]);
    }

    private function payload(Request $request): array
    {
        $channel = $request->input('channel', CourseSurveyLink::CHANNEL_EXTERNAL);
        $isNative = $channel === CourseSurveyLink::CHANNEL_NATIVE;
        $url = $isNative ? null : $request->input('url');

        return [
            'url' => $url,
            'title' => $request->input('title'),
            'channel' => $isNative ? CourseSurveyLink::CHANNEL_NATIVE : CourseSurveyLink::CHANNEL_EXTERNAL,
            'provider' => $isNative ? 'pnedu' : CourseSurveyLink::detectProvider($url),
            'is_anonymous' => $request->boolean('is_anonymous', true),
            'survey_template_id' => $isNative ? ($request->input('survey_template_id') ?: SurveySetting::getSettings()->default_template_id) : null,
            'is_active' => $request->boolean('is_active', true),
            'opens_at' => $request->filled('opens_at')
                ? CourseSurveyLink::parseAdminDatetimeLocal($request->input('opens_at'))
                : null,
            'closes_at' => $request->filled('closes_at')
                ? CourseSurveyLink::parseAdminDatetimeLocal($request->input('closes_at'))
                : null,
            'order' => (int) ($request->input('order') ?? 0),
        ];
    }

    private function serializeLink(CourseSurveyLink $link): array
    {
        return [
            'id' => $link->id,
            'course_id' => $link->course_id,
            'survey_id' => $link->survey_id,
            'survey_template_id' => $link->survey_template_id,
            'url' => $link->url,
            'title' => $link->title,
            'provider' => $link->provider,
            'channel' => $link->channel ?? CourseSurveyLink::CHANNEL_EXTERNAL,
            'is_anonymous' => (bool) $link->is_anonymous,
            'provider_label' => $link->providerLabel(),
            'provider_icon' => $link->providerIconClass(),
            'is_active' => $link->is_active,
            'opens_at' => optional($link->opens_at)->format('Y-m-d\TH:i'),
            'closes_at' => optional($link->closes_at)->format('Y-m-d\TH:i'),
            'is_available_now' => $link->isAvailableNow(),
            'order' => $link->order,
            'participant_facing_url' => $link->participantFacingSurveyUrl(),
        ];
    }
}
