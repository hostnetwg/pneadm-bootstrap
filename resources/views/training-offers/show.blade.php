<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                {{ __('Podgląd oferty szkolenia') }}
            </h2>
            <div class="d-flex gap-2">
                <a href="{{ route('training-offers.create-course', $offer) }}" class="btn btn-success">
                    Utwórz szkolenie z oferty
                </a>
                <a href="{{ route('training-offers.edit', $offer) }}" class="btn btn-primary">Edytuj</a>
                <button type="button"
                        class="btn btn-outline-danger"
                        data-bs-toggle="modal"
                        data-bs-target="#deleteTrainingOfferModal">
                    <i class="bi bi-trash" aria-hidden="true"></i> Usuń
                </button>
                <a href="{{ route('training-offers.index') }}" class="btn btn-outline-secondary">Lista ofert</a>
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
                        <div class="card-body">
                            @if($offer->publicImageUrl())
                                <img src="{{ $offer->publicImageUrl() }}" alt="{{ $offer->title }}" class="img-fluid rounded border mb-4">
                            @endif

                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge {{ $offer->is_active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $offer->is_active ? 'Aktywna' : 'Nieaktywna' }}
                                </span>
                                <span class="badge {{ $offer->show_on_pnedu ? 'bg-primary' : 'bg-light text-dark' }}">
                                    {{ $offer->show_on_pnedu ? 'Widoczna na pnedu' : 'Niepubliczna' }}
                                </span>
                                @if($offer->featured_on_homepage)
                                    <span class="badge bg-warning text-dark">Strona główna</span>
                                @endif
                                <span class="badge bg-info text-dark">{{ $offer->defaultCourseCategoryLabel() }}</span>
                            </div>

                            <h1 class="h3 mb-3">{{ $offer->title }}</h1>

                            @if($offer->summary)
                                <p class="lead">{{ $offer->summary }}</p>
                            @endif

                            @if($offer->description_html)
                                <div class="mb-4">
                                    {!! $offer->description_html !!}
                                </div>
                            @endif

                            @if($offer->scope)
                                <h2 class="h5">Zakres / zagadnienia</h2>
                                <div class="mb-4" style="white-space: pre-line;">{{ $offer->scope }}</div>
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
                                <dd class="font-monospace">{{ $offer->slug }}</dd>

                                <dt>Cena</dt>
                                <dd>{{ $offer->formattedPrice() }}</dd>

                                <dt>Odbiorcy</dt>
                                <dd>{{ $offer->audience ?: 'Brak' }}</dd>

                                <dt>Trener</dt>
                                <dd>{{ $offer->instructor?->full_title_name ?? 'Brak' }}</dd>

                                <dt>Kolejność</dt>
                                <dd>{{ $offer->sort_order }}</dd>
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
                                <dd>{{ $offer->meta_title ?: 'Brak' }}</dd>

                                <dt>Meta description</dt>
                                <dd>{{ $offer->meta_description ?: 'Brak' }}</dd>
                            </dl>
                        </div>
                    </div>

                    @if($offer->internal_notes)
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Notatki</h5>
                            </div>
                            <div class="card-body">
                                <div style="white-space: pre-line;">{{ $offer->internal_notes }}</div>
                            </div>
                        </div>
                    @endif

                    <div class="card border-danger">
                        <div class="card-body">
                            <h5 class="card-title text-danger">Usunięcie oferty</h5>
                            <p class="text-muted small">Oferta zostanie przeniesiona do kosza przez soft delete.</p>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteTrainingOfferModal">
                                Usuń ofertę
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteTrainingOfferModal" tabindex="-1" aria-labelledby="deleteTrainingOfferModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteTrainingOfferModalLabel">Usunąć ofertę szkolenia?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    Czy na pewno chcesz usunąć ofertę „{{ $offer->title }}”?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <form action="{{ route('training-offers.destroy', $offer) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Usuń ofertę</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
