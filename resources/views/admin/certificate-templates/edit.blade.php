<x-app-layout>
    <x-slot name="header">
        Edycja Szablonu: {{ $certificateTemplate->name }}
    </x-slot>

    <div class="container-fluid">
        <form action="{{ route('admin.certificate-templates.update', $certificateTemplate) }}" method="POST" id="template-form">
            @csrf
            @method('PUT')

            <div class="row">
                <div class="col-md-8">
                    <!-- Podstawowe informacje -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="bi bi-info-circle me-2"></i>Podstawowe Informacje
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nazwa szablonu <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control @error('name') is-invalid @enderror" 
                                       id="name" 
                                       name="name" 
                                       value="{{ old('name', $certificateTemplate->name) }}" 
                                       required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="slug" class="form-label">Slug</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="slug" 
                                       value="{{ $certificateTemplate->slug }}" 
                                       disabled>
                                <small class="text-muted">Slug nie może być zmieniony. Nazwa pliku: <code>{{ $certificateTemplate->slug }}.blade.php</code></small>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Opis</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" 
                                          id="description" 
                                          name="description" 
                                          rows="3">{{ old('description', $certificateTemplate->description) }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="is_active" 
                                       name="is_active" 
                                       value="1"
                                       {{ old('is_active', $certificateTemplate->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    Szablon aktywny
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Ustawienia wyglądu -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="bi bi-palette me-2"></i>Ustawienia Wyglądu
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="font_family" class="form-label">Czcionka</label>
                                    <select class="form-select" id="font_family" name="font_family">
                                        <option value="DejaVu Sans" {{ old('font_family', $certificateTemplate->config['settings']['font_family'] ?? 'DejaVu Sans') == 'DejaVu Sans' ? 'selected' : '' }}>DejaVu Sans</option>
                                        <option value="DejaVu Serif" {{ old('font_family', $certificateTemplate->config['settings']['font_family'] ?? '') == 'DejaVu Serif' ? 'selected' : '' }}>DejaVu Serif</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="orientation" class="form-label">Orientacja</label>
                                    <select class="form-select" id="orientation" name="orientation">
                                        <option value="portrait" {{ old('orientation', $certificateTemplate->config['settings']['orientation'] ?? 'portrait') == 'portrait' ? 'selected' : '' }}>Pionowa</option>
                                        <option value="landscape" {{ old('orientation', $certificateTemplate->config['settings']['orientation'] ?? '') == 'landscape' ? 'selected' : '' }}>Pozioma</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="title_size" class="form-label">Rozmiar tytułu (px)</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="title_size" 
                                           name="title_size" 
                                           value="{{ old('title_size', $certificateTemplate->config['settings']['title_size'] ?? 38) }}" 
                                           min="10" 
                                           max="100">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="title_color" class="form-label">Kolor tytułu</label>
                                    <input type="color" 
                                           class="form-control form-control-color" 
                                           id="title_color" 
                                           name="title_color" 
                                           value="{{ old('title_color', $certificateTemplate->config['settings']['title_color'] ?? '#000000') }}">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="course_title_size" class="form-label">Rozmiar tytułu kursu (px)</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="course_title_size" 
                                           name="course_title_size" 
                                           value="{{ old('course_title_size', $certificateTemplate->config['settings']['course_title_size'] ?? 32) }}" 
                                           min="10" 
                                           max="100">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bloki szablonu -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-grid-3x3 me-2"></i>Bloki Szablonu
                            </div>
                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addBlockModal">
                                <i class="bi bi-plus-circle me-1"></i>Dodaj Blok
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="blocks-container" class="mb-3">
                                @if(!empty($certificateTemplate->config['blocks']))
                                    @foreach($certificateTemplate->config['blocks'] as $blockId => $block)
                                        @php
                                            $blockType = $block['type'] ?? '';
                                            $blockData = $availableBlocks[$blockType] ?? null;
                                        @endphp
                                        @if($blockData)
                                            <div class="card mb-3 block-item" data-block-id="{{ $blockId }}">
                                                <div class="card-header d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <i class="bi bi-grip-vertical me-2"></i>
                                                        <strong>{{ $blockData['name'] }}</strong>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-danger remove-block-btn">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                                <div class="card-body">
                                                    <input type="hidden" name="blocks[{{ $blockId }}][type]" value="{{ $blockType }}">
                                                    
                                                    @foreach($blockData['fields'] ?? [] as $fieldName => $fieldConfig)
                                                        <div class="mb-3">
                                                            <label for="{{ $blockId }}_{{ $fieldName }}" class="form-label">{{ $fieldConfig['label'] }}</label>
                                                            
                                                            @if($fieldConfig['type'] === 'text')
                                                                @if($fieldName === 'logo_path')
                                                                    <div class="input-group">
                                                                        <input type="text" 
                                                                               class="form-control" 
                                                                               id="{{ $blockId }}_{{ $fieldName }}" 
                                                                               name="blocks[{{ $blockId }}][config][{{ $fieldName }}]" 
                                                                               value="{{ $block['config'][$fieldName] ?? $fieldConfig['default'] ?? '' }}"
                                                                               readonly>
                                                                        <button type="button" 
                                                                                class="btn btn-outline-secondary" 
                                                                                onclick="openLogoGallery('blocks[{{ $blockId }}][config][{{ $fieldName }}]')">
                                                                            <i class="bi bi-image me-1"></i>Wybierz logo
                                                                        </button>
                                                                    </div>
                                                                    <div id="{{ $blockId }}_logo_preview">
                                                                        @if(!empty($block['config'][$fieldName]))
                                                                            <img src="{{ asset('storage/' . $block['config'][$fieldName]) }}" 
                                                                                 alt="Logo" 
                                                                                 style="max-width: 150px; margin-top: 10px;" 
                                                                                 class="img-thumbnail">
                                                                        @endif
                                                                    </div>
                                                                @else
                                                                    <input type="text" 
                                                                           class="form-control" 
                                                                           id="{{ $blockId }}_{{ $fieldName }}" 
                                                                           name="blocks[{{ $blockId }}][config][{{ $fieldName }}]" 
                                                                           value="{{ $block['config'][$fieldName] ?? $fieldConfig['default'] ?? '' }}">
                                                                @endif
                                                            @elseif($fieldConfig['type'] === 'number')
                                                                <input type="number" 
                                                                       class="form-control" 
                                                                       id="{{ $blockId }}_{{ $fieldName }}" 
                                                                       name="blocks[{{ $blockId }}][config][{{ $fieldName }}]" 
                                                                       value="{{ $block['config'][$fieldName] ?? $fieldConfig['default'] ?? '' }}">
                                                            @elseif($fieldConfig['type'] === 'textarea')
                                                                <textarea class="form-control" 
                                                                          id="{{ $blockId }}_{{ $fieldName }}" 
                                                                          name="blocks[{{ $blockId }}][config][{{ $fieldName }}]" 
                                                                          rows="3">{{ $block['config'][$fieldName] ?? $fieldConfig['default'] ?? '' }}</textarea>
                                                            @elseif($fieldConfig['type'] === 'checkbox')
                                                                <div class="form-check">
                                                                    <input class="form-check-input" 
                                                                           type="checkbox" 
                                                                           id="{{ $blockId }}_{{ $fieldName }}" 
                                                                           name="blocks[{{ $blockId }}][config][{{ $fieldName }}]" 
                                                                           value="1"
                                                                           {{ !empty($block['config'][$fieldName]) ? 'checked' : '' }}>
                                                                    <label class="form-check-label" for="{{ $blockId }}_{{ $fieldName }}">
                                                                        {{ $fieldConfig['label'] }}
                                                                    </label>
                                                                </div>
                                                            @elseif($fieldConfig['type'] === 'select')
                                                                <select class="form-select" 
                                                                        id="{{ $blockId }}_{{ $fieldName }}" 
                                                                        name="blocks[{{ $blockId }}][config][{{ $fieldName }}]">
                                                                    @foreach($fieldConfig['options'] ?? [] as $value => $label)
                                                                        <option value="{{ $value }}" 
                                                                                {{ ($block['config'][$fieldName] ?? $fieldConfig['default'] ?? '') == $value ? 'selected' : '' }}>
                                                                            {{ $label }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                @else
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Dodaj bloki do szablonu klikając "Dodaj Blok" powyżej.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Akcje -->
                    <div class="card mb-4 position-sticky" style="top: 20px;">
                        <div class="card-header">
                            <i class="bi bi-lightning me-2"></i>Akcje
                        </div>
                        <div class="card-body">
                            <button type="submit" class="btn btn-primary w-100 mb-2">
                                <i class="bi bi-save me-1"></i>Zapisz Zmiany
                            </button>
                            <a href="{{ route('admin.certificate-templates.preview', $certificateTemplate) }}" 
                               class="btn btn-info w-100 mb-2" 
                               target="_blank">
                                <i class="bi bi-eye me-1"></i>Podgląd
                            </a>
                            <a href="{{ route('admin.certificate-templates.index') }}" class="btn btn-secondary w-100">
                                <i class="bi bi-x-circle me-1"></i>Anuluj
                            </a>
                        </div>
                    </div>

                    <!-- Informacje -->
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-info-circle me-2"></i>Informacje
                        </div>
                        <div class="card-body">
                            <p class="small mb-2"><strong>Utworzono:</strong><br>{{ $certificateTemplate->created_at->format('d.m.Y H:i') }}</p>
                            <p class="small mb-2"><strong>Ostatnia modyfikacja:</strong><br>{{ $certificateTemplate->updated_at->format('d.m.Y H:i') }}</p>
                            <p class="small mb-0"><strong>Używany przez kursów:</strong> {{ $certificateTemplate->courses()->count() }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Modal dodawania bloku - taki sam jak w create -->
    <div class="modal fade" id="addBlockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Dodaj Blok</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group">
                        @foreach($availableBlocks as $type => $block)
                            <a href="#" 
                               class="list-group-item list-group-item-action add-block-btn" 
                               data-block-type="{{ $type }}"
                               data-block-name="{{ $block['name'] }}"
                               data-bs-dismiss="modal">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">{{ $block['name'] }}</h6>
                                </div>
                                <p class="mb-1 small text-muted">{{ $block['description'] }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal galerii logo -->
    <div class="modal fade" id="logoGalleryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Galeria Logo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Upload nowego logo -->
                    <div class="mb-4">
                        <label class="form-label"><strong>Dodaj nowe logo</strong></label>
                        <div class="input-group">
                            <input type="file" class="form-control" id="logo-upload-input" accept="image/*">
                            <button type="button" class="btn btn-primary" id="upload-logo-btn">
                                <i class="bi bi-upload me-1"></i>Wgraj
                            </button>
                        </div>
                        <small class="text-muted">Dozwolone: JPG, PNG, GIF, SVG (max 2MB)</small>
                        <div id="upload-progress" class="mt-2" style="display:none;">
                            <div class="progress">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Galeria istniejących logo -->
                    <div>
                        <label class="form-label"><strong>Dostępne logo</strong></label>
                        <div class="row g-3" id="logos-gallery">
                            @if(count($availableLogos) > 0)
                                @foreach($availableLogos as $logo)
                                    <div class="col-md-3">
                                        <div class="card h-100 logo-item" data-logo-path="{{ $logo['path'] }}">
                                            <img src="{{ $logo['url'] }}" class="card-img-top" alt="{{ $logo['name'] }}" style="height: 150px; object-fit: contain; padding: 10px;">
                                            <div class="card-body p-2">
                                                <p class="card-text small mb-1">{{ $logo['name'] }}</p>
                                                <p class="card-text small text-muted">{{ round($logo['size']/1024, 1) }} KB</p>
                                                <button type="button" class="btn btn-sm btn-success select-logo-btn w-100 mb-1" data-logo-path="{{ $logo['path'] }}" data-logo-url="{{ $logo['url'] }}">
                                                    <i class="bi bi-check-circle me-1"></i>Wybierz
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger delete-logo-btn w-100" data-logo-path="{{ $logo['path'] }}">
                                                    <i class="bi bi-trash me-1"></i>Usuń
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>Brak dostępnych logo. Wgraj pierwsze logo używając powyższego formularza.
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        console.log('=== SKRYPT ROZPOCZĘTY ===');
        
        // Globalne zmienne i funkcje (muszą być poza DOMContentLoaded dla onclick)
        let blockCounter = {{ count($certificateTemplate->config['blocks'] ?? []) }};
        const availableBlocks = @json($availableBlocks);
        let currentLogoField = null;
        
        console.log('Zmienne zainicjalizowane - blockCounter:', blockCounter);
        
        // Funkcja globalna dla onclick w HTML
        window.openLogoGallery = function(fieldName) {
            console.log('openLogoGallery wywołana dla:', fieldName);
            currentLogoField = fieldName;
            const modal = new bootstrap.Modal(document.getElementById('logoGalleryModal'));
            modal.show();
        };

        document.addEventListener('DOMContentLoaded', function() {
            console.log('=== DOM LOADED ===');
            console.log('Available blocks:', availableBlocks);

            // Delegacja eventów dla usuwania bloków (działa dla istniejących i nowych)
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-block-btn') || e.target.closest('.remove-block-btn')) {
                    e.preventDefault();
                    const btn = e.target.classList.contains('remove-block-btn') ? e.target : e.target.closest('.remove-block-btn');
                    const blockItem = btn.closest('.block-item');
                    
                    if (blockItem && confirm('Czy na pewno chcesz usunąć ten blok?')) {
                        blockItem.remove();
                        
                        const container = document.getElementById('blocks-container');
                        if (!container.querySelector('.block-item')) {
                            container.innerHTML = `
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Dodaj bloki do szablonu klikając "Dodaj Blok" powyżej.
                                </div>
                            `;
                        }
                        console.log('Blok usunięty');
                    }
                }
            });

            // Event listeners dla dodawania bloków
            document.querySelectorAll('.add-block-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const blockType = this.dataset.blockType;
                    const blockName = this.dataset.blockName;
                    console.log('Dodaję blok:', blockType, blockName);
                    addBlock(blockType, blockName);
                });
            });

        function addBlock(type, name) {
            const blockId = 'block_' + blockCounter++;
            const blockData = availableBlocks[type];
            
            let html = `
                <div class="card mb-3 block-item" data-block-id="${blockId}">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-grip-vertical me-2"></i>
                            <strong>${name}</strong>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger remove-block-btn">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        <input type="hidden" name="blocks[${blockId}][type]" value="${type}">
            `;

            if (blockData.fields && Object.keys(blockData.fields).length > 0) {
                for (const [fieldName, fieldConfig] of Object.entries(blockData.fields)) {
                    html += renderField(blockId, fieldName, fieldConfig);
                }
            }

            html += `
                    </div>
                </div>
            `;

            const container = document.getElementById('blocks-container');
            const infoAlert = container.querySelector('.alert-info');
            if (infoAlert) {
                infoAlert.remove();
            }
            
            container.insertAdjacentHTML('beforeend', html);
            console.log('Blok dodany:', blockId);
            
            // Nie dodajemy event listenera tutaj - delegacja eventów obsługuje to globalnie
        }

        function renderField(blockId, fieldName, config) {
            const fullName = `blocks[${blockId}][config][${fieldName}]`;
            const id = `${blockId}_${fieldName}`;
            
            let html = `<div class="mb-3">`;
            html += `<label for="${id}" class="form-label">${config.label}</label>`;

            switch (config.type) {
                case 'text':
                    // Specjalna obsługa dla pola logo_path
                    if (fieldName === 'logo_path') {
                        html += `<div class="input-group">`;
                        html += `<input type="text" class="form-control" id="${id}" name="${fullName}" value="${config.default || ''}" readonly>`;
                        html += `<button type="button" class="btn btn-outline-secondary" onclick="openLogoGallery('${fullName}')">`;
                        html += `<i class="bi bi-image me-1"></i>Wybierz logo`;
                        html += `</button>`;
                        html += `</div>`;
                        html += `<div id="${id.replace('logo_path', 'logo_preview')}"></div>`;
                    } else {
                        html += `<input type="text" class="form-control" id="${id}" name="${fullName}" value="${config.default || ''}">`;
                    }
                    break;
                case 'number':
                    html += `<input type="number" class="form-control" id="${id}" name="${fullName}" value="${config.default || ''}">`;
                    break;
                case 'textarea':
                    html += `<textarea class="form-control" id="${id}" name="${fullName}" rows="3">${config.default || ''}</textarea>`;
                    break;
                case 'checkbox':
                    const checked = config.default ? 'checked' : '';
                    html += `
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="${id}" name="${fullName}" value="1" ${checked}>
                            <label class="form-check-label" for="${id}">${config.label}</label>
                        </div>
                    `;
                    break;
                case 'select':
                    html += `<select class="form-select" id="${id}" name="${fullName}">`;
                    for (const [value, label] of Object.entries(config.options || {})) {
                        const selected = config.default === value ? 'selected' : '';
                        html += `<option value="${value}" ${selected}>${label}</option>`;
                    }
                    html += `</select>`;
                    break;
            }

            html += `</div>`;
            return html;
        }

        // ===== Obsługa Logo =====
        // currentLogoField jest już zadeklarowana globalnie na górze

        // Upload logo
        document.getElementById('upload-logo-btn').addEventListener('click', function() {
            const fileInput = document.getElementById('logo-upload-input');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Wybierz plik');
                return;
            }

            const formData = new FormData();
            formData.append('logo', file);
            formData.append('_token', '{{ csrf_token() }}');

            document.getElementById('upload-progress').style.display = 'block';

            fetch('{{ route('admin.certificate-templates.upload-logo') }}', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('upload-progress').style.display = 'none';
                
                if (data.success) {
                    // Dodaj nowe logo do galerii
                    addLogoToGallery(data.path, data.url, data.name);
                    fileInput.value = '';
                    alert('Logo zostało wgrane!');
                } else {
                    alert('Błąd: ' + (data.message || 'Nieznany błąd'));
                }
            })
            .catch(error => {
                document.getElementById('upload-progress').style.display = 'none';
                alert('Błąd uploadu: ' + error);
            });
        });

        // Usuwanie logo
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('delete-logo-btn') || e.target.closest('.delete-logo-btn')) {
                const btn = e.target.classList.contains('delete-logo-btn') ? e.target : e.target.closest('.delete-logo-btn');
                const logoPath = btn.dataset.logoPath;
                
                if (!confirm('Czy na pewno chcesz usunąć to logo?')) {
                    return;
                }

                fetch('{{ route('admin.certificate-templates.delete-logo') }}', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ path: logoPath })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        btn.closest('.col-md-3').remove();
                        alert('Logo zostało usunięte');
                    } else {
                        alert('Błąd: ' + (data.message || 'Nie można usunąć'));
                    }
                })
                .catch(error => {
                    alert('Błąd: ' + error);
                });
            }
        });

        // Wybieranie logo
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('select-logo-btn') || e.target.closest('.select-logo-btn')) {
                const btn = e.target.classList.contains('select-logo-btn') ? e.target : e.target.closest('.select-logo-btn');
                const logoPath = btn.dataset.logoPath;
                const logoUrl = btn.dataset.logoUrl;
                
                if (currentLogoField) {
                    // Ustaw ścieżkę logo w polu
                    document.querySelector(`[name="${currentLogoField}"]`).value = logoPath;
                    
                    // Pokaż podgląd
                    const previewId = currentLogoField.replace('logo_path', 'logo_preview');
                    const preview = document.getElementById(previewId);
                    if (preview) {
                        preview.innerHTML = `<img src="${logoUrl}" alt="Logo" style="max-width: 150px; margin-top: 10px;" class="img-thumbnail">`;
                    }
                    
                    // Zamknij modal
                    bootstrap.Modal.getInstance(document.getElementById('logoGalleryModal')).hide();
                }
            }
        });

        // Dodawanie nowego logo do galerii
        function addLogoToGallery(path, url, name) {
            const gallery = document.getElementById('logos-gallery');
            
            const html = `
                <div class="col-md-3">
                    <div class="card h-100 logo-item" data-logo-path="${path}">
                        <img src="${url}" class="card-img-top" alt="${name}" style="height: 150px; object-fit: contain; padding: 10px;">
                        <div class="card-body p-2">
                            <p class="card-text small mb-1">${name}</p>
                            <button type="button" class="btn btn-sm btn-success select-logo-btn w-100 mb-1" data-logo-path="${path}" data-logo-url="${url}">
                                <i class="bi bi-check-circle me-1"></i>Wybierz
                            </button>
                            <button type="button" class="btn btn-sm btn-danger delete-logo-btn w-100" data-logo-path="${path}">
                                <i class="bi bi-trash me-1"></i>Usuń
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            const infoAlert = gallery.querySelector('.alert-info');
            if (infoAlert) {
                infoAlert.remove();
            }
            
            gallery.insertAdjacentHTML('beforeend', html);
        }

        // openLogoGallery jest już zdefiniowana globalnie na górze skryptu
            
        console.log('=== INICJALIZACJA ZAKOŃCZONA ===');
        }); // Koniec DOMContentLoaded
        
        console.log('=== SKRYPT ZAKOŃCZONY (poza DOMContentLoaded) ===');
    </script>
    @endpush
</x-app-layout>

