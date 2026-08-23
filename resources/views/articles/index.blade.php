<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                {{ __('Artykuły') }}
            </h2>
            <div class="d-flex gap-2">
                <a href="{{ route('articles.example-preview') }}" class="btn btn-outline-secondary">
                    Przykładowy podgląd
                </a>
                <a href="{{ route('articles.create') }}" class="btn btn-primary">
                    Dodaj artykuł
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="alert alert-light border mb-4">
                <h5 class="mb-2">Zarządzanie blogiem pnedu.pl</h5>
                <p class="mb-2">
                    Opublikowane artykuły pojawią się na publicznej stronie <code>/blog</code>.
                    Kolumna <strong>Wyświetlenia</strong> liczy wejścia na publiczny artykuł (max. raz na sesję odwiedzającego).
                    Komentarze są przewidziane jako następny etap i obecnie nie mają publicznego formularza.
                    <a href="{{ route('articles.example-preview') }}">Zobacz przykładowy podgląd artykułu.</a>
                </p>
                @if($canReorder)
                    <p class="mb-0">
                        <i class="bi bi-arrows-move text-primary"></i>
                        <strong>Kolejność na blogu:</strong> przeciągnij wiersze lub użyj strzałek w kolumnie „Kolej.”.
                        Nowy artykuł domyślnie ląduje na górze listy.
                    </p>
                @else
                    <p class="mb-0 text-muted small">
                        Aby zmienić kolejność artykułów na blogu, wyczyść filtry (status „Wszystkie”, bez wyszukiwania).
                    </p>
                @endif
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('articles.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label for="q" class="form-label">Szukaj</label>
                            <input type="text" name="q" id="q" value="{{ $search }}" class="form-control" placeholder="Tytuł, slug, opis">
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="all" {{ $status === 'all' ? 'selected' : '' }}>Wszystkie</option>
                                <option value="draft" {{ $status === 'draft' ? 'selected' : '' }}>Szkice</option>
                                <option value="published" {{ $status === 'published' ? 'selected' : '' }}>Opublikowane</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-outline-primary">Filtruj</button>
                            <a href="{{ route('articles.index') }}" class="btn btn-outline-secondary">Wyczyść</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                @if($canReorder)
                    <div id="articles-reorder-root"
                         class="card-body pb-0"
                         data-reorder-url="{{ route('articles.reorder') }}">
                        <div id="articles-reorder-toast"
                             class="alert alert-success alert-dismissible fade show d-none mb-2 py-2"
                             role="alert">
                            <span data-toast-body></span>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                        </div>
                    </div>
                @endif
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                @if($canReorder)
                                    <th class="text-center" style="width: 5rem;" title="Kolejność na publicznym blogu">Kolej.</th>
                                @endif
                                <th>Tytuł</th>
                                <th>Status</th>
                                <th>Publikacja</th>
                                <th class="text-end" title="Wejścia na publiczny artykuł (max. raz na sesję)">Wyśw.</th>
                                <th>Dodał</th>
                                <th>Komentarze</th>
                                <th class="text-end">Akcje</th>
                            </tr>
                        </thead>
                        <tbody id="{{ $canReorder ? 'articles-sortable' : '' }}">
                            @forelse($articles as $article)
                                <tr class="{{ $canReorder ? 'article-row' : '' }}"
                                    @if($canReorder) data-article-id="{{ $article->id }}" @endif>
                                    @if($canReorder)
                                        <td class="text-center text-nowrap">
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary btn-article-move-up"
                                                    title="Wyżej"
                                                    aria-label="Przesuń wyżej">
                                                <i class="bi bi-chevron-up"></i>
                                            </button>
                                            <span class="article-drag-handle d-inline-flex align-items-center justify-content-center mx-1 text-muted"
                                                  title="Przeciągnij"
                                                  style="cursor: grab;">
                                                <i class="bi bi-grip-vertical"></i>
                                            </span>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary btn-article-move-down"
                                                    title="Niżej"
                                                    aria-label="Przesuń niżej">
                                                <i class="bi bi-chevron-down"></i>
                                            </button>
                                        </td>
                                    @endif
                                    <td>
                                        <div class="fw-semibold">{{ $article->plainTitle() }}</div>
                                        <div class="small text-muted">{{ $article->slug }}</div>
                                        @if(filled($article->excerpt))
                                            <div class="small text-muted text-truncate" style="max-width: 520px;">
                                                {{ $article->plainExcerpt() }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $article->statusBadgeClass() }}">
                                            {{ $article->statusLabel() }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($article->published_at)
                                            {{ $article->published_at->format('Y-m-d H:i') }}
                                        @else
                                            <span class="text-muted">Brak</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <span title="{{ $article->formattedViewCount() }} wyświetleń">
                                            {{ $article->formattedViewCount() }}
                                        </span>
                                    </td>
                                    <td>{{ $article->author?->name ?? 'Brak' }}</td>
                                    <td>
                                        <span class="badge {{ $article->comments_enabled ? 'bg-primary' : 'bg-light text-dark' }}">
                                            {{ $article->comments_enabled ? 'Włączone później' : 'Wyłączone' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="{{ route('articles.show', $article) }}" class="btn btn-outline-secondary">Podgląd</a>
                                            <a href="{{ route('articles.edit', $article) }}" class="btn btn-outline-primary">Edytuj</a>
                                            <button type="button"
                                                    class="btn btn-outline-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteArticleModal{{ $article->id }}">
                                                <i class="bi bi-trash" aria-hidden="true"></i> Usuń
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canReorder ? 8 : 7 }}" class="text-center text-muted py-4">
                                        Brak artykułów. Dodaj pierwszy wpis, aby przygotować blog pnedu.pl.
                                        <div class="mt-3">
                                            <a href="{{ route('articles.example-preview') }}" class="btn btn-outline-secondary btn-sm">
                                                Zobacz przykładowy podgląd
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if(!$canReorder && $articles instanceof \Illuminate\Contracts\Pagination\Paginator && $articles->hasPages())
                    <div class="card-footer">
                        {{ $articles->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if($canReorder)
        @include('articles.partials.index-sortable')
    @endif

    @foreach($articles as $article)
        <div class="modal fade" id="deleteArticleModal{{ $article->id }}" tabindex="-1" aria-labelledby="deleteArticleModalLabel{{ $article->id }}" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteArticleModalLabel{{ $article->id }}">
                            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Potwierdzenie usunięcia
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p>Czy na pewno chcesz usunąć artykuł <strong>#{{ $article->id }}</strong>?</p>
                        <div class="bg-light p-3 rounded">
                            <ul class="mb-0">
                                <li><strong>Tytuł:</strong> {{ $article->title }}</li>
                                <li><strong>Slug:</strong> <span class="font-monospace">{{ $article->slug }}</span></li>
                                <li><strong>Status:</strong> {{ $article->statusLabel() }}</li>
                            </ul>
                        </div>
                        <p class="text-muted mt-3 mb-0">
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                            Artykuł trafi do kosza i zniknie z publicznego bloga.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                        <form action="{{ route('articles.destroy', $article) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash" aria-hidden="true"></i> Usuń artykuł
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</x-app-layout>
