<?php

namespace App\Http\Controllers;

use App\Models\SurveyTemplate;
use App\Models\SurveyTemplateQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SurveyTemplateController extends Controller
{
    public function index(): View
    {
        $templates = SurveyTemplate::query()
            ->withCount('questions')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('surveys.templates.index', compact('templates'));
    }

    public function edit(SurveyTemplate $template): View
    {
        $template->load('questions');
        $types = SurveyTemplateQuestion::TYPES;

        return view('surveys.templates.edit', compact('template', 'types'));
    }

    public function update(Request $request, SurveyTemplate $template): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        $template->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'is_default' => $request->boolean('is_default'),
        ]);

        return back()->with('success', 'Szablon został zaktualizowany.');
    }

    public function storeQuestion(Request $request, SurveyTemplate $template): RedirectResponse
    {
        $validated = $request->validate([
            'question_text' => 'required|string|max:500',
            'question_type' => 'required|in:'.implode(',', SurveyTemplateQuestion::TYPES),
            'question_order' => 'nullable|integer|min:0|max:9999',
            'is_required' => 'nullable|boolean',
            'options_text' => 'nullable|string|max:5000',
        ]);

        $key = Str::slug(Str::limit($validated['question_text'], 40, '')).'-'.Str::lower(Str::random(4));

        SurveyTemplateQuestion::create([
            'survey_template_id' => $template->id,
            'question_key' => $key,
            'question_text' => $validated['question_text'],
            'question_type' => $validated['question_type'],
            'question_order' => (int) ($validated['question_order'] ?? (($template->questions()->max('question_order') ?? 0) + 10)),
            'is_required' => $request->boolean('is_required', true),
            'options' => $this->parseOptionsText($validated['options_text'] ?? null, $validated['question_type']),
        ]);

        return back()->with('success', 'Pytanie zostało dodane.');
    }

    public function updateQuestion(Request $request, SurveyTemplate $template, SurveyTemplateQuestion $question): RedirectResponse
    {
        if ((int) $question->survey_template_id !== (int) $template->id) {
            abort(404);
        }

        $validated = $request->validate([
            'question_text' => 'required|string|max:500',
            'question_type' => 'required|in:'.implode(',', SurveyTemplateQuestion::TYPES),
            'question_order' => 'nullable|integer|min:0|max:9999',
            'is_required' => 'nullable|boolean',
            'options_text' => 'nullable|string|max:5000',
        ]);

        $question->update([
            'question_text' => $validated['question_text'],
            'question_type' => $validated['question_type'],
            'question_order' => (int) ($validated['question_order'] ?? $question->question_order),
            'is_required' => $request->boolean('is_required'),
            'options' => $this->parseOptionsText($validated['options_text'] ?? null, $validated['question_type']),
        ]);

        return back()->with('success', 'Pytanie zostało zaktualizowane.');
    }

    public function destroyQuestion(SurveyTemplate $template, SurveyTemplateQuestion $question): RedirectResponse
    {
        if ((int) $question->survey_template_id !== (int) $template->id) {
            abort(404);
        }

        $question->delete();

        return back()->with('success', 'Pytanie zostało usunięte.');
    }

    /**
     * @return array<int|string, mixed>|null
     */
    private function parseOptionsText(?string $text, string $type): ?array
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        if ($type === 'availability') {
            // Oczekiwany JSON lub dwa bloki oddzielone ---
            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $options = array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));

        return $options === [] ? null : $options;
    }
}
