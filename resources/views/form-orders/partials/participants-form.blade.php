@php
    $maxParticipants = (int) ($maxParticipants ?? config('form_orders.max_participants', 50));
    $unitPrice = $unitPrice ?? null;
    $prefillRows = old('participants');
    if (! is_array($prefillRows) || $prefillRows === []) {
        $prefillRows = $participantsPrefill ?? null;
    }
    if (! is_array($prefillRows) || $prefillRows === []) {
        $prefillRows = [[
            'first_name' => old('participant_firstname', old('participant_first_name', '')),
            'last_name' => old('participant_lastname', old('participant_last_name', '')),
            'email' => old('participant_email', ''),
        ]];
    }
@endphp

<div id="order-form-participants-root"
     data-max-participants="{{ $maxParticipants }}"
     data-unit-price="{{ $unitPrice !== null && $unitPrice !== '' ? number_format((float) $unitPrice, 2, '.', '') : '' }}">
    <div id="order-form-participant-rows">
        @foreach($prefillRows as $idx => $row)
            @include('form-orders.partials.participants-form-row', [
                'index' => $idx,
                'row' => $row,
            ])
        @endforeach
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
        <button type="button" class="btn btn-outline-success btn-sm" id="order-form-add-participant">
            <i class="bi bi-person-plus"></i> Dodaj kolejnego uczestnika
        </button>
        <div class="ms-auto text-end">
            <span class="small text-muted" id="order-form-price-breakdown"></span>
        </div>
    </div>

    <template id="order-form-participant-row-template">
        @include('form-orders.partials.participants-form-row', [
            'index' => '__INDEX__',
            'row' => ['first_name' => '', 'last_name' => '', 'email' => ''],
            'isTemplate' => true,
        ])
    </template>
</div>
