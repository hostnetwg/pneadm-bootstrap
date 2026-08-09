<x-app-layout>
    <x-slot name="header">
        Szablony ankiet
    </x-slot>

    <div class="py-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <p class="text-muted mb-0">
                Szablon = zestaw pytań kopiowany do szkolenia przy tworzeniu ankiety natywnej.
                <a href="{{ route('settings.surveys.edit') }}">Ustawienia ankiet</a>
            </p>
            <a href="{{ route('surveys.testimonials.index') }}" class="btn btn-outline-primary btn-sm">Rekomendacje (homepage)</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nazwa</th>
                            <th>Slug</th>
                            <th>Pytań</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($templates as $template)
                            <tr>
                                <td>
                                    <strong>{{ $template->name }}</strong>
                                    @if($template->is_default)
                                        <span class="badge bg-primary ms-1">Domyślny</span>
                                    @endif
                                </td>
                                <td><code>{{ $template->slug }}</code></td>
                                <td>{{ $template->questions_count }}</td>
                                <td>
                                    @if($template->is_active)
                                        <span class="badge bg-success">Aktywny</span>
                                    @else
                                        <span class="badge bg-secondary">Wyłączony</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('surveys.templates.edit', $template) }}" class="btn btn-sm btn-outline-primary">
                                        Edytuj pytania
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-muted text-center py-4">Brak szablonów. Uruchom migracje.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
