@php
    /** @var \App\Models\FormOrder $zamowienie */
    $variantLabel = $zamowienie->orderFormVariantAdminLabel();
    $variantBadgeClass = $zamowienie->orderFormVariantBadgeClass();
@endphp
<span class="badge bg-{{ $variantBadgeClass }} fs-6 ms-1" title="Wersja publicznego formularza zamówienia">
    {{ $variantLabel }}
</span>
