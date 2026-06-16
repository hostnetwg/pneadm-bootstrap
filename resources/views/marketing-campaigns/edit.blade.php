<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Edytuj kampanię marketingową') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container col-lg-9">
            <div class="mb-3 d-flex flex-wrap gap-2">
                <a href="{{ route('marketing-campaigns.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Kampanie
                </a>
                <a href="{{ route('marketing-campaigns.show', $marketingCampaign) }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-eye"></i> Podgląd kampanii
                </a>
            </div>

            <div class="card mb-4 border-success shadow-sm">
                <div class="card-header bg-success-subtle py-2 px-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-link-45deg text-success"></i>
                        <div>
                            <h6 class="mb-0 fw-semibold">Zapisane linki — gotowe do skopiowania</h6>
                            <p class="small text-muted mb-0">Kod <code>{{ $marketingCampaign->campaign_code }}</code> · ustawienia z ostatniego zapisu</p>
                        </div>
                    </div>
                </div>
                <div class="card-body py-3 px-3">
                    @include('marketing-campaigns.partials.campaign-links', [
                        'campaignUrls' => $campaignUrls,
                        'marketingCampaign' => $marketingCampaign,
                        'idPrefix' => 'editCampaign',
                        'showHeading' => false,
                        'compact' => true,
                        'verifyShortLinkUrl' => route('marketing-campaigns.verify-short-link', $marketingCampaign),
                    ])
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h4 class="h5 mb-1 fw-semibold">Edycja kampanii</h4>
                    <p class="small text-muted mb-0">
                        Zmiany w szkoleniu lub UTM wymagają zapisu — linki u góry odświeżą się po przekierowaniu.
                    </p>
                </div>
                <div class="card-body p-3 p-md-4">
                    <form action="{{ route('marketing-campaigns.update', $marketingCampaign) }}" method="POST" id="marketing-campaign-form">
                        @csrf
                        @method('PUT')

                        @include('marketing-campaigns.partials.campaign-form-body', [
                            'isCreate' => false,
                            'marketingCampaign' => $marketingCampaign,
                            'selectedCourse' => $selectedCourse ?? null,
                            'sourceTypes' => $sourceTypes,
                            'utmMediumOptions' => $utmMediumOptions,
                        ])

                        <div class="campaign-form-actions rounded px-3 py-3 mt-2 d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox"
                                       id="is_active" name="is_active" value="1"
                                       {{ old('is_active', $marketingCampaign->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    Kampania aktywna
                                </label>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('marketing-campaigns.show', $marketingCampaign) }}" class="btn btn-outline-secondary">
                                    Anuluj
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Zapisz zmiany
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
