<?php

namespace App\Http\Controllers;

use App\Models\Instructor;
use App\Models\TrainingOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TrainingOfferController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $visibility = $request->query('visibility', 'all');

        $offers = TrainingOffer::with('instructor')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', '%'.$search.'%')
                        ->orWhere('summary', 'like', '%'.$search.'%')
                        ->orWhere('audience', 'like', '%'.$search.'%');
                });
            })
            ->when($visibility === 'public', fn ($query) => $query->where('show_on_pnedu', true))
            ->when($visibility === 'hidden', fn ($query) => $query->where('show_on_pnedu', false))
            ->when($visibility === 'active', fn ($query) => $query->where('is_active', true))
            ->when($visibility === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(20)
            ->withQueryString();

        return view('training-offers.index', compact('offers', 'search', 'visibility'));
    }

    public function create(): View
    {
        $offer = new TrainingOffer([
            'price_mode' => TrainingOffer::PRICE_MODE_INDIVIDUAL,
            'default_course_category' => TrainingOffer::COURSE_CATEGORY_CLOSED,
            'is_active' => true,
            'show_on_pnedu' => false,
            'sort_order' => 0,
        ]);

        $instructors = $this->instructorsForSelect();

        return view('training-offers.create', compact('offer', 'instructors'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateOffer($request);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['show_on_pnedu'] = $request->boolean('show_on_pnedu');
        $validated['description_html'] = $this->sanitizeHtml($validated['description_html'] ?? null);
        $validated['price_amount'] = $validated['price_mode'] === TrainingOffer::PRICE_MODE_FIXED
            ? $validated['price_amount']
            : null;

        unset($validated['image']);

        $offer = TrainingOffer::create($validated);

        if ($request->hasFile('image')) {
            $offer->update(['image' => $this->saveImage($request)]);
        }

        if ($request->input('save_action') === 'stay_editing') {
            return redirect()
                ->route('training-offers.edit', $offer)
                ->with('success', 'Oferta szkolenia została utworzona.');
        }

        return redirect()
            ->route('training-offers.index')
            ->with('success', 'Oferta szkolenia została utworzona.');
    }

    public function show(TrainingOffer $trainingOffer): View
    {
        $trainingOffer->load('instructor');

        return view('training-offers.show', ['offer' => $trainingOffer]);
    }

    public function edit(TrainingOffer $trainingOffer): View
    {
        $instructors = $this->instructorsForSelect();

        return view('training-offers.edit', [
            'offer' => $trainingOffer,
            'instructors' => $instructors,
        ]);
    }

    public function update(Request $request, TrainingOffer $trainingOffer): RedirectResponse
    {
        $validated = $this->validateOffer($request, $trainingOffer);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['show_on_pnedu'] = $request->boolean('show_on_pnedu');
        $validated['description_html'] = $this->sanitizeHtml($validated['description_html'] ?? null);
        $validated['price_amount'] = $validated['price_mode'] === TrainingOffer::PRICE_MODE_FIXED
            ? $validated['price_amount']
            : null;

        unset($validated['image']);

        if ($request->boolean('remove_image') && $trainingOffer->image) {
            Storage::disk('public')->delete($trainingOffer->image);
            $validated['image'] = null;
        }

        if ($request->hasFile('image')) {
            if ($trainingOffer->image) {
                Storage::disk('public')->delete($trainingOffer->image);
            }
            $validated['image'] = $this->saveImage($request);
        }

        $trainingOffer->update($validated);

        if ($request->input('save_action') === 'stay_editing') {
            return redirect()
                ->route('training-offers.edit', $trainingOffer)
                ->with('success', 'Oferta szkolenia została zaktualizowana.');
        }

        return redirect()
            ->route('training-offers.index')
            ->with('success', 'Oferta szkolenia została zaktualizowana.');
    }

    public function destroy(TrainingOffer $trainingOffer): RedirectResponse
    {
        $trainingOffer->delete();

        return redirect()
            ->route('training-offers.index')
            ->with('success', 'Oferta szkolenia została usunięta.');
    }

    private function validateOffer(Request $request, ?TrainingOffer $offer = null): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'summary' => 'nullable|string|max:500',
            'description_html' => 'nullable|string',
            'scope' => 'nullable|string',
            'audience' => 'nullable|string|max:255',
            'price_mode' => 'required|in:individual,fixed',
            'price_amount' => 'nullable|required_if:price_mode,fixed|numeric|min:0|max:999999.99',
            'instructor_id' => 'nullable|exists:instructors,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'default_course_category' => 'required|in:open,closed',
            'is_active' => 'nullable|boolean',
            'show_on_pnedu' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:999999',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'internal_notes' => 'nullable|string',
            'save_action' => 'required|in:close,stay_editing',
            'remove_image' => 'nullable|boolean',
        ]);
    }

    private function instructorsForSelect()
    {
        return Instructor::query()
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'title', 'first_name', 'last_name']);
    }

    private function saveImage(Request $request): string
    {
        return $request->file('image')->store('training-offers/images', 'public');
    }

    private function sanitizeHtml(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        return strip_tags($html,
            '<p><br><br/><strong><b><em><i><u><ul><ol><li><h1><h2><h3><h4><h5><h6><a><img><div><span>'.
            '<section><article><header><footer><main><aside>'.
            '<hr><hr/><code><pre><small><mark><del><ins><sub><sup>'.
            '<table><thead><tbody><tfoot><tr><td><th><caption><colgroup><col>'.
            '<blockquote><cite><abbr><dfn><time><address><q>'.
            '<dl><dt><dd>');
    }
}
