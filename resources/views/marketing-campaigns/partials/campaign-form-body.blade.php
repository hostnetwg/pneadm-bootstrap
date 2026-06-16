@php
    $isCreate = $isCreate ?? true;
    $marketingCampaign = $marketingCampaign ?? null;
    $landingDefault = $isCreate
        ? 'order_form'
        : ($marketingCampaign->landing_target ?? 'order_form');
    $selectedSourceTypeId = old(
        'source_type_id',
        $isCreate ? null : $marketingCampaign->source_type_id,
    );
    $rawUtmContent = old('utm_content', $isCreate ? null : $marketingCampaign->utm_content);
    $typeDefaultContent = filled($selectedSourceTypeId)
        ? $sourceTypes->firstWhere('id', (int) $selectedSourceTypeId)?->default_utm_content
        : null;
    $effectiveUtmContent = filled($rawUtmContent) ? $rawUtmContent : $typeDefaultContent;
    $sourceTypeUtmDefaults = $sourceTypes->mapWithKeys(fn ($type) => [
        $type->id => [
            'utm_source' => $type->utm_source ?? '',
            'default_utm_medium' => $type->default_utm_medium ?: 'paid',
            'default_utm_content' => $type->default_utm_content ?? '',
        ],
    ]);
@endphp

@include('marketing-campaigns.partials.campaign-form-styles')

<x-campaign-form-section
    :step="1"
    title="Link docelowy"
    subtitle="Szkolenie, kod kampanii i strona, na którą prowadzi URL."
    accent="primary">
    <div class="row g-3">
        <div class="col-12">
            @include('marketing-campaigns.partials.campaign-code-fields', [
                'isCreate' => $isCreate,
                'nextCampaignCode' => $nextCampaignCode ?? null,
                'campaignCodeDefault' => $isCreate ? '' : $marketingCampaign->campaign_code,
            ])
        </div>
        <div class="col-12">
            @include('marketing-campaigns.partials.course-select-fields', [
                'selectedCourse' => $selectedCourse ?? null,
                'showEarlyPickHint' => true,
            ])
        </div>
        <div class="col-12">
            @include('marketing-campaigns.partials.campaign-landing-target-fields', [
                'landingDefault' => $landingDefault,
            ])
        </div>
    </div>
</x-campaign-form-section>

<x-campaign-form-section
    :step="2"
    title="Nazwa i notatki"
    subtitle="Etykiety widoczne w adm — nie trafiają do linku."
    accent="secondary">
    @include('marketing-campaigns.partials.campaign-name-description-fields', [
        'nameDefault' => $isCreate ? '' : $marketingCampaign->name,
        'descriptionDefault' => $isCreate ? '' : $marketingCampaign->description,
    ])
</x-campaign-form-section>

<x-campaign-form-section
    :step="3"
    title="Kanał i parametry UTM"
    subtitle="Skąd przychodzi ruch — wartości w generowanym linku."
    accent="info">
    <div class="row g-3">
        <div class="col-12">
            @include('marketing-campaigns.partials.campaign-source-type-fields', [
                'sourceTypes' => $sourceTypes,
                'selectedSourceTypeId' => $selectedSourceTypeId,
            ])
        </div>
        <div class="col-12">
            @include('marketing-campaigns.partials.utm-medium-fields', [
                'sourceTypes' => $sourceTypes,
                'utmMediumOptions' => $utmMediumOptions,
                'selectedSourceTypeId' => $selectedSourceTypeId,
                'currentUtmMedium' => old('utm_medium', $isCreate ? null : $marketingCampaign->utm_medium),
            ])
        </div>
        <div class="col-12">
            @include('marketing-campaigns.partials.utm-content-fields', [
                'currentUtmContent' => $effectiveUtmContent,
                'sourceTypeDefaults' => $sourceTypeUtmDefaults,
            ])
        </div>
    </div>
</x-campaign-form-section>

<x-campaign-form-section
    :step="4"
    title="Podgląd linków"
    subtitle="Sprawdź adres przed zapisaniem i skopiowaniem do newslettera lub reklamy."
    accent="success"
    class="mb-0">
    @include('marketing-campaigns.partials.campaign-link-live-preview', [
        'sourceTypes' => $sourceTypes,
        'isDraft' => $isCreate,
    ])
</x-campaign-form-section>
