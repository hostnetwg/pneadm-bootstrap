<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = $request->query('status', 'all');

        $query = Article::query()
            ->with('author')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('title', 'like', '%'.$search.'%')
                        ->orWhere('excerpt', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%');
                });
            })
            ->when($status === Article::STATUS_DRAFT, fn ($query) => $query->where('status', Article::STATUS_DRAFT))
            ->when($status === Article::STATUS_PUBLISHED, fn ($query) => $query->where('status', Article::STATUS_PUBLISHED))
            ->ordered();

        $canReorder = $search === '' && $status === 'all';

        if ($canReorder) {
            $articles = $query->get();
        } else {
            $articles = $query->paginate(20)->withQueryString();
        }

        return view('articles.index', compact('articles', 'search', 'status', 'canReorder'));
    }

    public function create(): View
    {
        $article = new Article([
            'status' => Article::STATUS_DRAFT,
            'comments_enabled' => false,
        ]);

        return view('articles.create', compact('article'));
    }

    public function examplePreview(): View
    {
        return view('articles.example-preview');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateArticle($request);
        $validated['comments_enabled'] = $request->boolean('comments_enabled');
        $validated['content_html'] = $this->sanitizeHtml($validated['content_html'] ?? null);
        $validated['author_id'] = $request->user()?->id;

        unset($validated['cover_image']);

        $article = Article::create($validated);

        if ($request->hasFile('cover_image')) {
            $article->update(['cover_image' => $this->saveCoverImage($request, $article)]);
        }

        if ($request->input('save_action') === 'stay_editing') {
            return redirect()
                ->route('articles.edit', $article)
                ->with('success', 'Artykuł został utworzony.');
        }

        return redirect()
            ->route('articles.index')
            ->with('success', 'Artykuł został utworzony.');
    }

    public function show(Article $article): View
    {
        $article->load('author');

        return view('articles.show', compact('article'));
    }

    public function edit(Article $article): View
    {
        return view('articles.edit', compact('article'));
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        $validated = $this->validateArticle($request, $article);
        $validated['comments_enabled'] = $request->boolean('comments_enabled');
        $validated['content_html'] = $this->sanitizeHtml($validated['content_html'] ?? null);

        unset($validated['cover_image']);

        if ($request->boolean('remove_cover_image') && $article->cover_image) {
            Storage::disk('public')->delete($article->cover_image);
            $validated['cover_image'] = null;
        }

        if ($request->hasFile('cover_image')) {
            if ($article->cover_image) {
                Storage::disk('public')->delete($article->cover_image);
            }

            $validated['cover_image'] = $this->saveCoverImage($request, $article);
        }

        $article->update($validated);

        if ($request->input('save_action') === 'stay_editing') {
            return redirect()
                ->route('articles.edit', $article)
                ->with('success', 'Artykuł został zaktualizowany.');
        }

        return redirect()
            ->route('articles.index')
            ->with('success', 'Artykuł został zaktualizowany.');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $article->delete();

        return redirect()
            ->route('articles.index')
            ->with('success', 'Artykuł został usunięty (przeniesiony do kosza).');
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:articles,id'],
        ]);

        $order = array_values(array_unique(array_map('intval', $validated['order'])));
        $expectedIds = Article::query()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();
        $sortedOrder = collect($order)->sort()->values()->all();

        if ($sortedOrder !== $expectedIds) {
            return response()->json([
                'message' => 'Lista musi zawierać wszystkie artykuły.',
            ], 422);
        }

        DB::transaction(function () use ($order): void {
            foreach ($order as $position => $id) {
                Article::query()->whereKey($id)->update(['sort_order' => $position]);
            }
        });

        return response()->json(['message' => 'Kolejność artykułów zapisana.']);
    }

    private function validateArticle(Request $request, ?Article $article = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('articles', 'slug')->ignore($article?->id),
            ],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content_html' => ['nullable', 'string'],
            'status' => ['required', Rule::in([Article::STATUS_DRAFT, Article::STATUS_PUBLISHED])],
            'published_at' => ['nullable', 'date'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'comments_enabled' => ['nullable', 'boolean'],
            'internal_notes' => ['nullable', 'string'],
            'save_action' => ['required', Rule::in(['close', 'stay_editing'])],
            'remove_cover_image' => ['nullable', 'boolean'],
        ]);
    }

    private function saveCoverImage(Request $request, Article $article): string
    {
        /** @var UploadedFile $file */
        $file = $request->file('cover_image');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (! in_array($extension, $allowed, true)) {
            $extension = 'jpg';
        }

        $filename = Article::seoCoverImageFilename($article, $extension);

        return $file->storeAs('articles/covers', $filename, 'public');
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
