<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                {{ __('Podgląd artykułu') }}
            </h2>
            <div class="d-flex gap-2">
                <a href="{{ route('articles.edit', $article) }}" class="btn btn-primary">Edytuj</a>
                <button type="button"
                        class="btn btn-outline-danger"
                        data-bs-toggle="modal"
                        data-bs-target="#deleteArticleModal">
                    <i class="bi bi-trash" aria-hidden="true"></i> Usuń
                </button>
                <a href="{{ route('articles.index') }}" class="btn btn-outline-secondary">Lista artykułów</a>
            </div>
        </div>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-body p-4">
                            @if($article->publicImageUrl())
                                <img src="{{ $article->publicImageUrl() }}" alt="{{ $article->title }}" class="img-fluid rounded border mb-4">
                            @endif

                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge {{ $article->statusBadgeClass() }}">
                                    {{ $article->statusLabel() }}
                                </span>
                                <span class="badge {{ $article->comments_enabled ? 'bg-primary' : 'bg-light text-dark' }}">
                                    {{ $article->comments_enabled ? 'Komentarze włączone później' : 'Komentarze wyłączone' }}
                                </span>
                            </div>

                            <h1 class="display-6 fw-bold mb-3">{{ $article->title }}</h1>

                            <div class="text-muted mb-4">
                                @if($article->published_at)
                                    Opublikowano: {{ $article->published_at->format('Y-m-d H:i') }}
                                @else
                                    Bez daty publikacji
                                @endif
                                @if($article->author)
                                    <span class="mx-1">|</span> Autor: {{ $article->author->name }}
                                @endif
                            </div>

                            @if($article->excerpt)
                                <p class="lead">{{ $article->excerpt }}</p>
                            @endif

                            @if($article->content_html)
                                <div class="article-preview-content">
                                    {!! $article->content_html !!}
                                </div>
                            @else
                                <div class="alert alert-light border mb-0">
                                    Ten artykuł nie ma jeszcze treści. Uzupełnij pole „Treść HTML” w edycji.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Informacje</h5>
                        </div>
                        <div class="card-body">
                            <dl class="mb-0">
                                <dt>Slug</dt>
                                <dd class="font-monospace">{{ $article->slug }}</dd>

                                <dt>Status</dt>
                                <dd>{{ $article->statusLabel() }}</dd>

                                <dt>Data publikacji</dt>
                                <dd>{{ $article->published_at?->format('Y-m-d H:i') ?? 'Brak' }}</dd>

                                <dt>Wyświetlenia</dt>
                                <dd>
                                    {{ $article->formattedViewCount() }}
                                    <span class="text-muted small">(publiczny blog, zgodnie z ustawieniami analityki)</span>
                                </dd>

                                <dt>Autor</dt>
                                <dd>{{ $article->author?->name ?? 'Brak' }}</dd>

                                <dt>Utworzono</dt>
                                <dd>{{ $article->created_at?->format('Y-m-d H:i') }}</dd>
                            </dl>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">SEO</h5>
                        </div>
                        <div class="card-body">
                            <dl class="mb-0">
                                <dt>Meta title</dt>
                                <dd>{{ $article->meta_title ?: 'Używany będzie tytuł artykułu' }}</dd>

                                <dt>Meta description</dt>
                                <dd>{{ $article->meta_description ?: 'Używany będzie krótki opis lub fragment treści' }}</dd>
                            </dl>
                        </div>
                    </div>

                    @if($article->internal_notes)
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Notatki</h5>
                            </div>
                            <div class="card-body">
                                <div style="white-space: pre-line;">{{ $article->internal_notes }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteArticleModal" tabindex="-1" aria-labelledby="deleteArticleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteArticleModalLabel">Usunąć artykuł?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    Czy na pewno chcesz usunąć artykuł „{{ $article->title }}”?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <form action="{{ route('articles.destroy', $article) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Usuń artykuł</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
