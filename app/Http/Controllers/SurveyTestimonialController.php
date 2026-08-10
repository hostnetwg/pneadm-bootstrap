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

        $query = SurveyTestimonial::query()->with(['course', 'survey']);

        if ($filter === 'pending') {
            $query->where('publish_consent', true)->where('is_published', false)
                ->orderByDesc('created_at');
        } elseif ($filter === 'published') {
            $query->where('is_published', true)
                ->orderByDesc('is_featured')
                ->orderBy('display_order')
                ->orderByDesc('created_at');
        } elseif ($filter === 'featured') {
            $query->where('is_featured', true)
                ->orderBy('display_order')
                ->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $testimonials = $query->paginate(30)->withQueryString();
        $featuredCount = SurveyTestimonial::featuredCount();

        return view('surveys.testimonials.index', compact('testimonials', 'filter', 'featuredCount'));
    }

    public function update(Request $request, SurveyTestimonial $testimonial): RedirectResponse
    {
        $validated = $request->validate([
            'quote' => ['required', 'string', 'max:1000'],
            'author_name' => ['required', 'string', 'max:120'],
            'author_role' => ['nullable', 'string', 'max:120'],
            'author_city' => ['nullable', 'string', 'max:80'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ], [
            'quote.required' => 'Treść opinii jest wymagana.',
            'author_name.required' => 'Imię i nazwisko autora jest wymagane.',
        ]);

        $testimonial->update([
            'quote' => $validated['quote'],
            'author_name' => $validated['author_name'],
            'author_role' => $validated['author_role'] ?? null,
            'author_city' => $validated['author_city'] ?? null,
            'rating' => $validated['rating'] ?? null,
        ]);

        return $this->redirectToIndex($request)->with('success', 'Rekomendacja została zaktualizowana.');
    }

    public function publish(Request $request, SurveyTestimonial $testimonial): RedirectResponse
    {
        if (! $testimonial->publish_consent) {
            return $this->redirectToIndex($request)->with('error', 'Brak zgody uczestnika na publikację.');
        }

        $testimonial->publish();

        return $this->redirectToIndex($request)->with('success', 'Rekomendacja opublikowana na stronie głównej.');
    }

    public function unpublish(Request $request, SurveyTestimonial $testimonial): RedirectResponse
    {
        $testimonial->unpublish();

        return $this->redirectToIndex($request)->with('success', 'Rekomendacja zdjęta z publikacji.');
    }

    public function feature(Request $request, SurveyTestimonial $testimonial): RedirectResponse
    {
        if (! $testimonial->is_published || ! $testimonial->publish_consent) {
            return $this->redirectToIndex($request)->with('error', 'Wyróżnić można tylko opublikowaną rekomendację.');
        }

        if (! $testimonial->is_featured
            && SurveyTestimonial::featuredCount() >= SurveyTestimonial::FEATURED_SOFT_LIMIT) {
            return $this->redirectToIndex($request)->with(
                'error',
                'Masz już '.SurveyTestimonial::FEATURED_SOFT_LIMIT.' wyróżnionych rekomendacji. '
                .'Usuń wyróżnienie z jednej z nich (filtr „Wyróżnione”), potem dodaj nową — '
                .'krótka lista na górze działa lepiej marketingowo.'
            );
        }

        $testimonial->feature();

        return $this->redirectToIndex($request)->with(
            'success',
            'Rekomendacja wyróżniona — będzie wyświetlana na górze listy na pnedu.pl.'
        );
    }

    public function unfeature(Request $request, SurveyTestimonial $testimonial): RedirectResponse
    {
        $testimonial->unfeature();

        return $this->redirectToIndex($request)->with('success', 'Usunięto wyróżnienie rekomendacji.');
    }

    public function moveUp(Request $request, SurveyTestimonial $testimonial): RedirectResponse
    {
        if (! $testimonial->moveFeatured('up')) {
            return $this->redirectToIndex($request)->with('error', 'Nie można przesunąć wyżej.');
        }

        return $this->redirectToIndex($request)->with('success', 'Kolejność wyróżnień zaktualizowana.');
    }

    public function moveDown(Request $request, SurveyTestimonial $testimonial): RedirectResponse
    {
        if (! $testimonial->moveFeatured('down')) {
            return $this->redirectToIndex($request)->with('error', 'Nie można przesunąć niżej.');
        }

        return $this->redirectToIndex($request)->with('success', 'Kolejność wyróżnień zaktualizowana.');
    }

    public function destroy(Request $request, SurveyTestimonial $testimonial): RedirectResponse
    {
        $testimonial->delete();

        return $this->redirectToIndex($request)->with('success', 'Rekomendacja usunięta.');
    }

    /**
     * Świadomy powrót na listę — nie używamy back(), bo poprzedni URL bywa API JSON
     * (np. /certificates/pdf-generation-status-any z listy uczestników).
     */
    private function redirectToIndex(Request $request): RedirectResponse
    {
        $filter = $request->string('filter')->toString();
        if ($filter === '' && $request->headers->get('referer')) {
            $query = parse_url((string) $request->headers->get('referer'), PHP_URL_QUERY);
            if (is_string($query) && $query !== '') {
                parse_str($query, $params);
                if (isset($params['filter']) && is_string($params['filter'])) {
                    $filter = $params['filter'];
                }
            }
        }

        $params = in_array($filter, ['pending', 'published', 'featured'], true)
            ? ['filter' => $filter]
            : [];

        return redirect()->route('surveys.testimonials.index', $params);
    }
}
