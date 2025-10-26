<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Edytuj typ źródła') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h4>Edytuj typ źródła: {{ $marketingSourceType->name }}</h4>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('marketing-source-types.update', $marketingSourceType) }}" method="POST">
                                @csrf
                                @method('PUT')
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nazwa *</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                           id="name" name="name" value="{{ old('name', $marketingSourceType->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="slug" class="form-label">Slug *</label>
                                    <input type="text" class="form-control @error('slug') is-invalid @enderror" 
                                           id="slug" name="slug" value="{{ old('slug', $marketingSourceType->slug) }}" required>
                                    <div class="form-text">Unikalny identyfikator (np. facebook, email)</div>
                                    @error('slug')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Opis</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" 
                                              id="description" name="description" rows="3">{{ old('description', $marketingSourceType->description) }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="color" class="form-label">Kolor *</label>
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color @error('color') is-invalid @enderror" 
                                               id="color" name="color" value="{{ old('color', $marketingSourceType->color) }}" required>
                                        <input type="text" class="form-control @error('color') is-invalid @enderror" 
                                               id="color_text" value="{{ old('color', $marketingSourceType->color) }}" readonly>
                                    </div>
                                    @error('color')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="sort_order" class="form-label">Kolejność sortowania</label>
                                    <input type="number" class="form-control @error('sort_order') is-invalid @enderror" 
                                           id="sort_order" name="sort_order" value="{{ old('sort_order', $marketingSourceType->sort_order) }}" min="0">
                                    @error('sort_order')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" 
                                           {{ old('is_active', $marketingSourceType->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">
                                        Typ źródła jest aktywny
                                    </label>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="{{ route('marketing-source-types.index') }}" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left"></i> Anuluj
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Zaktualizuj typ źródła
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Synchronizacja koloru między input color a text
        document.getElementById('color').addEventListener('input', function() {
            document.getElementById('color_text').value = this.value;
        });
    </script>
</x-app-layout>
