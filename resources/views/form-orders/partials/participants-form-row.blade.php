@php
    $isTemplate = $isTemplate ?? false;
    $idx = $index;
    $first = $row['first_name'] ?? '';
    $last = $row['last_name'] ?? '';
    $email = $row['email'] ?? '';
@endphp
<div class="order-form-participant-row border rounded p-2 mb-2 {{ $idx == 0 || $idx === '__INDEX__' ? 'bg-light' : '' }}"
     data-participant-index="{{ $idx }}">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <strong class="small text-uppercase text-muted mb-0">
            Uczestnik <span class="js-participant-number">{{ $isTemplate ? '' : ((int) $idx + 1) }}</span>
        </strong>
        <button type="button" class="btn btn-outline-danger btn-sm js-remove-participant" @if(! $isTemplate && (int) $idx === 0) hidden @endif title="Usuń uczestnika">
            <i class="bi bi-trash"></i> Usuń
        </button>
    </div>
    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label" for="{{ ((int) $idx === 0 && ! $isTemplate) ? 'participant_first_name' : 'participant_first_name_'.$idx }}">
                Imię <span class="text-danger">*</span>
            </label>
            <input type="text"
                   class="form-control @error('participants.'.$idx.'.first_name') is-invalid @enderror @error('participant_firstname') is-invalid @enderror"
                   id="{{ ((int) $idx === 0 && ! $isTemplate) ? 'participant_first_name' : 'participant_first_name_'.$idx }}"
                   name="participants[{{ $idx }}][first_name]"
                   value="{{ $first }}"
                   autocomplete="given-name"
                   @if(! $isTemplate) required @endif
                   data-primary-field="first_name">
            @if(! $isTemplate && (int) $idx === 0)
                @error('participant_firstname')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @error('participants.0.first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @endif
        </div>
        <div class="col-md-3">
            <label class="form-label" for="{{ ((int) $idx === 0 && ! $isTemplate) ? 'participant_last_name' : 'participant_last_name_'.$idx }}">
                Nazwisko <span class="text-danger">*</span>
            </label>
            <input type="text"
                   class="form-control @error('participants.'.$idx.'.last_name') is-invalid @enderror @error('participant_lastname') is-invalid @enderror"
                   id="{{ ((int) $idx === 0 && ! $isTemplate) ? 'participant_last_name' : 'participant_last_name_'.$idx }}"
                   name="participants[{{ $idx }}][last_name]"
                   value="{{ $last }}"
                   autocomplete="family-name"
                   @if(! $isTemplate) required @endif
                   data-primary-field="last_name">
            @if(! $isTemplate && (int) $idx === 0)
                @error('participant_lastname')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @error('participants.0.last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @endif
        </div>
        <div class="col-md-6">
            <label class="form-label" for="{{ ((int) $idx === 0 && ! $isTemplate) ? 'participant_email' : 'participant_email_'.$idx }}">
                E-mail — dane dostępowe do szkolenia <span class="text-danger">*</span>
            </label>
            <input type="email"
                   class="form-control js-participant-email @error('participants.'.$idx.'.email') is-invalid @enderror @error('participant_email') is-invalid @enderror"
                   id="{{ ((int) $idx === 0 && ! $isTemplate) ? 'participant_email' : 'participant_email_'.$idx }}"
                   name="participants[{{ $idx }}][email]"
                   value="{{ $email }}"
                   autocomplete="email"
                   @if(! $isTemplate) required @endif
                   data-primary-field="email">
            <div class="invalid-feedback js-email-feedback"></div>
            @if(! $isTemplate && (int) $idx === 0)
                @error('participant_email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                @error('participants.0.email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            @elseif(! $isTemplate)
                @error('participants.'.$idx.'.email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            @endif
        </div>
    </div>
</div>
