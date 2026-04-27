<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseSurveyLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CourseSurveyLinkController extends Controller
{
    public function index(Course $course)
    {
        $links = $course->surveyLinks()->orderBy('order')->get()->map(function (CourseSurveyLink $link) {
            return [
                'id' => $link->id,
                'course_id' => $link->course_id,
                'url' => $link->url,
                'title' => $link->title,
                'provider' => $link->provider,
                'provider_label' => $link->providerLabel(),
                'provider_icon' => $link->providerIconClass(),
                'is_active' => $link->is_active,
                'opens_at' => optional($link->opens_at)->format('Y-m-d\TH:i'),
                'closes_at' => optional($link->closes_at)->format('Y-m-d\TH:i'),
                'is_available_now' => $link->isAvailableNow(),
                'order' => $link->order,
            ];
        });

        return response()->json([
            'success' => true,
            'survey_links' => $links,
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

            return response()->json([
                'success' => true,
                'message' => 'Ankieta została dodana pomyślnie.',
                'survey_link' => $link,
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
        if ($surveyLink->course_id !== $course->id) {
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
            $surveyLink->update($this->payload($request));

            return response()->json([
                'success' => true,
                'message' => 'Ankieta została zaktualizowana pomyślnie.',
                'survey_link' => $surveyLink,
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
        if ($surveyLink->course_id !== $course->id) {
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
        return Validator::make($request->all(), [
            'url' => 'required|url|max:2048',
            'title' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'opens_at' => 'nullable|date',
            'closes_at' => 'nullable|date|after_or_equal:opens_at',
            'order' => 'nullable|integer|min:0',
        ], [
            'url.required' => 'Adres ankiety jest wymagany.',
            'url.url' => 'Podaj prawidłowy adres URL.',
            'closes_at.after_or_equal' => 'Data zamknięcia musi być późniejsza lub równa dacie otwarcia.',
        ]);
    }

    private function payload(Request $request): array
    {
        return [
            'url' => $request->input('url'),
            'title' => $request->input('title'),
            'provider' => CourseSurveyLink::detectProvider($request->input('url')),
            'is_active' => $request->boolean('is_active', true),
            'opens_at' => $request->filled('opens_at') ? $request->input('opens_at') : null,
            'closes_at' => $request->filled('closes_at') ? $request->input('closes_at') : null,
            'order' => (int) ($request->input('order') ?? 0),
        ];
    }
}
