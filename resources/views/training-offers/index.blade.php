<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                {{ __('Oferty szkoleń') }}
            </h2>
            <a href="{{ route('training-offers.create') }}" class="btn btn-primary">
                Dodaj ofertę
            </a>
        </div>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('training-offers.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label for="q" class="form-label">Szukaj</label>
                            <input type="text" name="q" id="q" value="{{ $search }}" class="form-control" placeholder="Tytuł, podsumowanie, odbiorcy">
                        </div>
                        <div class="col-md-3">
                            <label for="visibility" class="form-label">Widoczność</label>
                            <select name="visibility" id="visibility" class="form-select">
                                <option value="all" {{ $visibility === 'all' ? 'selected' : '' }}>Wszystkie</option>
                                <option value="public" {{ $visibility === 'public' ? 'selected' : '' }}>Publiczne na pnedu</option>
                                <option value="hidden" {{ $visibility === 'hidden' ? 'selected' : '' }}>Niepubliczne</option>
                                <option value="active" {{ $visibility === 'active' ? 'selected' : '' }}>Aktywne</option>
                                <option value="inactive" {{ $visibility === 'inactive' ? 'selected' : '' }}>Nieaktywne</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-outline-primary">Filtruj</button>
                            <a href="{{ route('training-offers.index') }}" class="btn btn-outline-secondary">Wyczyść</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tytuł</th>
                                <th>Trener</th>
                                <th>Cena</th>
                                <th>Status</th>
                                <th>Kolejność</th>
                                <th class="text-end">Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($offers as $offer)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $offer->title }}</div>
                                        <div class="small text-muted">{{ $offer->slug }}</div>
                                    </td>
                                    <td>{{ $offer->instructor?->full_title_name ?? 'Brak' }}</td>
                                    <td>{{ $offer->formattedPrice() }}</td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <span class="badge {{ $offer->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $offer->is_active ? 'Aktywna' : 'Nieaktywna' }}
                                            </span>
                                            <span class="badge {{ $offer->show_on_pnedu ? 'bg-primary' : 'bg-light text-dark' }}">
                                                {{ $offer->show_on_pnedu ? 'Na pnedu' : 'Ukryta' }}
                                            </span>
                                            @if($offer->featured_on_homepage)
                                                <span class="badge bg-warning text-dark">Strona główna</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $offer->sort_order }}</td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="{{ route('training-offers.show', $offer) }}" class="btn btn-outline-secondary">Podgląd</a>
                                            <a href="{{ route('training-offers.create-course', $offer) }}" class="btn btn-outline-success">Utwórz szkolenie</a>
                                            <a href="{{ route('training-offers.edit', $offer) }}" class="btn btn-outline-primary">Edytuj</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        Brak ofert szkoleń.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($offers->hasPages())
                    <div class="card-footer">
                        {{ $offers->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
