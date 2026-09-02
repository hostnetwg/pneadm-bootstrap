<?php

namespace App\Http\Controllers;

use App\Models\SurveyTestimonial;
use Illuminate\Http\JsonResponse;
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
                ->orderByDesc('created_at');
        } elseif ($filter === 'featured') {
            $query->where('is_featured', true)
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

    public function publish(Request $request, SurveyTestimonial $testimonial): RedirectResponse|JsonResponse
    {
        if (! $testimonial->publish_consent) {
            return $this->respond(
                $request,
                $testimonial,
                'Brak zgody uczestnika na publikację.',
                success: false,
            );
        }

        $testimonial->publish();

        return $this->respond(
            $request,
            $testimonial,
            'Rekomendacja opublikowana na stronie głównej.',
        );
    }

    public function unpublish(Request $request, SurveyTestimonial $testimonial): RedirectResponse|JsonResponse
    {
        $testimonial->unpublish();

        return $this->respond(
            $request,
            $testimonial,
            'Rekomendacja zdjęta z publikacji.',
        );
    }

    public function feature(Request $request, SurveyTestimonial $testimonial): RedirectResponse|JsonResponse
    {
        if (! $testimonial->is_published || ! $testimonial->publish_consent) {
            return $this->respond(
                $request,
                $testimonial,
                'Wyróżnić można tylko opublikowaną rekomendację.',
                success: false,
            );
        }

        if (! $testimonial->is_featured
            && SurveyTestimonial::featuredCount() >= SurveyTestimonial::FEATURED_SOFT_LIMIT) {
            return $this->respond(
                $request,
                $testimonial,
                'Masz już '.SurveyTestimonial::FEATURED_SOFT_LIMIT.' wyróżnionych rekomendacji. '
                .'Usuń wyróżnienie z jednej z nich (filtr „Wyróżnione”), potem dodaj nową — '
                .'krótka lista na górze działa lepiej marketingowo.',
                success: false,
            );
        }

        $testimonial->feature();

        return $this->respond(
            $request,
            $testimonial,
            'Rekomendacja wyróżniona — będzie wyświetlana na górze listy na pnedu.pl.',
        );
    }

    public function unfeature(Request $request, SurveyTestimonial $testimonial): RedirectResponse|JsonResponse
    {
        $testimonial->unfeature();

        return $this->respond(
            $request,
            $testimonial,
            'Usunięto wyróżnienie rekomendacji.',
        );
    }

    public function destroy(Request $request, SurveyTestimonial $testimonial): RedirectResponse|JsonResponse
    {
        $testimonial->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Rekomendacja usunięta.',
                'featured_count' => SurveyTestimonial::featuredCount(),
            ]);
        }

        return $this->redirectToIndex($request)->with('success', 'Rekomendacja usunięta.');
    }

    private function respond(
        Request $request,
        SurveyTestimonial $testimonial,
        string $message,
        bool $success = true,
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson() || $request->ajax()) {
            $testimonial->refresh();

            return response()->json([
                'success' => $success,
                'message' => $message,
                'testimonial' => [
                    'id' => $testimonial->id,
                    'is_published' => (bool) $testimonial->is_published,
                    'is_featured' => (bool) $testimonial->is_featured,
                    'publish_consent' => (bool) $testimonial->publish_consent,
                ],
                'featured_count' => SurveyTestimonial::featuredCount(),
                'urls' => [
                    'publish' => route('surveys.testimonials.publish', $testimonial),
                    'unpublish' => route('surveys.testimonials.unpublish', $testimonial),
                    'feature' => route('surveys.testimonials.feature', $testimonial),
                    'unfeature' => route('surveys.testimonials.unfeature', $testimonial),
                ],
            ], $success ? 200 : 422);
        }

        return $success
            ? $this->redirectToIndex($request)->with('success', $message)
            : $this->redirectToIndex($request)->with('error', $message);
    }

    /**
     * Świadomy powrót na listę — zachowaj filtr i stronę paginacji (nie używamy back()).
     */
    private function redirectToIndex(Request $request): RedirectResponse
    {
        $filter = $request->string('filter')->toString();
        $page = $request->integer('page');

        if ($request->headers->get('referer')) {
            $query = parse_url((string) $request->headers->get('referer'), PHP_URL_QUERY);
            if (is_string($query) && $query !== '') {
                parse_str($query, $params);
                if ($filter === '' && isset($params['filter']) && is_string($params['filter'])) {
                    $filter = $params['filter'];
                }
                if ($page < 1 && isset($params['page'])) {
                    $page = (int) $params['page'];
                }
            }
        }

        $params = [];
        if (in_array($filter, ['pending', 'published', 'featured'], true)) {
            $params['filter'] = $filter;
        }
        if ($page > 1) {
            $params['page'] = $page;
        }

        return redirect()->route('surveys.testimonials.index', $params);
    }
}
