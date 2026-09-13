@php
    $omnibus = app(\App\Services\PriceOmnibusService::class);
    $rows = $omnibus->history($subject);
    $computed = $subject->isPromotionActive() ? $omnibus->lowestFor($subject) : null;
    $excludeRoute = $excludeRoute ?? null;
    $restoreRoute = $restoreRoute ?? null;
@endphp
<div class="border rounded p-3 mt-3 bg-light">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
        <strong>Omnibus — najniższa cena z 30 dni</strong>
        @if($computed !== null)
            <span>Wyliczona teraz: <strong>{{ number_format((float) $computed, 2, ',', ' ') }} zł</strong></span>
        @else
            <span class="text-muted">Brak aktywnej promocji — informacja nie jest pokazywana klientowi.</span>
        @endif
    </div>
    <p class="small text-muted mb-2">
        System zapisuje każdą cenę oferowaną klientowi. Możesz wyłączyć wpis testowy albo pomyłkę — zostaje ślad, kto i dlaczego.
        UOKiK liczy też krótkie ceny, jeśli naprawdę były w ofercie, więc wyłączaj tylko realne pomyłki.
    </p>
    @if($rows->isEmpty())
        <p class="small mb-0">Brak historii. Pojawi się po pierwszym zapisie ceny.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Od</th>
                        <th>Do</th>
                        <th>Cena</th>
                        <th>Rodzaj</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr class="{{ $row->excluded_from_omnibus ? 'table-warning' : '' }}">
                            <td>{{ $row->effective_from?->timezone('Europe/Warsaw')->format('d.m.Y H:i') }}</td>
                            <td>{{ $row->effective_to?->timezone('Europe/Warsaw')->format('d.m.Y H:i') ?? 'teraz' }}</td>
                            <td>{{ number_format((float) $row->offered_price, 2, ',', ' ') }} zł</td>
                            <td>{{ $row->kind === 'promotional' ? 'promocja' : 'regularna' }}</td>
                            <td class="text-end">
                                @if($row->excluded_from_omnibus)
                                    <div class="small text-muted mb-1">Wyłączone: {{ $row->excluded_reason }}</div>
                                    <form method="POST" action="{{ $restoreRoute($row) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Przywróć</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ $excludeRoute($row) }}" class="d-flex gap-2 justify-content-end">
                                        @csrf
                                        <input type="text" name="reason" class="form-control form-control-sm" style="max-width: 16rem;"
                                               placeholder="Powód, np. test / pomyłka" required maxlength="500">
                                        <button type="submit" class="btn btn-sm btn-outline-warning">Wyłącz</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
