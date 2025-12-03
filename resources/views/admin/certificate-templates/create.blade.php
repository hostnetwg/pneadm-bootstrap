<x-app-layout>
    <x-slot name="header">
        @if(isset($templateToClone))
            Klonowanie Szablonu: {{ $templateToClone->name }}
        @else
            Nowy Szablon Certyfikatu
        @endif
    </x-slot>

    <div class="container-fluid">
        @if(isset($templateToClone))
            <div class="alert alert-info mb-4">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Klonowanie szablonu:</strong> Formularz został wypełniony danymi z szablonu "{{ $templateToClone->name }}". 
                Zmień nazwę i inne ustawienia według potrzeb, a następnie kliknij "Zapisz zmiany" aby utworzyć kopię.
            </div>
        @endif

        <form action="{{ route('admin.certificate-templates.store') }}" method="POST" id="template-form" enctype="multipart/form-data">
            @csrf

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
                                       value="{{ old('name', isset($templateToClone) ? $templateToClone->name . ' (Kopia)' : '') }}" 
                                       required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Np. "Szablon Nowoczesny", "Szablon Klasyczny"</small>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Opis</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" 
                                          id="description" 
                                          name="description" 
                                          rows="3">{{ old('description', isset($templateToClone) ? $templateToClone->description : '') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="is_active" 
                                       name="is_active" 
                                       value="1"
                                       {{ old('is_active', isset($templateToClone) ? $templateToClone->is_active : true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    Szablon aktywny
                                </label>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="is_default" 
                                       name="is_default" 
                                       value="1"
                                       {{ old('is_default', isset($templateToClone) ? false : false) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_default">
                                    <strong>Domyślny szablon</strong>
                                    <small class="text-muted d-block">Ten szablon będzie używany gdy w szkoleniu wybrano "Domyślny szablon"</small>
                                </label>
                                @if(isset($templateToClone))
                                    <small class="form-text text-muted d-block mt-1">
                                        <i class="bi bi-info-circle"></i> Kopia szablonu nie będzie automatycznie ustawiona jako domyślna, nawet jeśli oryginał był domyślny.
                                    </small>
                                @endif
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
                                        @php
                                            $fontFamily = old('font_family', isset($templateToClone) ? ($templateToClone->config['settings']['font_family'] ?? 'DejaVu Sans') : 'DejaVu Sans');
                                        @endphp
                                        <option value="DejaVu Sans" {{ $fontFamily == 'DejaVu Sans' ? 'selected' : '' }}>DejaVu Sans</option>
                                        <option value="DejaVu Serif" {{ $fontFamily == 'DejaVu Serif' ? 'selected' : '' }}>DejaVu Serif</option>
                                        <option value="DejaVu Sans Mono" {{ $fontFamily == 'DejaVu Sans Mono' ? 'selected' : '' }}>DejaVu Sans Mono</option>
                                    </select>
                                    <small class="form-text text-muted">Wszystkie czcionki obsługują polskie znaki</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="orientation" class="form-label">Orientacja</label>
                                    <select class="form-select" id="orientation" name="orientation">
                                        @php
                                            $orientation = old('orientation', isset($templateToClone) ? ($templateToClone->config['settings']['orientation'] ?? 'portrait') : 'portrait');
                                        @endphp
                                        <option value="portrait" {{ $orientation == 'portrait' ? 'selected' : '' }}>Pionowa</option>
                                        <option value="landscape" {{ $orientation == 'landscape' ? 'selected' : '' }}>Pozioma</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="title_size" class="form-label">Rozmiar tytułu (px)</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="title_size" 
                                           name="title_size" 
                                           value="{{ old('title_size', isset($templateToClone) ? ($templateToClone->config['settings']['title_size'] ?? 38) : 38) }}" 
                                           min="10" 
                                           max="100">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="title_color" class="form-label">Kolor tytułu</label>
                                    <input type="color" 
                                           class="form-control form-control-color" 
                                           id="title_color" 
                                           name="title_color" 
                                           value="{{ old('title_color', isset($templateToClone) ? ($templateToClone->config['settings']['title_color'] ?? '#000000') : '#000000') }}">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="course_title_size" class="form-label">Rozmiar tytułu kursu (px)</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="course_title_size" 
                                           name="course_title_size" 
                                           value="{{ old('course_title_size', isset($templateToClone) ? ($templateToClone->config['settings']['course_title_size'] ?? 32) : 32) }}" 
                                           min="10" 
                                           max="100">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="participant_name_size" class="form-label">Rozmiar imienia i nazwiska (px)</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="participant_name_size" 
                                           name="participant_name_size" 
                                           value="{{ old('participant_name_size', isset($templateToClone) ? ($templateToClone->config['settings']['participant_name_size'] ?? 24) : 24) }}" 
                                           min="10" 
                                           max="100">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="participant_name_font" class="form-label">Czcionka imienia i nazwiska</label>
                                    <select class="form-select" id="participant_name_font" name="participant_name_font">
                                        @php
                                            $participantNameFont = old('participant_name_font', isset($templateToClone) ? ($templateToClone->config['settings']['participant_name_font'] ?? 'DejaVu Sans') : 'DejaVu Sans');
                                        @endphp
                                        <option value="DejaVu Sans" {{ $participantNameFont == 'DejaVu Sans' ? 'selected' : '' }}>DejaVu Sans</option>
                                        <option value="DejaVu Serif" {{ $participantNameFont == 'DejaVu Serif' ? 'selected' : '' }}>DejaVu Serif</option>
                                        <option value="DejaVu Sans Mono" {{ $participantNameFont == 'DejaVu Sans Mono' ? 'selected' : '' }}>DejaVu Sans Mono</option>
                                    </select>
                                    <small class="form-text text-muted">Wszystkie czcionki obsługują polskie znaki</small>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="participant_name_italic" class="form-label">Styl tekstu</label>
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="participant_name_italic" 
                                               name="participant_name_italic" 
                                               value="1"
                                               {{ old('participant_name_italic', isset($templateToClone) ? ($templateToClone->config['settings']['participant_name_italic'] ?? false) : false) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="participant_name_italic">
                                            Pochylenie (kursywa)
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="show_certificate_number" 
                                               name="show_certificate_number" 
                                               value="1"
                                               {{ old('show_certificate_number', isset($templateToClone) ? ($templateToClone->config['settings']['show_certificate_number'] ?? true) : true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="show_certificate_number">
                                            Pokaż numer rejestru w certyfikacie
                                        </label>
                                        <small class="form-text text-muted d-block">
                                            Jeśli odznaczone, numer rejestru nie będzie wyświetlany w certyfikacie.
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <h6 class="mb-3">Marginesy dokumentu</h6>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="margin_top" class="form-label">Góra (px)</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="margin_top" 
                                           name="margin_top" 
                                           value="{{ old('margin_top', isset($templateToClone) ? ($templateToClone->config['settings']['margin_top'] ?? 10) : 10) }}" 
                                           min="0" 
                                           max="200">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="margin_bottom" class="form-label">Dół (px)</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="margin_bottom" 
                                           name="margin_bottom" 
                                           value="{{ old('margin_bottom', isset($templateToClone) ? ($templateToClone->config['settings']['margin_bottom'] ?? 10) : 10) }}" 
                                           min="0" 
                                           max="200">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="margin_left" class="form-label">Lewy margines (px)</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="margin_left" 
                                           name="margin_left" 
                                           value="{{ old('margin_left', isset($templateToClone) ? ($templateToClone->config['settings']['margin_left'] ?? 50) : 50) }}" 
                                           min="0" 
                                           max="200">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="margin_right" class="form-label">Prawy margines (px)</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="margin_right" 
                                           name="margin_right" 
                                           value="{{ old('margin_right', isset($templateToClone) ? ($templateToClone->config['settings']['margin_right'] ?? 50) : 50) }}" 
                                           min="0" 
                                           max="200">
                                </div>
                            </div>
                            <small class="form-text text-muted">
                                Marginesy określają odstępy od krawędzi dokumentu. Tekst automatycznie dostosuje się do ustawionych marginesów. Ustaw 0px, aby tekst był przy samej krawędzi.
                            </small>
                            
                            <hr class="my-4">
                            
                            <h6 class="mb-3">Tło zaświadczenia</h6>
                            <div class="mb-3">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="show_background" 
                                           name="show_background" 
                                           value="1"
                                           {{ old('show_background', isset($templateToClone) ? ($templateToClone->config['settings']['show_background'] ?? false) : false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="show_background">
                                        Użyj tła zaświadczenia
                                    </label>
                                    <small class="form-text text-muted d-block">
                                        Zaznacz, aby wyświetlać grafikę tła na zaświadczeniu (jeśli jest wczytana).
                                    </small>
                                </div>
                                
                                <label for="background_image" class="form-label">Grafika tła (gilosz)</label>
                                <div class="input-group mb-2">
                                    <input type="text" 
                                           class="form-control" 
                                           id="background_image" 
                                           name="background_image" 
                                           value="{{ old('background_image', isset($templateToClone) ? ($templateToClone->config['settings']['background_image'] ?? '') : '') }}"
                                           readonly
                                           placeholder="Wybierz tło z galerii">
                                    <button type="button" 
                                            class="btn btn-outline-secondary" 
                                            onclick="openBackgroundGallery()">
                                        <i class="bi bi-images me-1"></i>Wybierz tło
                                    </button>
                                </div>
                                <small class="form-text text-muted">
                                    Wybierz grafikę tła (np. gilosz) z galerii lub wgraj nową. Zalecane formaty: PNG, JPG. Maksymalny rozmiar: 5MB.
                                </small>
                            </div>

                        </div>
                    </div>

                    <!-- Bloki szablonu -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-grid-3x3 me-2"></i>Bloki Szablonu
                                <small class="text-muted ms-2">(przeciągnij, aby zmienić kolejność)</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addBlockModal">
                                <i class="bi bi-plus-circle me-1"></i>Dodaj Blok
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="blocks-container" class="mb-3 sortable-blocks">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Dodaj bloki do szablonu klikając "Dodaj Blok" powyżej.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Akcje -->
                    <div class="card mb-4 position-sticky" style="top: 20px; z-index: 1030;">
                        <div class="card-header">
                            <i class="bi bi-lightning me-2"></i>Akcje
                        </div>
                        <div class="card-body">
                            <button type="submit" class="btn btn-primary w-100 mb-2">
                                <i class="bi bi-save me-1"></i>Zapisz Szablon
                            </button>
                            <a href="{{ route('admin.certificate-templates.index') }}" class="btn btn-secondary w-100">
                                <i class="bi bi-x-circle me-1"></i>Anuluj
                            </a>
                        </div>
                    </div>

                    <!-- Pomoc -->
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-question-circle me-2"></i>Pomoc
                        </div>
                        <div class="card-body">
                            <p class="small mb-2"><strong>Dostępne bloki:</strong></p>
                            <ul class="small">
                                <li>Nagłówek - tytuł i logo</li>
                                <li>Dane uczestnika - imię, nazwisko</li>
                                <li>Info o kursie - temat, organizator</li>
                                <li>Podpis prowadzącego</li>
                                <li>Stopka</li>
                                <li>Własny tekst</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Modal dodawania bloku -->
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
    <!-- SortableJS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    @if(isset($templateToClone))
    <script>
        // Przekaż konfigurację szablonu do sklonowania do JavaScript
        const templateToCloneConfig = @json($templateToClone->config ?? []);
    </script>
    @endif
    
    <style>
        .sortable-blocks .block-item {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .sortable-blocks .block-item.sortable-chosen {
            opacity: 0.5;
        }
        
        .sortable-blocks .block-item.sortable-ghost {
            opacity: 0.3;
            background-color: #f0f0f0;
        }
        
        .sortable-blocks .block-item.sortable-drag {
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }
        
        .drag-handle:hover {
            background-color: #f8f9fa;
        }
        
        .drag-handle .bi-grip-vertical {
            cursor: grab;
        }
        
        .drag-handle.dragging .bi-grip-vertical {
            cursor: grabbing;
        }
    </style>
    
    <script>
        console.log('=== SKRYPT CREATE ROZPOCZĘTY ===');
        
        // Globalne zmienne i funkcje
        let blockCounter = 0;
        const availableBlocks = @json($availableBlocks);
        let currentLogoField = null;
        let sortable = null;
        
        console.log('Zmienne zainicjalizowane - availableBlocks:', availableBlocks);
        
        // Funkcja globalna dla onclick w HTML
        window.openLogoGallery = function(fieldName) {
            console.log('openLogoGallery wywołana dla:', fieldName);
            currentLogoField = fieldName;
            const modal = new bootstrap.Modal(document.getElementById('logoGalleryModal'));
            modal.show();
        };
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('=== DOM LOADED (CREATE) ===');
            
            // Jeśli klonujemy szablon, załaduj jego bloki
            @if(isset($templateToClone))
            if (typeof templateToCloneConfig !== 'undefined') {
                console.log('Konfiguracja szablonu do sklonowania:', templateToCloneConfig);
                console.log('Bloki w konfiguracji:', templateToCloneConfig.blocks);
                
                if (templateToCloneConfig.blocks) {
                    // Użyj setTimeout aby upewnić się, że funkcja loadBlocksFromConfig jest już zdefiniowana
                    setTimeout(() => {
                        loadBlocksFromConfig(templateToCloneConfig.blocks);
                    }, 100);
                } else {
                    console.warn('Brak bloków w konfiguracji szablonu do sklonowania');
                }
            } else {
                console.warn('templateToCloneConfig nie jest zdefiniowany');
            }
            @endif
            
            // Inicjalizacja Sortable dla drag & drop bloków
            initializeSortable();
            
            // Delegacja eventów dla usuwania bloków
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
                            // Zniszcz Sortable, bo nie ma bloków
                            if (sortable) {
                                sortable.destroy();
                                sortable = null;
                            }
                        } else {
                            // Reinicjalizuj Sortable po usunięciu bloku
                            if (sortable) {
                                sortable.destroy();
                            }
                            initializeSortable();
                        }
                        console.log('Blok usunięty');
                    }
                }
            });

            document.querySelectorAll('.add-block-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const blockType = this.dataset.blockType;
                    const blockName = this.dataset.blockName;
                    console.log('Dodaję blok:', blockType, blockName);
                    addBlock(blockType, blockName);
                });
            });

        function addBlock(type, name, blockConfig = null) {
            const blockId = 'block_' + blockCounter++;
            const blockData = availableBlocks[type];
            
            let html = `
                <div class="card mb-3 block-item" data-block-id="${blockId}">
                    <div class="card-header d-flex justify-content-between align-items-center drag-handle" style="cursor: move;">
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
                        <input type="hidden" name="blocks[${blockId}][order]" value="${blockConfig?.order ?? 999}" class="block-order-input">
            `;

            // Dodawanie pól konfiguracji bloku
            if (blockData.fields && Object.keys(blockData.fields).length > 0) {
                // Pobierz konfigurację bloku (może być w blockConfig.config lub bezpośrednio w blockConfig)
                const blockConfigData = blockConfig?.config || blockConfig || {};
                
                console.log(`Dodawanie pól dla bloku ${blockId} typu ${type}:`, {
                    blockConfig: blockConfig,
                    blockConfigData: blockConfigData,
                    fields: Object.keys(blockData.fields)
                });
                
                for (const [fieldName, fieldConfig] of Object.entries(blockData.fields)) {
                    // Użyj wartości z blockConfigData jeśli istnieje, w przeciwnym razie użyj default
                    const fieldValue = blockConfigData[fieldName] !== undefined ? blockConfigData[fieldName] : (fieldConfig.default ?? '');
                    
                    console.log(`  Pole ${fieldName}: wartość z konfiguracji = ${blockConfigData[fieldName]}, wartość użyta = ${fieldValue}`);
                    
                    html += renderField(blockId, fieldName, fieldConfig, fieldValue);
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
            
            // Reinicjalizuj Sortable po dodaniu nowego bloku
            if (sortable) {
                sortable.destroy();
            }
            initializeSortable();
            
            // Aktualizuj kolejność wszystkich bloków (nowy blok dostanie poprawny order)
            updateBlockOrder();
        }

        function renderField(blockId, fieldName, config, value = null) {
            const fullName = `blocks[${blockId}][config][${fieldName}]`;
            const id = `${blockId}_${fieldName}`;
            const fieldValue = value !== null ? value : (config.default || '');
            
            let html = `<div class="mb-3">`;
            html += `<label for="${id}" class="form-label">${config.label}</label>`;

            switch (config.type) {
                case 'text':
                    // Specjalna obsługa dla pola logo_path
                    if (fieldName === 'logo_path') {
                        html += `<div class="input-group">`;
                        html += `<input type="text" class="form-control" id="${id}" name="${fullName}" value="${fieldValue}" readonly>`;
                        html += `<button type="button" class="btn btn-outline-secondary" onclick="openLogoGallery('${fullName}')">`;
                        html += `<i class="bi bi-image me-1"></i>Wybierz logo`;
                        html += `</button>`;
                        html += `</div>`;
                        html += `<div id="${id.replace('logo_path', 'logo_preview')}"></div>`;
                    } else {
                        html += `<input type="text" class="form-control" id="${id}" name="${fullName}" value="${fieldValue}">`;
                    }
                    break;
                case 'number':
                    html += `<input type="number" class="form-control" id="${id}" name="${fullName}" value="${fieldValue}">`;
                    break;
                case 'textarea':
                    html += `<textarea class="form-control" id="${id}" name="${fullName}" rows="3">${fieldValue}</textarea>`;
                    break;
                case 'checkbox':
                    const checked = fieldValue ? 'checked' : '';
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

            fetch('/api/admin/certificate-templates/upload-logo', {
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

                fetch('/api/admin/certificate-templates/delete-logo', {
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
            const sizeKB = 0; // Nie znamy rozmiaru po uploadu
            
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

        // Funkcja inicjalizująca Sortable
        function initializeSortable() {
            const blocksContainer = document.getElementById('blocks-container');
            
            if (!blocksContainer) {
                console.log('Brak kontenera bloków');
                return;
            }
            
            // Sprawdź czy są jakieś bloki do sortowania
            const blockItems = blocksContainer.querySelectorAll('.block-item');
            if (blockItems.length === 0) {
                console.log('Brak bloków do sortowania');
                return;
            }
            
            sortable = new Sortable(blocksContainer, {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                onStart: function(evt) {
                    evt.item.querySelector('.drag-handle').classList.add('dragging');
                },
                onEnd: function(evt) {
                    evt.item.querySelector('.drag-handle').classList.remove('dragging');
                    console.log('Blok przeniesiony z pozycji', evt.oldIndex, 'na pozycję', evt.newIndex);
                    
                    // Aktualizuj kolejność wszystkich bloków
                    updateBlockOrder();
                }
            });
            
            console.log('Sortable zainicjalizowany dla', blockItems.length, 'bloków');
        }

        // Funkcja aktualizująca pole 'order' dla wszystkich bloków
        function updateBlockOrder() {
            const blocksContainer = document.getElementById('blocks-container');
            const blockItems = blocksContainer.querySelectorAll('.block-item');
            
            blockItems.forEach((blockItem, index) => {
                const orderInput = blockItem.querySelector('.block-order-input');
                if (orderInput) {
                    orderInput.value = index;
                    console.log('Blok', blockItem.dataset.blockId, 'ma teraz order:', index);
                }
            });
        }

        // openLogoGallery jest już zdefiniowana globalnie na górze skryptu
        
        // Funkcja do ładowania bloków z konfiguracji (używana przy klonowaniu)
        function loadBlocksFromConfig(blocks) {
            console.log('loadBlocksFromConfig wywołana z:', blocks);
            
            if (!blocks) {
                console.log('Brak bloków do załadowania - blocks jest null/undefined');
                return;
            }

            // Konwertuj obiekt na tablicę jeśli potrzeba
            let blocksArray = [];
            if (Array.isArray(blocks)) {
                blocksArray = blocks;
            } else if (typeof blocks === 'object') {
                // To jest obiekt (associative array) - konwertuj na tablicę
                blocksArray = Object.values(blocks);
            } else {
                console.error('Nieprawidłowy format bloków:', typeof blocks);
                return;
            }

            if (blocksArray.length === 0) {
                console.log('Brak bloków do załadowania - pusta tablica');
                return;
            }

            const container = document.getElementById('blocks-container');
            const infoAlert = container.querySelector('.alert-info');
            if (infoAlert) {
                infoAlert.remove();
            }

            // Sortuj bloki według order
            const sortedBlocks = [...blocksArray].sort((a, b) => {
                const orderA = a.order ?? 999;
                const orderB = b.order ?? 999;
                return orderA - orderB;
            });

            console.log('Sortowanie bloków:', sortedBlocks);

            sortedBlocks.forEach((block, index) => {
                const blockType = block.type;
                const blockData = availableBlocks[blockType];
                
                console.log(`Przetwarzanie bloku ${index + 1}/${sortedBlocks.length}:`, {
                    type: blockType,
                    config: block.config,
                    order: block.order
                });
                
                if (blockData) {
                    const blockName = blockData.name || blockType;
                    // Przekaż cały obiekt block jako trzeci parametr - zawiera type, order i config
                    addBlock(blockType, blockName, block);
                } else {
                    console.warn('Nieznany typ bloku:', blockType, 'Dostępne typy:', Object.keys(availableBlocks));
                }
            });

            console.log('Załadowano', sortedBlocks.length, 'bloków z konfiguracji');
        }
        
        console.log('=== INICJALIZACJA ZAKOŃCZONA (CREATE) ===');
        }); // Koniec DOMContentLoaded
        
        console.log('=== SKRYPT ZAKOŃCZONY (CREATE) ===');
    </script>

    <!-- Modal galerii tła -->
    <div class="modal fade" id="backgroundGalleryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Galeria Tła</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Upload nowego tła -->
                    <div class="mb-4">
                        <label class="form-label"><strong>Dodaj nowe tło</strong></label>
                        <div class="input-group">
                            <input type="file" class="form-control" id="background-upload-input" accept="image/*">
                            <button type="button" class="btn btn-primary" id="upload-background-btn">
                                <i class="bi bi-upload me-1"></i>Wgraj
                            </button>
                        </div>
                        <small class="text-muted">Dozwolone: JPG, PNG, GIF (max 5MB)</small>
                        <div id="background-upload-progress" class="mt-2" style="display:none;">
                            <div class="progress">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Galeria istniejących tła -->
                    <div>
                        <label class="form-label"><strong>Dostępne tła</strong></label>
                        <div class="row g-3" id="backgrounds-gallery">
                            @if(count($availableBackgrounds ?? []) > 0)
                                @foreach($availableBackgrounds as $background)
                                    <div class="col-md-3">
                                        <div class="card h-100 background-item" data-background-path="{{ $background['path'] }}">
                                            <img src="{{ $background['url'] }}" class="card-img-top" alt="{{ $background['name'] }}" style="height: 150px; object-fit: cover; padding: 10px;">
                                            <div class="card-body p-2">
                                                <p class="card-text small mb-1">{{ $background['name'] }}</p>
                                                <p class="card-text small text-muted">{{ round($background['size']/1024, 1) }} KB</p>
                                                <button type="button" class="btn btn-sm btn-success select-background-btn w-100 mb-1" data-background-path="{{ $background['path'] }}" data-background-url="{{ $background['url'] }}">
                                                    <i class="bi bi-check-circle me-1"></i>Wybierz
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger delete-background-btn w-100" data-background-path="{{ $background['path'] }}">
                                                    <i class="bi bi-trash me-1"></i>Usuń
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>Brak dostępnych tła. Wgraj pierwsze tło używając powyższego formularza.
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ===== Obsługa Tła =====
        
        // Funkcja otwierająca galerię tła
        window.openBackgroundGallery = function() {
            const modalElement = document.getElementById('backgroundGalleryModal');
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        };

        // Upload tła
        document.getElementById('upload-background-btn').addEventListener('click', function() {
            const fileInput = document.getElementById('background-upload-input');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Wybierz plik');
                return;
            }

            const formData = new FormData();
            formData.append('background', file);
            formData.append('_token', '{{ csrf_token() }}');

            document.getElementById('background-upload-progress').style.display = 'block';

            fetch('/api/admin/certificate-templates/upload-background', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('background-upload-progress').style.display = 'none';
                
                if (data.success) {
                    // Dodaj nowe tło do galerii
                    addBackgroundToGallery(data.path, data.url, data.name);
                    fileInput.value = '';
                    alert('Tło zostało wgrane!');
                } else {
                    alert('Błąd: ' + (data.message || 'Nieznany błąd'));
                }
            })
            .catch(error => {
                document.getElementById('background-upload-progress').style.display = 'none';
                alert('Błąd uploadu: ' + error);
            });
        });

        // Usuwanie tła
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('delete-background-btn') || e.target.closest('.delete-background-btn')) {
                const btn = e.target.classList.contains('delete-background-btn') ? e.target : e.target.closest('.delete-background-btn');
                const backgroundPath = btn.dataset.backgroundPath;
                
                if (!confirm('Czy na pewno chcesz usunąć to tło?')) {
                    return;
                }

                fetch('/api/admin/certificate-templates/delete-background', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ path: backgroundPath })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        btn.closest('.col-md-3').remove();
                        alert('Tło zostało usunięte');
                    } else {
                        alert('Błąd: ' + (data.message || 'Nie można usunąć'));
                    }
                })
                .catch(error => {
                    alert('Błąd: ' + error);
                });
            }
        });

        // Wybieranie tła
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('select-background-btn') || e.target.closest('.select-background-btn')) {
                const btn = e.target.classList.contains('select-background-btn') ? e.target : e.target.closest('.select-background-btn');
                const backgroundPath = btn.dataset.backgroundPath;
                
                // Ustaw ścieżkę tła w polu
                document.getElementById('background_image').value = backgroundPath;
                
                // Zamknij modal
                bootstrap.Modal.getInstance(document.getElementById('backgroundGalleryModal')).hide();
            }
        });

        // Dodawanie nowego tła do galerii
        function addBackgroundToGallery(path, url, name) {
            const gallery = document.getElementById('backgrounds-gallery');
            
            const html = `
                <div class="col-md-3">
                    <div class="card h-100 background-item" data-background-path="${path}">
                        <img src="${url}" class="card-img-top" alt="${name}" style="height: 150px; object-fit: cover; padding: 10px;">
                        <div class="card-body p-2">
                            <p class="card-text small mb-1">${name}</p>
                            <button type="button" class="btn btn-sm btn-success select-background-btn w-100 mb-1" data-background-path="${path}" data-background-url="${url}">
                                <i class="bi bi-check-circle me-1"></i>Wybierz
                            </button>
                            <button type="button" class="btn btn-sm btn-danger delete-background-btn w-100" data-background-path="${path}">
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
    </script>

    @endpush
</x-app-layout>

