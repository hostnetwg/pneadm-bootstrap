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
                </form>
                <a href="{{ route('online-courses.create') }}" class="btn btn-primary">Nowy kurs online</a>
            </div>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tytuł</th>
                            <th>Slug</th>
                            <th>Moduły/lekcje</th>
                            <th>Dostępy</th>
                            <th>Aktywny</th>
                            <th>W panelu PNEDU</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($courses as $course)
                            <tr>
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
                                <td>{{ $course->is_active ? 'Tak' : 'Nie' }}</td>
                                <td>{{ $course->visible_in_dashboard ? 'Tak' : 'Nie' }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('online-courses.edit', $course) }}" class="btn btn-sm btn-outline-primary">Edycja</a>
                                    <a href="{{ route('online-courses.enrollments.index', $course) }}" class="btn btn-sm btn-outline-secondary">Dostępy</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-muted">Brak kursów online.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $courses->links() }}
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
</x-app-layout>
