@php
    $hostSource = old('host_source', $defaultHostSource ?? ($hostInstructorId !== null ? \App\Support\GrowthOS\GrowthPeople::HOST_SOURCE_INSTRUCTOR : \App\Support\GrowthOS\GrowthPeople::HOST_SOURCE_MANUAL));
    $selectedHostId = (string) old('host_instructor_id', $hostInstructorId ?? '');
    $selectedVoiceId = (string) old('voice_instructor_id', $voiceInstructorId ?? '');
    $optionLabel = static fn (array $option): string => $option['name'].($option['active'] ? '' : ' (nieaktywny)');
@endphp
<div data-growth-people>
    <fieldset class="mb-3">
        <legend class="form-label fs-6 mb-1">Prowadzący</legend>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="host_source" value="instructor" id="{{ $idPrefix }}_host_source_instructor" @checked($hostSource === 'instructor') data-people-host-source>
            <label class="form-check-label" for="{{ $idPrefix }}_host_source_instructor">Instruktor z bazy</label>
        </div>
        <div class="ms-4 mb-2" data-people-host-instructor>
            <select name="host_instructor_id" id="{{ $idPrefix }}_host_instructor_id" class="form-select form-select-sm @error('host_instructor_id') is-invalid @enderror" aria-label="Instruktor prowadzący">
                <option value="">— wybierz instruktora —</option>
                @foreach($instructorOptions as $option)
                    <option value="{{ $option['id'] }}" @selected($selectedHostId === (string) $option['id'])>{{ $optionLabel($option) }}</option>
                @endforeach
            </select>
            @error('host_instructor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="host_source" value="manual" id="{{ $idPrefix }}_host_source_manual" @checked($hostSource === 'manual') data-people-host-source>
            <label class="form-check-label" for="{{ $idPrefix }}_host_source_manual">Inna osoba (spoza bazy)</label>
        </div>
        <div class="ms-4" data-people-host-manual>
            <input name="host" id="{{ $idPrefix }}_host" class="form-control form-control-sm @error('host') is-invalid @enderror" value="{{ old('host', $hostInstructorId === null ? $hostName : '') }}" maxlength="120" placeholder="Imię i nazwisko" aria-label="Imię i nazwisko prowadzącego">
            @error('host')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </fieldset>

    <div class="mb-1">
        <label for="{{ $idPrefix }}_voice_instructor_id" class="form-label">Głos komunikacji</label>
        <select name="voice_instructor_id" id="{{ $idPrefix }}_voice_instructor_id" class="form-select form-select-sm @error('voice_instructor_id') is-invalid @enderror" data-people-voice>
            <option value="">PNE — neutralnie</option>
            @foreach($instructorOptions as $option)
                <option value="{{ $option['id'] }}" data-has-profile="{{ $option['has_profile'] ? '1' : '0' }}" @selected($selectedVoiceId === (string) $option['id'])>{{ $optionLabel($option) }}</option>
            @endforeach
        </select>
        @error('voice_instructor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="form-text">Czyim stylem AI pisze treści. Nie musi to być prowadzący ani osoba zalogowana.</div>
    <div class="form-text d-none" data-people-voice-same>Ten sam co prowadzący.</div>
    <div class="form-text text-warning-emphasis d-none" data-people-voice-no-profile>Ten instruktor nie ma jeszcze indywidualnego profilu komunikacji. AI użyje głosu PNE.</div>
</div>

@once
    <script>
        document.querySelectorAll('[data-growth-people]').forEach((box) => {
            const hostSelect = box.querySelector('[name="host_instructor_id"]');
            const voiceSelect = box.querySelector('[data-people-voice]');
            const refresh = () => {
                const source = box.querySelector('[data-people-host-source]:checked')?.value;
                box.querySelector('[data-people-host-instructor]').classList.toggle('d-none', source !== 'instructor');
                box.querySelector('[data-people-host-manual]').classList.toggle('d-none', source !== 'manual');
                const voice = voiceSelect.selectedOptions[0];
                box.querySelector('[data-people-voice-same]').classList.toggle('d-none', ! (source === 'instructor' && voiceSelect.value !== '' && voiceSelect.value === hostSelect.value));
                box.querySelector('[data-people-voice-no-profile]').classList.toggle('d-none', ! (voiceSelect.value !== '' && voice?.dataset.hasProfile === '0'));
            };
            box.querySelectorAll('[data-people-host-source]').forEach((input) => input.addEventListener('change', refresh));
            hostSelect.addEventListener('change', refresh);
            voiceSelect.addEventListener('change', refresh);
            refresh();
        });
    </script>
@endonce
