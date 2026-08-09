<?php

namespace App\Http\Controllers;

use App\Models\SurveyTestimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SurveyTestimonialController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->string('filter')->toString();

        $query = SurveyTestimonial::query()->with(['course', 'survey'])->orderByDesc('created_at');

        if ($filter === 'pending') {
            $query->where('publish_consent', true)->where('is_published', false);
        } elseif ($filter === 'published') {
            $query->where('is_published', true);
        }

        $testimonials = $query->paginate(30)->withQueryString();

        return view('surveys.testimonials.index', compact('testimonials', 'filter'));
    }

    public function publish(SurveyTestimonial $testimonial): RedirectResponse
    {
        if (! $testimonial->publish_consent) {
            return back()->with('error', 'Brak zgody uczestnika na publikację.');
        }

        $testimonial->publish();

        return back()->with('success', 'Rekomendacja opublikowana na stronie głównej.');
    }

    public function unpublish(SurveyTestimonial $testimonial): RedirectResponse
    {
        $testimonial->unpublish();

        return back()->with('success', 'Rekomendacja zdjęta z publikacji.');
    }

    public function destroy(SurveyTestimonial $testimonial): RedirectResponse
    {
        $testimonial->delete();

        return back()->with('success', 'Rekomendacja usunięta.');
    }
}
