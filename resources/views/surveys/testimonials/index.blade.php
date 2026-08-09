<x-app-layout>
    <x-slot name="header">
        Rekomendacje (opinie na pnedu.pl)
    </x-slot>

    <div class="py-3">
        <p class="text-muted mb-3">
            Opinie zebrane z bloku „rekomendacja” w ankiecie natywnej. Publikacja na stronie głównej wymaga zgody uczestnika
            oraz Twojego zatwierdzenia.
        </p>

        <div class="mb-3 btn-group">
            <a href="{{ route('surveys.testimonials.index') }}" class="btn btn-sm {{ $filter === '' ? 'btn-primary' : 'btn-outline-primary' }}">Wszystkie</a>
            <a href="{{ route('surveys.testimonials.index', ['filter' => 'pending']) }}" class="btn btn-sm {{ $filter === 'pending' ? 'btn-primary' : 'btn-outline-primary' }}">Do akceptacji</a>
            <a href="{{ route('surveys.testimonials.index', ['filter' => 'published']) }}" class="btn btn-sm {{ $filter === 'published' ? 'btn-primary' : 'btn-outline-primary' }}">Opublikowane</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Autor</th>
                            <th>Opinia</th>
                            <th>Ocena</th>
                            <th>Zgoda</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($testimonials as $t)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if($t->hasAvatar())
                                            <img src="{{ $t->avatarUrl() }}" alt="" width="44" height="44"
                                                 class="rounded-circle flex-shrink-0" style="object-fit:cover;background:#eef2f6;">
                                        @else
                                            <div class="rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center text-white fw-semibold"
                                                 style="width:44px;height:44px;background:#6c757d;font-size:.75rem;"
                                                 title="Brak awatara">{{ $t->initials() }}</div>
                                        @endif
                                        <div>
                                            <strong>{{ $t->author_name }}</strong><br>
                                            <small class="text-muted">{{ $t->subtitle() }}</small>
                                            @if($t->course)
                                                <br><small class="text-muted">{{ Str::limit($t->course->title, 40) }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td style="max-width: 360px;">{{ Str::limit($t->quote, 180) }}</td>
                                <td>{{ $t->rating ? $t->rating.'/5' : '—' }}</td>
                                <td>
                                    @if($t->publish_consent)
                                        <span class="badge bg-success">Tak</span>
                                    @else
                                        <span class="badge bg-secondary">Nie</span>
                                    @endif
                                </td>
                                <td>
                                    @if($t->is_published)
                                        <span class="badge bg-primary">Opublikowana</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Szkic</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @if(!$t->is_published && $t->publish_consent)
                                        <form method="POST" action="{{ route('surveys.testimonials.publish', $t) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-success">Publikuj</button>
                                        </form>
                                    @elseif($t->is_published)
                                        <form method="POST" action="{{ route('surveys.testimonials.unpublish', $t) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary">Ukryj</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('surveys.testimonials.destroy', $t) }}" class="d-inline"
                                          onsubmit="return confirm('Usunąć rekomendację?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Usuń</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Brak rekomendacji w tym filtrze.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($testimonials->hasPages())
                <div class="card-footer">{{ $testimonials->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
