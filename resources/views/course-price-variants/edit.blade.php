<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Edytuj wariant cenowy') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('courses.index') }}">Kursy</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('courses.show', $course->id) }}">{{ $course->title }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edytuj wariant cenowy</li>
                </ol>
            </nav>

            @if(session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif

            <form action="{{ route('courses.price-variants.update', [$course->id, $variant->id]) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Podstawowe informacje</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nazwa wariantu <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name', $variant->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Opis</label>
                            <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror" 
                                      rows="3">{{ old('description', $variant->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="price" class="form-label">Cena (PLN) <span class="text-danger">*</span></label>
                                    <input type="number" name="price" id="price" step="0.01" min="0" 
                                           class="form-control @error('price') is-invalid @enderror" 
                                           value="{{ old('price', $variant->price) }}" required>
                                    @error('price')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" 
                                               {{ old('is_active', $variant->is_active) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_active">
                                            Wariant aktywny
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Promocja -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Promocja</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_promotion" id="is_promotion" value="1" 
                                       {{ old('is_promotion', $variant->is_promotion) ? 'checked' : '' }} onchange="togglePromotionFields()">
                                <label class="form-check-label" for="is_promotion">
                                    Włącz promocję
                                </label>
                            </div>
                        </div>

                        <div id="promotionFields" style="display: {{ old('is_promotion', $variant->is_promotion) ? 'block' : 'none' }};">
                            <div class="mb-3">
                                <label for="promotion_price" class="form-label">Cena promocyjna (PLN)</label>
                                <input type="number" name="promotion_price" id="promotion_price" step="0.01" min="0" 
                                       class="form-control @error('promotion_price') is-invalid @enderror" 
                                       value="{{ old('promotion_price', $variant->promotion_price) }}">
                                @error('promotion_price')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="promotion_type" class="form-label">Typ promocji <span class="text-danger">*</span></label>
                                <select name="promotion_type" id="promotion_type" 
                                        class="form-select @error('promotion_type') is-invalid @enderror" 
                                        onchange="togglePromotionDates()">
                                    <option value="disabled" {{ old('promotion_type', $variant->promotion_type) == 'disabled' ? 'selected' : '' }}>Wyłączona</option>
                                    <option value="unlimited" {{ old('promotion_type', $variant->promotion_type) == 'unlimited' ? 'selected' : '' }}>Bez ram czasowych</option>
                                    <option value="time_limited" {{ old('promotion_type', $variant->promotion_type) == 'time_limited' ? 'selected' : '' }}>Ograniczona czasowo</option>
                                </select>
                                @error('promotion_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div id="promotionDates" style="display: {{ old('promotion_type', $variant->promotion_type) == 'time_limited' ? 'block' : 'none' }};">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="promotion_start" class="form-label">Data i godzina rozpoczęcia</label>
                                            <input type="datetime-local" name="promotion_start" id="promotion_start" 
                                                   class="form-control @error('promotion_start') is-invalid @enderror" 
                                                   value="{{ old('promotion_start', $variant->promotion_start ? $variant->promotion_start->format('Y-m-d\TH:i') : '') }}">
                                            @error('promotion_start')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="promotion_end" class="form-label">Data i godzina zakończenia</label>
                                            <input type="datetime-local" name="promotion_end" id="promotion_end" 
                                                   class="form-control @error('promotion_end') is-invalid @enderror" 
                                                   value="{{ old('promotion_end', $variant->promotion_end ? $variant->promotion_end->format('Y-m-d\TH:i') : '') }}">
                                            @error('promotion_end')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Typ dostępu -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Typ dostępu do kursu</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="access_type" class="form-label">Typ dostępu <span class="text-danger">*</span></label>
                            <select name="access_type" id="access_type" 
                                    class="form-select @error('access_type') is-invalid @enderror" 
                                    onchange="toggleAccessFields()">
                                <option value="1" {{ old('access_type', $variant->access_type) == '1' ? 'selected' : '' }}>
                                    1) Bezterminowy, z natychmiastowym dostępem
                                </option>
                                <option value="2" {{ old('access_type', $variant->access_type) == '2' ? 'selected' : '' }}>
                                    2) Bezterminowy, od określonej daty
                                </option>
                                <option value="3" {{ old('access_type', $variant->access_type) == '3' ? 'selected' : '' }}>
                                    3) Przez określony czas, z natychmiastowym dostępem
                                </option>
                                <option value="4" {{ old('access_type', $variant->access_type) == '4' ? 'selected' : '' }}>
                                    4) Od określonej daty, z ustaloną datą końca
                                </option>
                                <option value="5" {{ old('access_type', $variant->access_type) == '5' ? 'selected' : '' }}>
                                    5) Przez określony czas, od określonej daty
                                </option>
                            </select>
                            @error('access_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Pola dla typów 2, 4, 5 -->
                        <div id="accessDates" style="display: {{ in_array(old('access_type', $variant->access_type), ['2', '4', '5']) ? 'block' : 'none' }};">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="access_start_datetime" class="form-label">Data i godzina startu</label>
                                        <input type="datetime-local" name="access_start_datetime" id="access_start_datetime" 
                                               class="form-control @error('access_start_datetime') is-invalid @enderror" 
                                               value="{{ old('access_start_datetime', $variant->access_start_datetime ? $variant->access_start_datetime->format('Y-m-d\TH:i') : '') }}">
                                        @error('access_start_datetime')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6" id="accessEndDateContainer" style="display: {{ in_array(old('access_type', $variant->access_type), ['2', '4']) ? 'block' : 'none' }};">
                                    <div class="mb-3">
                                        <label for="access_end_datetime" class="form-label">Data i godzina końca</label>
                                        <input type="datetime-local" name="access_end_datetime" id="access_end_datetime" 
                                               class="form-control @error('access_end_datetime') is-invalid @enderror" 
                                               value="{{ old('access_end_datetime', $variant->access_end_datetime ? $variant->access_end_datetime->format('Y-m-d\TH:i') : '') }}">
                                        @error('access_end_datetime')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pola dla typów 3, 5 -->
                        <div id="accessDuration" style="display: {{ in_array(old('access_type', $variant->access_type), ['3', '5']) ? 'block' : 'none' }};">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="access_duration_value" class="form-label">Czas dostępu (ilość)</label>
                                        <input type="number" name="access_duration_value" id="access_duration_value" min="1" 
                                               class="form-control @error('access_duration_value') is-invalid @enderror" 
                                               value="{{ old('access_duration_value', $variant->access_duration_value) }}">
                                        @error('access_duration_value')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="access_duration_unit" class="form-label">Jednostka czasu</label>
                                        <select name="access_duration_unit" id="access_duration_unit" 
                                                class="form-select @error('access_duration_unit') is-invalid @enderror">
                                            <option value="hours" {{ old('access_duration_unit', $variant->access_duration_unit) == 'hours' ? 'selected' : '' }}>Godziny</option>
                                            <option value="days" {{ old('access_duration_unit', $variant->access_duration_unit) == 'days' ? 'selected' : '' }}>Dni</option>
                                            <option value="months" {{ old('access_duration_unit', $variant->access_duration_unit) == 'months' ? 'selected' : '' }}>Miesiące</option>
                                            <option value="years" {{ old('access_duration_unit', $variant->access_duration_unit) == 'years' ? 'selected' : '' }}>Lata</option>
                                        </select>
                                        @error('access_duration_unit')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('courses.show', $course->id) }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Anuluj
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Zapisz zmiany
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePromotionFields() {
            const isPromotion = document.getElementById('is_promotion').checked;
            document.getElementById('promotionFields').style.display = isPromotion ? 'block' : 'none';
            if (!isPromotion) {
                document.getElementById('promotion_type').value = 'disabled';
                togglePromotionDates();
            }
        }

        function togglePromotionDates() {
            const promotionType = document.getElementById('promotion_type').value;
            document.getElementById('promotionDates').style.display = promotionType === 'time_limited' ? 'block' : 'none';
        }

        function toggleAccessFields() {
            const accessType = document.getElementById('access_type').value;
            
            // Pola dat (typy 2, 4, 5)
            const accessDates = document.getElementById('accessDates');
            accessDates.style.display = ['2', '4', '5'].includes(accessType) ? 'block' : 'none';
            
            // Pole daty końca (typy 2, 4)
            const accessEndDateContainer = document.getElementById('accessEndDateContainer');
            accessEndDateContainer.style.display = ['2', '4'].includes(accessType) ? 'block' : 'none';
            
            // Pola czasu dostępu (typy 3, 5)
            const accessDuration = document.getElementById('accessDuration');
            accessDuration.style.display = ['3', '5'].includes(accessType) ? 'block' : 'none';
        }

        // Inicjalizacja przy załadowaniu strony
        document.addEventListener('DOMContentLoaded', function() {
            togglePromotionFields();
            togglePromotionDates();
            toggleAccessFields();
        });
    </script>
</x-app-layout>

