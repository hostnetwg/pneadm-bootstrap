<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">Kursy online (nagrania)</h2>
    </x-slot>
    <style>
        .online-course-thumb-preview {
            cursor: zoom-in;
        }
        #online-course-thumb-preview-float {
            position: fixed;
            z-index: 1080;
            pointer-events: none;
            padding: 6px;
            background: #fff;
            border: 1px solid var(--bs-border-color);
            border-radius: 0.375rem;
            box-shadow: 0 0.5rem 1.25rem rgba(0, 0, 0, 0.18);
        }
        #online-course-thumb-preview-float img {
            display: block;
            max-width: min(320px, 90vw);
            max-height: min(320px, 70vh);
            width: auto;
            height: auto;
            object-fit: contain;
        }
    </style>
    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <form method="get" action="{{ route('online-courses.index') }}" class="d-flex gap-2">
                    <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Szukaj tytułu, slug, ID Publigo…">
                    <button type="submit" class="btn btn-outline-secondary">Szukaj</button>
                    @if($q !== '')
                        <a href="{{ route('online-courses.index') }}" class="btn btn-outline-secondary">Wyczyść</a>
                    @endif
                </form>
                <a href="{{ route('online-courses.create') }}" class="btn btn-primary">Nowy kurs online</a>
            </div>
            @if($canReorder)
                <div id="online-courses-reorder-root"
                     class="alert alert-light border mb-3"
                     data-reorder-url="{{ route('online-courses.reorder') }}">
                    <p class="mb-2">
                        <i class="bi bi-arrows-move text-primary"></i>
                        <strong>Kolejność na /kursy:</strong> przeciągnij wiersze albo użyj strzałek.
                        Na pnedu.pl najpierw widać kursy ze sprzedażą, potem wyłączone — w tej kolejności.
                    </p>
                    <div id="online-courses-reorder-toast"
                         class="alert alert-success alert-dismissible fade show d-none mb-0 py-2"
                         role="alert">
                        <span data-toast-body></span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                    </div>
                </div>
            @else
                <p class="text-muted small mb-3">Żeby zmienić kolejność na /kursy, wyczyść wyszukiwanie.</p>
            @endif
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            @if($canReorder)
                                <th class="text-center" style="width: 5rem;" title="Kolejność na publicznym /kursy">Kolej.</th>
                            @endif
                            <th>ID</th>
                            <th>Tytuł</th>
                            <th>Slug</th>
                            <th>Moduły/lekcje</th>
                            <th>Dostępy</th>
                            <th>Konfiguracja sprzedaży</th>
                            <th>Aktywny</th>
                            <th>W panelu PNEDU</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="{{ $canReorder ? 'online-courses-sortable' : '' }}">
                        @forelse($courses as $course)
                            <tr class="{{ $canReorder ? 'online-course-row' : '' }}"
                                @if($canReorder) data-course-id="{{ $course->id }}" @endif>
                                @if($canReorder)
                                    <td class="text-center text-nowrap">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-secondary btn-online-course-move-up"
                                                title="Wyżej"
                                                aria-label="Przesuń wyżej">
                                            <i class="bi bi-chevron-up"></i>
                                        </button>
                                        <span class="online-course-drag-handle d-inline-flex align-items-center justify-content-center mx-1 text-muted"
                                              title="Przeciągnij"
                                              style="cursor: grab;">
                                            <i class="bi bi-grip-vertical"></i>
                                        </span>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-secondary btn-online-course-move-down"
                                                title="Niżej"
                                                aria-label="Przesuń niżej">
                                            <i class="bi bi-chevron-down"></i>
                                        </button>
                                    </td>
                                @endif
                                <td>{{ $course->id }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if(!empty($course->image))
                                            @php $imageUrl = asset('storage/'.$course->image); @endphp
                                            <img src="{{ $imageUrl }}"
                                                 alt="{{ $course->title }}"
                                                 class="img-thumbnail flex-shrink-0 online-course-thumb-preview"
                                                 width="96"
                                                 height="96"
                                                 style="object-fit: cover;"
                                                 data-oc-thumb-preview
                                                 data-preview-src="{{ $imageUrl }}">
                                        @endif
                                        <span>{{ $course->title }}</span>
                                    </div>
                                </td>
                                <td><code>{{ $course->slug }}</code></td>
                                <td>{{ $course->modules_count }}/{{ $course->lessons_count }}</td>
                                <td>{{ $course->enrollments_count }}</td>
                                <td>
                                    @php
                                        $salesProduct = $course->salesProduct;
                                        $salesOffer = $salesProduct?->defaultOffer;
                                        $activePriceCount = $salesOffer?->prices?->where('is_active', true)->count() ?? 0;
                                    @endphp
                                    @if($salesProduct?->is_active && $salesOffer?->is_active)
                                        <span class="badge text-bg-success">Gotowa</span>
                                        <small class="text-muted d-block">{{ $activePriceCount }} cen(y)</small>
                                    @elseif($salesProduct)
                                        <span class="badge text-bg-secondary">Wyłączona</span>
                                    @else
                                        <span class="badge text-bg-warning">Brak konfiguracji</span>
                                    @endif
                                </td>
                                <td>{{ $course->is_active ? 'Tak' : 'Nie' }}</td>
                                <td>{{ $course->visible_in_dashboard ? 'Tak' : 'Nie' }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('online-courses.edit', $course) }}" class="btn btn-sm btn-outline-primary">Edycja</a>
                                    <a href="{{ route('online-courses.sales.edit', $course) }}" class="btn btn-sm btn-outline-success">Sprzedaż</a>
                                    <a href="{{ route('online-courses.enrollments.index', $course) }}" class="btn btn-sm btn-outline-secondary">Dostępy</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canReorder ? 10 : 9 }}" class="text-muted">Brak kursów online.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(! $canReorder)
                {{ $courses->links() }}
            @endif
        </div>
    </div>

    <div id="online-course-thumb-preview-float" hidden aria-hidden="true">
        <img src="" alt="">
    </div>

    @push('scripts')
    <script>
        (function () {
            const floatEl = document.getElementById('online-course-thumb-preview-float');
            if (!floatEl) {
                return;
            }
            const floatImg = floatEl.querySelector('img');
            const thumbs = document.querySelectorAll('[data-oc-thumb-preview]');
            let activeThumb = null;

            function positionPreview(thumb) {
                const rect = thumb.getBoundingClientRect();
                const pad = 12;
                floatEl.hidden = false;
                const floatRect = floatEl.getBoundingClientRect();
                let left = rect.right + pad;
                let top = rect.top + (rect.height / 2) - (floatRect.height / 2);

                if (left + floatRect.width > window.innerWidth - pad) {
                    left = rect.left - pad - floatRect.width;
                }
                if (top < pad) {
                    top = pad;
                }
                if (top + floatRect.height > window.innerHeight - pad) {
                    top = window.innerHeight - pad - floatRect.height;
                }

                floatEl.style.left = left + 'px';
                floatEl.style.top = top + 'px';
            }

            thumbs.forEach(function (thumb) {
                thumb.addEventListener('mouseenter', function () {
                    activeThumb = thumb;
                    floatImg.src = thumb.dataset.previewSrc || thumb.src;
                    floatImg.alt = thumb.alt || '';
                    floatEl.hidden = false;
                    const place = function () {
                        if (activeThumb === thumb) {
                            positionPreview(thumb);
                        }
                    };
                    if (floatImg.complete) {
                        place();
                    } else {
                        floatImg.onload = place;
                    }
                });
                thumb.addEventListener('mouseleave', function () {
                    activeThumb = null;
                    floatEl.hidden = true;
                    floatImg.removeAttribute('src');
                });
                thumb.addEventListener('mousemove', function () {
                    if (activeThumb === thumb) {
                        positionPreview(thumb);
                    }
                });
            });
        })();
    </script>
    @endpush
    @include('online-courses.partials.index-sortable')
</x-app-layout>
