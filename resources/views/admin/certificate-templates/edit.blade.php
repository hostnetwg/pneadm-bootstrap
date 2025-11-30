<x-app-layout>
    <x-slot name="header">
        Edycja Szablonu: {{ $certificateTemplate->name }}
    </x-slot>

    <div class="container-fluid">
        <form action="{{ route('admin.certificate-templates.update', $certificateTemplate) }}" method="POST" id="template-form" enctype="multipart/form-data">
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
                                        <option value="DejaVu Sans Mono" {{ old('font_family', $certificateTemplate->config['settings']['font_family'] ?? '') == 'DejaVu Sans Mono' ? 'selected' : '' }}>DejaVu Sans Mono</option>
                                    </select>
                                    <small class="form-text text-muted">Wszystkie czcionki obsługują polskie znaki</small>
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

                                <div class="col-md-4 mb-3">
                                    <label for="participant_name_size" class="form-label">Rozmiar imienia i nazwiska (px)</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="participant_name_size" 
                                           name="participant_name_size" 
                                           value="{{ old('participant_name_size', $certificateTemplate->config['settings']['participant_name_size'] ?? 24) }}"
                                           min="10" 
                                           max="100">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="participant_name_font" class="form-label">Czcionka imienia i nazwiska</label>
                                    <select class="form-select" id="participant_name_font" name="participant_name_font">
                                        <option value="DejaVu Sans" {{ old('participant_name_font', $certificateTemplate->config['settings']['participant_name_font'] ?? 'DejaVu Sans') == 'DejaVu Sans' ? 'selected' : '' }}>DejaVu Sans</option>
                                        <option value="DejaVu Serif" {{ old('participant_name_font', $certificateTemplate->config['settings']['participant_name_font'] ?? '') == 'DejaVu Serif' ? 'selected' : '' }}>DejaVu Serif</option>
                                        <option value="DejaVu Sans Mono" {{ old('participant_name_font', $certificateTemplate->config['settings']['participant_name_font'] ?? '') == 'DejaVu Sans Mono' ? 'selected' : '' }}>DejaVu Sans Mono</option>
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
                                               {{ old('participant_name_italic', $certificateTemplate->config['settings']['participant_name_italic'] ?? false) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="participant_name_italic">
                                            Pochylenie (kursywa)
                                        </label>
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
                                           value="{{ old('margin_top', $certificateTemplate->config['settings']['margin_top'] ?? 10) }}" 
                                           min="0" 
                                           max="200">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="margin_bottom" class="form-label">Dół (px)</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="margin_bottom" 
                                           name="margin_bottom" 
                                           value="{{ old('margin_bottom', $certificateTemplate->config['settings']['margin_bottom'] ?? 10) }}" 
                                           min="0" 
                                           max="200">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="margin_left" class="form-label">Lewy margines (px)</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="margin_left" 
                                           name="margin_left" 
                                           value="{{ old('margin_left', $certificateTemplate->config['settings']['margin_left'] ?? 50) }}" 
                                           min="0" 
                                           max="200">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="margin_right" class="form-label">Prawy margines (px)</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="margin_right" 
                                           name="margin_right" 
                                           value="{{ old('margin_right', $certificateTemplate->config['settings']['margin_right'] ?? 50) }}" 
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
                                           {{ old('show_background', $certificateTemplate->config['settings']['show_background'] ?? false) ? 'checked' : '' }}>
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
                                           value="{{ old('background_image', $certificateTemplate->config['settings']['background_image'] ?? '') }}"
                                           readonly
                                           placeholder="Wybierz tło z galerii">
                                    <button type="button" 
                                            class="btn btn-outline-secondary" 
                                            onclick="openBackgroundGallery()">
                                        <i class="bi bi-images me-1"></i>Wybierz tło
                                    </button>
                                </div>
                                @if(!empty($certificateTemplate->config['settings']['background_image'] ?? null))
                                    <div class="mt-2">
                                        <p class="mb-1"><strong>Aktualne tło:</strong></p>
                                        <img src="{{ asset('storage/' . $certificateTemplate->config['settings']['background_image']) }}" 
                                             alt="Tło zaświadczenia" 
                                             class="img-thumbnail" 
                                             style="max-width: 300px; max-height: 200px;">
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   id="remove_background" 
                                                   name="remove_background" 
                                                   value="1">
                                            <label class="form-check-label" for="remove_background">
                                                Usuń aktualne tło
                                            </label>
                                        </div>
                                    </div>
                                @endif
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
                                <small class="text-muted ms-2">(użyj strzałek w nagłówku, aby zmienić kolejność)</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addBlockModal">
                                <i class="bi bi-plus-circle me-1"></i>Dodaj Blok
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="blocks-container" class="mb-3 sortable-blocks">
                                @if(!empty($certificateTemplate->config['blocks']))
                                    @php
                                        // Wyodrębnij bloki instructor_signature i footer
                                        $fixedBlocks = [];
                                        $sortableBlocks = [];
                                        
                                        foreach ($certificateTemplate->config['blocks'] as $blockId => $block) {
                                            $blockType = $block['type'] ?? '';
                                            if ($blockType === 'instructor_signature' || $blockType === 'footer') {
                                                $fixedBlocks[$blockId] = $block;
                                            } else {
                                                $sortableBlocks[$blockId] = $block;
                                            }
                                        }
                                        
                                        // Sortuj tylko bloki sortowalne według pola 'order'
                                        $sortedBlocks = collect($sortableBlocks)->sortBy(function($block) {
                                            return $block['order'] ?? 999;
                                        })->toArray();
                                    @endphp
                                    @foreach($sortedBlocks as $blockId => $block)
                                        @php
                                            $blockType = $block['type'] ?? '';
                                            $blockData = $availableBlocks[$blockType] ?? null;
                                        @endphp
                                        @if($blockData)
                                            <div class="card mb-3 block-item" data-block-id="{{ $blockId }}">
                                                <div class="card-header d-flex justify-content-between align-items-center">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary move-block-up" title="Przesuń w górę">
                                                            <i class="bi bi-arrow-up"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary move-block-down" title="Przesuń w dół">
                                                            <i class="bi bi-arrow-down"></i>
                                                        </button>
                                                        <strong>{{ $blockData['name'] }}</strong>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-danger remove-block-btn">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                                <div class="card-body">
                                                    <input type="hidden" name="blocks[{{ $blockId }}][type]" value="{{ $blockType }}">
                                                    <input type="hidden" name="blocks[{{ $blockId }}][order]" value="{{ $block['order'] ?? 0 }}" class="block-order-input">
                                                    
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
                                                                            <div class="mt-2">
                                                                                <img src="{{ asset('storage/' . $block['config'][$fieldName]) }}" 
                                                                                     alt="Logo" 
                                                                                     style="max-width: 150px; margin-top: 10px;" 
                                                                                     class="img-thumbnail">
                                                                                <div class="form-check mt-2">
                                                                                    <input class="form-check-input" 
                                                                                           type="checkbox" 
                                                                                           id="remove_logo_{{ $blockId }}" 
                                                                                           name="blocks[{{ $blockId }}][config][remove_logo]" 
                                                                                           value="1">
                                                                                    <label class="form-check-label" for="remove_logo_{{ $blockId }}">
                                                                                        Usuń aktualne logo
                                                                                    </label>
                                                                                </div>
                                                                            </div>
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

                    <!-- Stałe elementy (Data wystawienia i podpis prowadzącego oraz Stopka) -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="bi bi-pin-angle me-2"></i>Stałe Elementy Zaświadczenia
                            <small class="text-muted ms-2">(zawsze na dole zaświadczenia, kolejność nie ma znaczenia)</small>
                        </div>
                        <div class="card-body">
                            @php
                                $instructorSignatureBlock = null;
                                $footerBlock = null;
                                
                                if (!empty($certificateTemplate->config['blocks'])) {
                                    foreach ($certificateTemplate->config['blocks'] as $blockId => $block) {
                                        if (($block['type'] ?? '') === 'instructor_signature') {
                                            $instructorSignatureBlock = ['id' => $blockId, 'data' => $block];
                                        } elseif (($block['type'] ?? '') === 'footer') {
                                            $footerBlock = ['id' => $blockId, 'data' => $block];
                                        }
                                    }
                                }
                            @endphp
                            
                            <!-- Data wystawienia i podpis prowadzącego -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="show_instructor_signature" 
                                               name="show_instructor_signature" 
                                               value="1"
                                               {{ $instructorSignatureBlock ? 'checked' : '' }}>
                                        <label class="form-check-label fw-bold" for="show_instructor_signature">
                                            Data wystawienia i podpis prowadzącego
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">Wyświetla sekcję "Data" i "Prowadzący" na dole zaświadczenia</small>
                                </div>
                                @if($instructorSignatureBlock)
                                    <div class="card-body" id="instructor-signature-config">
                                        @php
                                            $blockId = $instructorSignatureBlock['id'];
                                            $block = $instructorSignatureBlock['data'];
                                            $blockData = $availableBlocks['instructor_signature'] ?? null;
                                        @endphp
                                        <input type="hidden" name="blocks[{{ $blockId }}][type]" value="instructor_signature">
                                        <input type="hidden" name="blocks[{{ $blockId }}][order]" value="9999">
                                        
                                        {{-- Checkbox "Pokaż numer rejestru w certyfikacie" --}}
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   id="show_certificate_number" 
                                                   name="show_certificate_number" 
                                                   value="1"
                                                   {{ old('show_certificate_number', $certificateTemplate->config['settings']['show_certificate_number'] ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="show_certificate_number">
                                                Pokaż numer rejestru w certyfikacie
                                            </label>
                                            <small class="form-text text-muted d-block">
                                                Jeśli odznaczone, numer rejestru nie będzie wyświetlany w certyfikacie.
                                            </small>
                                        </div>
                                        
                                        @if($blockData)
                                            @foreach($blockData['fields'] ?? [] as $fieldName => $fieldConfig)
                                                <div class="mb-3">
                                                    <label for="{{ $blockId }}_{{ $fieldName }}" class="form-label">{{ $fieldConfig['label'] }}</label>
                                                    @if($fieldConfig['type'] === 'text')
                                                        <input type="text" 
                                                               class="form-control" 
                                                               id="{{ $blockId }}_{{ $fieldName }}" 
                                                               name="blocks[{{ $blockId }}][config][{{ $fieldName }}]" 
                                                               value="{{ $block['config'][$fieldName] ?? $fieldConfig['default'] ?? '' }}">
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
                                                    @endif
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                @else
                                    <div class="card-body" id="instructor-signature-config" style="display: none;">
                                        <input type="hidden" name="blocks[instructor_signature_new][type]" value="instructor_signature">
                                        <input type="hidden" name="blocks[instructor_signature_new][order]" value="9999">
                                        
                                        {{-- Checkbox "Pokaż numer rejestru w certyfikacie" --}}
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   id="show_certificate_number_hidden" 
                                                   name="show_certificate_number" 
                                                   value="1"
                                                   {{ old('show_certificate_number', $certificateTemplate->config['settings']['show_certificate_number'] ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="show_certificate_number_hidden">
                                                Pokaż numer rejestru w certyfikacie
                                            </label>
                                            <small class="form-text text-muted d-block">
                                                Jeśli odznaczone, numer rejestru nie będzie wyświetlany w certyfikacie.
                                            </small>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            
                            <!-- Stopka -->
                            <div class="card">
                                <div class="card-header">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="show_footer" 
                                               name="show_footer" 
                                               value="1"
                                               {{ $footerBlock ? 'checked' : '' }}>
                                        <label class="form-check-label fw-bold" for="show_footer">
                                            Stopka
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">Wyświetla stopkę z logo i tekstem na dole zaświadczenia</small>
                                </div>
                                @if($footerBlock)
                                    <div class="card-body" id="footer-config">
                                        @php
                                            $blockId = $footerBlock['id'];
                                            $block = $footerBlock['data'];
                                            $blockData = $availableBlocks['footer'] ?? null;
                                        @endphp
                                        <input type="hidden" name="blocks[{{ $blockId }}][type]" value="footer">
                                        <input type="hidden" name="blocks[{{ $blockId }}][order]" value="9999">
                                        @if($blockData)
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
                                        @endif
                                    </div>
                                @else
                                    <div class="card-body" id="footer-config" style="display: none;">
                                        <input type="hidden" name="blocks[footer_new][type]" value="footer">
                                        <input type="hidden" name="blocks[footer_new][order]" value="9999">
                                        @php
                                            $blockData = $availableBlocks['footer'] ?? null;
                                        @endphp
                                        @if($blockData)
                                            @foreach($blockData['fields'] ?? [] as $fieldName => $fieldConfig)
                                                <div class="mb-3">
                                                    <label for="footer_new_{{ $fieldName }}" class="form-label">{{ $fieldConfig['label'] }}</label>
                                                    
                                                    @if($fieldConfig['type'] === 'text')
                                                        @if($fieldName === 'logo_path')
                                                            <div class="input-group">
                                                                <input type="text" 
                                                                       class="form-control" 
                                                                       id="footer_new_{{ $fieldName }}" 
                                                                       name="blocks[footer_new][config][{{ $fieldName }}]" 
                                                                       value="{{ $fieldConfig['default'] ?? '' }}"
                                                                       readonly>
                                                                <button type="button" 
                                                                        class="btn btn-outline-secondary" 
                                                                        onclick="openLogoGallery('blocks[footer_new][config][{{ $fieldName }}]')">
                                                                    <i class="bi bi-image me-1"></i>Wybierz logo
                                                                </button>
                                                            </div>
                                                            <div id="footer_new_logo_preview"></div>
                                                        @else
                                                            <input type="text" 
                                                                   class="form-control" 
                                                                   id="footer_new_{{ $fieldName }}" 
                                                                   name="blocks[footer_new][config][{{ $fieldName }}]" 
                                                                   value="{{ $fieldConfig['default'] ?? '' }}">
                                                        @endif
                                                    @elseif($fieldConfig['type'] === 'number')
                                                        <input type="number" 
                                                               class="form-control" 
                                                               id="footer_new_{{ $fieldName }}" 
                                                               name="blocks[footer_new][config][{{ $fieldName }}]" 
                                                               value="{{ $fieldConfig['default'] ?? '' }}">
                                                    @elseif($fieldConfig['type'] === 'textarea')
                                                        <textarea class="form-control" 
                                                                  id="footer_new_{{ $fieldName }}" 
                                                                  name="blocks[footer_new][config][{{ $fieldName }}]" 
                                                                  rows="3">{{ $fieldConfig['default'] ?? '' }}</textarea>
                                                    @elseif($fieldConfig['type'] === 'checkbox')
                                                        <div class="form-check">
                                                            <input class="form-check-input" 
                                                                   type="checkbox" 
                                                                   id="footer_new_{{ $fieldName }}" 
                                                                   name="blocks[footer_new][config][{{ $fieldName }}]" 
                                                                   value="1"
                                                                   {{ !empty($fieldConfig['default']) ? 'checked' : '' }}>
                                                            <label class="form-check-label" for="footer_new_{{ $fieldName }}">
                                                                {{ $fieldConfig['label'] }}
                                                            </label>
                                                        </div>
                                                    @elseif($fieldConfig['type'] === 'select')
                                                        <select class="form-select" 
                                                                id="footer_new_{{ $fieldName }}" 
                                                                name="blocks[footer_new][config][{{ $fieldName }}]">
                                                            @foreach($fieldConfig['options'] ?? [] as $value => $label)
                                                                <option value="{{ $value }}" 
                                                                        {{ ($fieldConfig['default'] ?? '') == $value ? 'selected' : '' }}>
                                                                    {{ $label }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    @endif
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                @endif
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
                            @if($type !== 'instructor_signature' && $type !== 'footer')
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
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal galerii logo -->
    <div class="modal fade" id="logoGalleryModal" tabindex="-1" aria-hidden="true">
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

    @push('scripts')
    <style>
        .block-item {
            transition: transform 0.3s ease, opacity 0.3s ease, box-shadow 0.3s ease;
        }
        
        .block-item.moving {
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            z-index: 10;
        }
        
        .move-block-up:disabled,
        .move-block-down:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .move-block-up:not(:disabled):hover,
        .move-block-down:not(:disabled):hover {
            background-color: #0d6efd;
            color: white;
        }
    </style>
    
    <script>
        // console.log('=== SKRYPT ROZPOCZĘTY ===');
        
        // Globalne zmienne i funkcje (muszą być poza DOMContentLoaded dla onclick)
        let blockCounter = {{ count($certificateTemplate->config['blocks'] ?? []) }};
        const availableBlocks = @json($availableBlocks);
        let currentLogoField = null;
        
        // console.log('Zmienne zainicjalizowane - blockCounter:', blockCounter);
        
        // Funkcja globalna dla onclick w HTML
        window.openLogoGallery = function(fieldName) {
            // console.log('openLogoGallery wywołana dla:', fieldName);
            currentLogoField = fieldName;
            const modalElement = document.getElementById('logoGalleryModal');
            const modal = new bootstrap.Modal(modalElement);
            
            // Usuń aria-hidden gdy modal się otwiera
            modalElement.addEventListener('shown.bs.modal', function() {
                modalElement.removeAttribute('aria-hidden');
                modalElement.setAttribute('aria-modal', 'true');
            });
            
            // Przywróć aria-hidden gdy modal się zamyka
            modalElement.addEventListener('hidden.bs.modal', function() {
                modalElement.setAttribute('aria-hidden', 'true');
                modalElement.removeAttribute('aria-modal');
            });
            
            modal.show();
        };

        document.addEventListener('DOMContentLoaded', function() {
            // console.log('=== DOM LOADED ===');
            // console.log('Available blocks:', availableBlocks);

            // Ustaw początkowe wartości order dla istniejących bloków
            updateBlockOrder();
            
            // Aktualizuj stan przycisków strzałek (góra/dół)
            updateArrowButtons();
            
            // Obsługa checkboxów dla stałych elementów
            const showInstructorSignature = document.getElementById('show_instructor_signature');
            const instructorSignatureConfig = document.getElementById('instructor-signature-config');
            const showFooter = document.getElementById('show_footer');
            const footerConfig = document.getElementById('footer-config');
            
            if (showInstructorSignature && instructorSignatureConfig) {
                // Ustaw początkowy stan
                instructorSignatureConfig.style.display = showInstructorSignature.checked ? 'block' : 'none';
                
                showInstructorSignature.addEventListener('change', function() {
                    instructorSignatureConfig.style.display = this.checked ? 'block' : 'none';
                });
            }
            
            if (showFooter && footerConfig) {
                // Ustaw początkowy stan
                footerConfig.style.display = showFooter.checked ? 'block' : 'none';
                
                showFooter.addEventListener('change', function() {
                    footerConfig.style.display = this.checked ? 'block' : 'none';
                });
            }

            // Delegacja eventów dla usuwania i przesuwania bloków (działa dla istniejących i nowych)
            document.addEventListener('click', function(e) {
                // Obsługa przycisków przesuwania
                if (e.target.classList.contains('move-block-up') || e.target.closest('.move-block-up')) {
                    e.preventDefault();
                    const btn = e.target.classList.contains('move-block-up') ? e.target : e.target.closest('.move-block-up');
                    const blockItem = btn.closest('.block-item');
                    if (blockItem) {
                        moveBlockUp(blockItem);
                    }
                    return;
                }
                
                if (e.target.classList.contains('move-block-down') || e.target.closest('.move-block-down')) {
                    e.preventDefault();
                    const btn = e.target.classList.contains('move-block-down') ? e.target : e.target.closest('.move-block-down');
                    const blockItem = btn.closest('.block-item');
                    if (blockItem) {
                        moveBlockDown(blockItem);
                    }
                    return;
                }
                
                // Obsługa usuwania bloków
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
                        // Aktualizuj kolejność i przyciski
                        updateBlockOrder();
                        updateArrowButtons();
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
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary move-block-up" title="Przesuń w górę">
                                <i class="bi bi-arrow-up"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary move-block-down" title="Przesuń w dół">
                                <i class="bi bi-arrow-down"></i>
                            </button>
                            <strong>${name}</strong>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger remove-block-btn">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        <input type="hidden" name="blocks[${blockId}][type]" value="${type}">
                        <input type="hidden" name="blocks[${blockId}][order]" value="999" class="block-order-input">
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
            
            // Aktualizuj kolejność wszystkich bloków (nowy blok dostanie poprawny order)
            updateBlockOrder();
            updateArrowButtons();
            
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
                        html += `<button type="button" class="btn btn-outline-secondary" onclick="openLogoGallery('${fullName}')" type="button">`;
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
                    
                    // Pokaż podgląd z checkboxem
                    const previewId = currentLogoField.replace('logo_path', 'logo_preview');
                    const preview = document.getElementById(previewId);
                    if (preview) {
                        // Wyciągnij blockId z nazwy pola (np. "blocks[header][config][logo_path]" -> "header")
                        const blockIdMatch = currentLogoField.match(/blocks\[([^\]]+)\]/);
                        const blockId = blockIdMatch ? blockIdMatch[1] : 'unknown';
                        preview.innerHTML = `
                            <div class="mt-2">
                                <img src="${logoUrl}" alt="Logo" style="max-width: 150px; margin-top: 10px;" class="img-thumbnail">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="remove_logo_${blockId}" 
                                           name="blocks[${blockId}][config][remove_logo]" 
                                           value="1">
                                    <label class="form-check-label" for="remove_logo_${blockId}">
                                        Usuń aktualne logo
                                    </label>
                                </div>
                            </div>
                        `;
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
                const backgroundUrl = btn.dataset.backgroundUrl;
                
                // Ustaw ścieżkę tła w polu
                document.getElementById('background_image').value = backgroundPath;
                
                // Pokaż podgląd
                const previewContainer = document.getElementById('background_image').closest('.input-group').nextElementSibling;
                if (previewContainer && previewContainer.classList.contains('mt-2')) {
                    // Jeśli już istnieje podgląd, zaktualizuj go
                    const img = previewContainer.querySelector('img');
                    if (img) {
                        img.src = backgroundUrl;
                    } else {
                        // Utwórz nowy podgląd
                        previewContainer.innerHTML = `
                            <p class="mb-1"><strong>Aktualne tło:</strong></p>
                            <img src="${backgroundUrl}" alt="Tło zaświadczenia" class="img-thumbnail" style="max-width: 300px; max-height: 200px;">
                        `;
                    }
                } else {
                    // Utwórz nowy kontener podglądu
                    const newPreview = document.createElement('div');
                    newPreview.className = 'mt-2';
                    newPreview.innerHTML = `
                        <p class="mb-1"><strong>Aktualne tło:</strong></p>
                        <img src="${backgroundUrl}" alt="Tło zaświadczenia" class="img-thumbnail" style="max-width: 300px; max-height: 200px;">
                    `;
                    document.getElementById('background_image').closest('.input-group').parentNode.insertBefore(newPreview, document.getElementById('background_image').closest('.input-group').nextSibling);
                }
                
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

        // Funkcja przesuwająca blok w górę
        function moveBlockUp(blockItem) {
            const previousBlock = blockItem.previousElementSibling;
            if (previousBlock && previousBlock.classList.contains('block-item')) {
                // Dodaj klasę moving dla efektu wizualnego
                blockItem.classList.add('moving');
                
                // Przesuń blok
                blockItem.parentNode.insertBefore(blockItem, previousBlock);
                
                // Usuń klasę moving po animacji
                setTimeout(() => {
                    blockItem.classList.remove('moving');
                }, 300);
                
                // Aktualizuj kolejność i przyciski
                updateBlockOrder();
                updateArrowButtons();
            }
        }
        
        // Funkcja przesuwająca blok w dół
        function moveBlockDown(blockItem) {
            const nextBlock = blockItem.nextElementSibling;
            if (nextBlock && nextBlock.classList.contains('block-item')) {
                // Dodaj klasę moving dla efektu wizualnego
                blockItem.classList.add('moving');
                
                // Przesuń blok
                blockItem.parentNode.insertBefore(nextBlock, blockItem);
                
                // Usuń klasę moving po animacji
                setTimeout(() => {
                    blockItem.classList.remove('moving');
                }, 300);
                
                // Aktualizuj kolejność i przyciski
                updateBlockOrder();
                updateArrowButtons();
            }
        }
        
        // Funkcja aktualizująca stan przycisków strzałek (góra/dół)
        function updateArrowButtons() {
            const blockItems = document.querySelectorAll('.block-item');
            blockItems.forEach((blockItem, index) => {
                const upBtn = blockItem.querySelector('.move-block-up');
                const downBtn = blockItem.querySelector('.move-block-down');
                
                // Pierwszy blok - wyłącz przycisk "w górę"
                if (upBtn) {
                    upBtn.disabled = (index === 0);
                }
                
                // Ostatni blok - wyłącz przycisk "w dół"
                if (downBtn) {
                    downBtn.disabled = (index === blockItems.length - 1);
                }
            });
        }
        
        // Funkcja aktualizująca pole 'order' dla wszystkich bloków
        function updateBlockOrder() {
            const blocksContainer = document.getElementById('blocks-container');
            const blockItems = blocksContainer.querySelectorAll('.block-item');
            
            blockItems.forEach((blockItem, index) => {
                const orderInput = blockItem.querySelector('.block-order-input');
                if (orderInput) {
                    orderInput.value = index;
                    // console.log('Blok', blockItem.dataset.blockId, 'ma teraz order:', index);
                }
            });
        }

        // openLogoGallery jest już zdefiniowana globalnie na górze skryptu
            
        // console.log('=== INICJALIZACJA ZAKOŃCZONA ===');
        }); // Koniec DOMContentLoaded
        
        // console.log('=== SKRYPT ZAKOŃCZONY (poza DOMContentLoaded) ===');
    </script>
    @endpush
</x-app-layout>

