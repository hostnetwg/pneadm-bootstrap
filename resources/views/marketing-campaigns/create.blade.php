<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Dodaj kampanię marketingową') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container col-lg-9">
            <div class="mb-3">
                <a href="{{ route('marketing-campaigns.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Kampanie marketingowe
                </a>
            </div>

            @if(session('duplicate_from'))
                @php($duplicateFrom = session('duplicate_from'))
                <div class="alert alert-info border-0 shadow-sm mb-3">
                    <i class="bi bi-copy"></i>
                    Tworzysz <strong>kopię</strong> kampanii
                    <code>{{ $duplicateFrom['campaign_code'] }}</code>
                    <span class="text-muted">— {{ Str::limit($duplicateFrom['name'], 80) }}</span>.
                    Sprawdź <strong>kod kampanii</strong> przed zapisem — dopóki nie klikniesz „Zapisz kampanię”, w bazie nie powstanie nowy rekord.
                </div>
            @endif

            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h4 class="h5 mb-1 fw-semibold">
                        @if(session('duplicate_from'))
                            Nowa kampania (kopia)
                        @else
                            Nowa kampania marketingowa
                        @endif
                    </h4>
                    <p class="small text-muted mb-0">
                        @if(session('duplicate_from'))
                            Pola wypełnione z wybranej kampanii — dostosuj kod i nazwę, potem zapisz.
                        @else
                            Uzupełnij sekcje po kolei — od szkolenia i linku, potem kanał UTM. Po zapisie skopiujesz gotowe adresy.
                        @endif
                    </p>
                </div>
                <div class="card-body p-3 p-md-4">
                    <form action="{{ route('marketing-campaigns.store') }}" method="POST" id="marketing-campaign-form">
                        @csrf

                        @include('marketing-campaigns.partials.campaign-form-body', [
                            'isCreate' => true,
                            'nextCampaignCode' => $nextCampaignCode ?? null,
                            'selectedCourse' => $selectedCourse ?? null,
                            'sourceTypes' => $sourceTypes,
                            'utmMediumOptions' => $utmMediumOptions,
                        ])

                        <div class="campaign-form-actions rounded px-3 py-3 mt-2 d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox"
                                       id="is_active" name="is_active" value="1"
                                       {{ old('is_active', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    Kampania aktywna
                                </label>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('marketing-campaigns.index') }}" class="btn btn-outline-secondary">
                                    Anuluj
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Zapisz kampanię
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
