<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Edytuj kampanię marketingową') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">Edytuj kampanię: {{ $marketingCampaign->campaign_code }}</h4>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('marketing-campaigns.update', $marketingCampaign) }}" method="POST">
                                @csrf
                                @method('PUT')
                                
                                <div class="mb-3">
                                    <label for="campaign_code" class="form-label">Kod kampanii <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('campaign_code') is-invalid @enderror" 
                                           id="campaign_code" name="campaign_code" 
                                           value="{{ old('campaign_code', $marketingCampaign->campaign_code) }}" required>
                                    @error('campaign_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">Unikalny kod kampanii (np. 537, fb_001)</div>
                                </div>

                                <div class="mb-3">
                                    <label for="name" class="form-label">Nazwa kampanii <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                           id="name" name="name" 
                                           value="{{ old('name', $marketingCampaign->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Opis</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" 
                                              id="description" name="description" rows="3">{{ old('description', $marketingCampaign->description) }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="source_type_id" class="form-label">Typ źródła <span class="text-danger">*</span></label>
                                    <select class="form-select @error('source_type_id') is-invalid @enderror" 
                                            id="source_type_id" name="source_type_id" required>
                                        <option value="">Wybierz typ źródła</option>
                                        @foreach($sourceTypes as $sourceType)
                                            <option value="{{ $sourceType->id }}" {{ old('source_type_id', $marketingCampaign->source_type_id) == $sourceType->id ? 'selected' : '' }}>
                                                {{ $sourceType->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('source_type_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               id="is_active" name="is_active" value="1" 
                                               {{ old('is_active', $marketingCampaign->is_active) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_active">
                                            Kampania aktywna
                                        </label>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="{{ route('marketing-campaigns.index') }}" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left"></i> Powrót
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Zaktualizuj kampanię
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
