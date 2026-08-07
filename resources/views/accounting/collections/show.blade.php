<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                Windykacja: sprawa #{{ $case->id }}
            </h2>
            <button type="button"
                    class="btn btn-primary btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#debtReminderModal">
                <i class="bi bi-envelope"></i> Wyślij przypomnienie
            </button>
        </div>
    </x-slot>

    @php
        $order = $case->formOrder;
        $course = $order?->course;
        $courseTitle = $course
            ? $course->plainTitle((string) ($order->product_name ?: 'Szkolenie'))
            : (string) ($order->product_name ?: '—');
        $courseDateTime = $course?->start_date
            ? $course->start_date->timezone(config('app.timezone'))->format('d.m.Y H:i')
            : null;
        $courseInstructor = trim(($course?->instructor?->first_name ?? '').' '.($course?->instructor?->last_name ?? ''));
        $ordererName = trim((string) ($order->orderer_name ?? ''));
        $ordererEmail = trim((string) ($order->orderer_email ?? ''));
        $participantName = trim((string) ($order->display_participant_name ?? ''));
        $participantEmail = trim((string) ($order->display_participant_email ?? ''));
        $profileIsVip = in_array($profile['customer_segment'] ?? null, [
            \App\Models\DebtCase::SEGMENT_VIP,
            \App\Models\DebtCase::SEGMENT_VIP_OVERDUE,
        ], true);
        $showVipAlert = (bool) $case->manual_vip || $profileIsVip;
        $vipAlertReason = $case->manual_vip && filled($case->vip_reason)
            ? $case->vip_reason
            : ($profile['vip_reason'] ?? null);

        $formatPhone = static function (?string $raw): ?array {
            $raw = trim((string) $raw);
            if ($raw === '') {
                return null;
            }
            $digits = preg_replace('/\D+/', '', $raw) ?: '';
            if ($digits === '') {
                return ['display' => $raw, 'tel' => preg_replace('/\s+/', '', $raw) ?: $raw];
            }
            if (strlen($digits) === 9) {
                return [
                    'display' => '+48 '.substr($digits, 0, 3).' '.substr($digits, 3, 3).' '.substr($digits, 6, 3),
                    'tel' => '+48'.$digits,
                ];
            }
            if (strlen($digits) === 11 && str_starts_with($digits, '48')) {
                return [
                    'display' => '+48 '.substr($digits, 2, 3).' '.substr($digits, 5, 3).' '.substr($digits, 8, 3),
                    'tel' => '+'.$digits,
                ];
            }
            if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
                $national = substr($digits, 1);

                return [
                    'display' => '+48 '.substr($national, 0, 3).' '.substr($national, 3, 3).' '.substr($national, 6, 3),
                    'tel' => '+48'.$national,
                ];
            }
            if (strlen($digits) >= 10 && strlen($digits) <= 15) {
                return [
                    'display' => '+'.$digits,
                    'tel' => '+'.$digits,
                ];
            }

            return ['display' => $raw, 'tel' => $digits];
        };

        $ordererPhoneFmt = $formatPhone($order->orderer_phone ?? null);
        $participantPhoneFmt = $formatPhone($order->primaryParticipant?->participant?->phone ?? null);
    @endphp

    <div class="py-3">
        <div class="container-fluid px-4">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <div class="fw-semibold mb-1">Nie udało się zapisać danych:</div>
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-3 d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('accounting.collections.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Lista spraw
                    </a>
                    <a href="{{ route('form-orders.show', $order->id) }}" class="btn btn-outline-primary btn-sm">
                        Zamówienie #{{ $order->id }}
                    </a>
                    <a href="{{ route('accounting.debtors.index') }}" class="btn btn-outline-success btn-sm">
                        Lookup faktury
                    </a>
                    @if($case->canSoftDeleteAsMistake())
                        <button type="button"
                                class="btn btn-outline-danger btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#deleteMistakenDebtCaseModal">
                            <i class="bi bi-trash"></i> Usuń błędną sprawę
                        </button>
                    @endif
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center"
                     id="caseNavControls"
                     data-prev-active-url="{{ $previousCaseActive ? route('accounting.collections.show', $previousCaseActive) : '' }}"
                     data-next-active-url="{{ $nextCaseActive ? route('accounting.collections.show', $nextCaseActive) : '' }}"
                     data-prev-all-url="{{ $previousCaseAll ? route('accounting.collections.show', $previousCaseAll) : '' }}"
                     data-next-all-url="{{ $nextCaseAll ? route('accounting.collections.show', $nextCaseAll) : '' }}"
                     data-prev-active-id="{{ $previousCaseActive?->id ?? '' }}"
                     data-next-active-id="{{ $nextCaseActive?->id ?? '' }}"
                     data-prev-all-id="{{ $previousCaseAll?->id ?? '' }}"
                     data-next-all-id="{{ $nextCaseAll?->id ?? '' }}">
                    <div class="form-check mb-0 me-1">
                        <input class="form-check-input"
                               type="checkbox"
                               value="1"
                               id="case_nav_active_only"
                               checked>
                        <label class="form-check-label small" for="case_nav_active_only">
                            tylko niezamknięte
                        </label>
                    </div>
                    <a href="{{ $previousCaseActive ? route('accounting.collections.show', $previousCaseActive) : '#' }}"
                       id="caseNavPrev"
                       class="btn btn-outline-dark btn-sm{{ $previousCaseActive ? '' : ' disabled' }}"
                       @if(! $previousCaseActive) aria-disabled="true" tabindex="-1" @endif
                       title="{{ $previousCaseActive ? 'Nowsza sprawa #'.$previousCaseActive->id : 'Brak nowszej sprawy' }}">
                        <i class="bi bi-chevron-left"></i> Poprzednia
                    </a>
                    <a href="{{ $nextCaseActive ? route('accounting.collections.show', $nextCaseActive) : '#' }}"
                       id="caseNavNext"
                       class="btn btn-outline-dark btn-sm{{ $nextCaseActive ? '' : ' disabled' }}"
                       @if(! $nextCaseActive) aria-disabled="true" tabindex="-1" @endif
                       title="{{ $nextCaseActive ? 'Starsza sprawa #'.$nextCaseActive->id : 'Brak starszej sprawy' }}">
                        Następna <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div>

            @if($showVipAlert)
                <div class="alert alert-warning">
                    <div class="fw-semibold">
                        <i class="bi bi-star-fill"></i> VIP / lojalny klient — zalecany kontakt osobisty.
                    </div>
                    <div>
                        Ten kontrahent ma wysoką relację z PNE. Zanim wyślesz formalny monit, rozważ telefon lub personalny e-mail z prośbą o pomoc w identyfikacji wpłaty.
                    </div>
                    @if($vipAlertReason)
                        <div class="small mt-1">Powód: {{ $vipAlertReason }}</div>
                    @endif
                </div>
            @endif

            <div class="row g-3 mb-3">
                <div class="col-12 col-xl-8">
                    <div class="card h-100 case-details-card">
                        <div class="card-header py-2 fw-semibold d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <span>Dane sprawy</span>
                            <form method="POST" action="{{ route('accounting.collections.sync-ifirma', $case) }}" class="mb-0">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary btn-sm py-0 px-2">
                                    <i class="bi bi-arrow-repeat"></i> Odśwież status z iFirma
                                </button>
                            </form>
                        </div>
                        <div class="card-body p-3">
                            @php
                                $invoiceNo = $case->invoice_number ?: $order->invoice_number ?: null;
                                $ksefNo = $case->ksef_number ?: $order->ksef_number ?: null;
                                $amountGross = (float) ($case->amount_gross ?? $order->product_price ?? 0);
                                $hasBuyer = filled($order->buyer_name) || filled($order->buyer_address) || filled($order->buyer_city) || filled($order->buyer_nip);
                                $hasRecipient = filled($order->recipient_name) || filled($order->recipient_address) || filled($order->recipient_city) || filled($order->recipient_nip);
                                $dueDate = $case->due_date?->copy()->startOfDay();
                                $ifirmaStatus = $case->ifirma_payment_status;
                                $paymentDate = null;
                                foreach ($bankPayments ?? [] as $bankPayment) {
                                    $operationDate = $bankPayment->transaction?->operation_date;
                                    if ($operationDate === null) {
                                        continue;
                                    }
                                    $candidatePaymentDate = $operationDate->copy()->startOfDay();
                                    if ($paymentDate === null || $candidatePaymentDate->gt($paymentDate)) {
                                        $paymentDate = $candidatePaymentDate;
                                    }
                                }

                                $dueOverdueDays = null;
                                $dueDaysLabel = null;
                                $dueDaysIsDanger = false;
                                if ($dueDate) {
                                    $today = now()->timezone(config('app.timezone'))->startOfDay();
                                    $dayWord = static function (int $n): string {
                                        $mod10 = $n % 10;
                                        $mod100 = $n % 100;
                                        if ($n === 1) {
                                            return 'dzień';
                                        }
                                        if ($mod10 >= 2 && $mod10 <= 4 && ! ($mod100 >= 12 && $mod100 <= 14)) {
                                            return 'dni';
                                        }

                                        return 'dni';
                                    };

                                    if ($ifirmaStatus === \App\Services\IfirmaInvoicePaymentStatusService::STATUS_PAID) {
                                        // Opłacona: „X dni po terminie” tylko gdy znamy datę wpłaty i była po terminie (szary).
                                        if ($paymentDate !== null && $paymentDate->gt($dueDate)) {
                                            $dueOverdueDays = (int) $dueDate->diffInDays($paymentDate);
                                            $dueDaysLabel = $dueOverdueDays.' '.$dayWord($dueOverdueDays).' po terminie';
                                            $dueDaysIsDanger = false;
                                        }
                                    } elseif ($ifirmaStatus === \App\Services\IfirmaInvoicePaymentStatusService::STATUS_OVERDUE) {
                                        // Czerwone przeterminowanie tylko gdy iFirma mówi „przeterminowana”.
                                        if ($dueDate->lt($today)) {
                                            $dueOverdueDays = (int) $dueDate->diffInDays($today);
                                            $dueDaysLabel = $dueOverdueDays.' '.$dayWord($dueOverdueDays).' po terminie';
                                            $dueDaysIsDanger = true;
                                        } elseif ($dueDate->equalTo($today)) {
                                            $dueDaysLabel = 'termin dziś';
                                        }
                                    } else {
                                        // Pozostałe statusy / brak sync: bez czerwonego „po terminie”; zostaw „dziś” / „za X”.
                                        if ($dueDate->equalTo($today)) {
                                            $dueDaysLabel = 'termin dziś';
                                        } elseif ($dueDate->gt($today)) {
                                            $n = (int) $today->diffInDays($dueDate);
                                            $dueDaysLabel = 'za '.$n.' '.$dayWord($n);
                                        }
                                    }
                                }
                            @endphp

                            {{-- Kluczowe dane: zamówienie / FV + status iFirma --}}
                            <div class="row g-2 mb-2">
                                <div class="col-md-8 col-xl-9">
                                    <div class="border rounded-2 h-100 p-2 d-flex flex-column">
                                        <div class="d-flex flex-wrap gap-3 gap-xl-4 align-items-start">
                                            <div>
                                                <div class="text-muted small text-uppercase mb-1" style="letter-spacing: .03em;">Zamówienie</div>
                                                <div class="fw-semibold lh-sm">
                                                    <a href="{{ route('form-orders.show', $order->id) }}" class="text-decoration-none">#{{ $order->id }}</a>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-muted small text-uppercase mb-1" style="letter-spacing: .03em;">Faktura</div>
                                                @if($invoiceNo)
                                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                                        <button type="button"
                                                                class="btn btn-link text-decoration-none text-body fw-semibold lh-sm text-nowrap p-0 border-0 case-copy-value"
                                                                data-copy-text="{{ $invoiceNo }}"
                                                                title="Kliknij, aby skopiować"
                                                                data-bs-toggle="tooltip"
                                                                data-bs-title="Kliknij, aby skopiować">{{ $invoiceNo }}</button>
                                                        <button type="button"
                                                                class="btn btn-link btn-sm p-0 text-muted case-fill-bank-search"
                                                                data-bank-search-text="{{ $invoiceNo }}"
                                                                title="Wstaw do wyszukiwarki przelewów"
                                                                data-bs-toggle="tooltip"
                                                                data-bs-title="Wstaw do wyszukiwarki przelewów"
                                                                aria-label="Wstaw numer faktury do wyszukiwarki przelewów">
                                                            <i class="bi bi-search" aria-hidden="true"></i>
                                                        </button>
                                                    </div>
                                                @else
                                                    <div class="fw-semibold lh-sm text-nowrap">—</div>
                                                @endif
                                            </div>
                                            <div class="flex-grow-1" style="min-width: 12rem;">
                                                <div class="text-muted small text-uppercase mb-1" style="letter-spacing: .03em;">KSeF</div>
                                                @if($ksefNo)
                                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                                        <button type="button"
                                                                class="btn btn-link text-decoration-none text-body fw-semibold text-nowrap lh-sm p-0 border-0 case-copy-value"
                                                                data-copy-text="{{ $ksefNo }}"
                                                                title="Kliknij, aby skopiować"
                                                                data-bs-toggle="tooltip"
                                                                data-bs-title="Kliknij, aby skopiować">{{ $ksefNo }}</button>
                                                        <button type="button"
                                                                class="btn btn-link btn-sm p-0 text-muted case-fill-bank-search"
                                                                data-bank-search-text="{{ $ksefNo }}"
                                                                title="Wstaw do wyszukiwarki przelewów"
                                                                data-bs-toggle="tooltip"
                                                                data-bs-title="Wstaw do wyszukiwarki przelewów"
                                                                aria-label="Wstaw numer KSeF do wyszukiwarki przelewów">
                                                            <i class="bi bi-search" aria-hidden="true"></i>
                                                        </button>
                                                    </div>
                                                @else
                                                    <div class="fw-semibold text-nowrap lh-sm">—</div>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="mt-auto pt-2 fw-semibold lh-sm">
                                            <span class="text-muted fw-normal">Kwota:</span>
                                            {{ number_format($amountGross, 2, ',', ' ') }} zł
                                            <span class="mx-2 text-muted fw-normal">·</span>
                                            <span class="text-muted fw-normal text-uppercase">Wystawiono:</span>
                                            {{ $case->invoice_date?->format('d.m.Y') ?: ($order->invoice_issue_date?->format('d.m.Y') ?: '—') }}
                                            <span class="mx-2 text-muted fw-normal">·</span>
                                            <span class="text-muted fw-normal text-uppercase">Termin płatności:</span>
                                            {{ $case->due_date?->format('d.m.Y') ?: ($order->invoice_due_date?->format('d.m.Y') ?: '—') }}
                                            @if($dueDaysLabel)
                                                <span @class([
                                                    'ms-1',
                                                    'text-danger' => $dueDaysIsDanger,
                                                    'text-muted fw-normal' => ! $dueDaysIsDanger,
                                                ])>({{ $dueDaysLabel }})</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-xl-3">
                                    <div class="border rounded-2 h-100 p-2 text-center">
                                        <div class="text-muted small text-uppercase mb-1 fw-bold" style="letter-spacing: .03em;">Status iFirma</div>
                                        @if($order->hasIfirmaInvoiceId())
                                            <div class="lh-sm mb-1">
                                                <code class="user-select-all"
                                                      data-bs-toggle="tooltip"
                                                      data-bs-title="ID iFirma"
                                                      title="ID iFirma">ID: {{ $order->ifirma_invoice_id }}</code>
                                            </div>
                                        @endif
                                        @if($case->ifirma_payment_status)
                                            <div>
                                                <span class="badge {{ \App\Services\IfirmaInvoicePaymentStatusService::statusBadgeClass($case->ifirma_payment_status) }}">
                                                    {{ $case->ifirmaPaymentStatusLabel() }}
                                                </span>
                                            </div>
                                            @if($case->ifirma_synced_at)
                                                <div class="small text-muted mt-1">
                                                    sync {{ $case->ifirma_synced_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                                                </div>
                                            @endif
                                        @else
                                            <div class="small text-muted">Nie synchronizowano</div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="border rounded-2 p-2 mb-3">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                    <div class="small text-uppercase fw-bold mb-0" style="letter-spacing: .03em;">
                                        PDF faktury
                                    </div>
                                    @if($caseHasInvoicePdf ?? $case->hasInvoicePdf())
                                        <div class="small text-muted">
                                            {{ $case->invoice_pdf_original_name ?: 'faktura.pdf' }}
                                            @if($case->invoice_pdf_uploaded_at)
                                                · {{ $case->invoice_pdf_uploaded_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                @if($caseHasInvoicePdf ?? $case->hasInvoicePdf())
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button"
                                                class="btn btn-primary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#caseInvoicePdfPreviewModal">
                                            <i class="bi bi-eye"></i> Podgląd PDF
                                        </button>
                                        <a href="{{ route('accounting.collections.invoice-pdf.preview', $case) }}"
                                           class="btn btn-outline-primary btn-sm"
                                           target="_blank"
                                           rel="noopener">
                                            <i class="bi bi-box-arrow-up-right"></i> Otwórz w nowej karcie
                                        </a>
                                        <button type="button"
                                                class="btn btn-outline-danger btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#caseInvoicePdfDeleteModal">
                                            <i class="bi bi-trash"></i> Usuń
                                        </button>
                                    </div>
                                    <form method="POST"
                                          action="{{ route('accounting.collections.invoice-pdf.upload', $case) }}"
                                          enctype="multipart/form-data"
                                          class="row g-2 align-items-end mt-2">
                                        @csrf
                                        <div class="col-12 col-md">
                                            <label class="form-label small mb-1" for="case_invoice_pdf_replace">Zastąp innym PDF</label>
                                            <input type="file"
                                                   class="form-control form-control-sm @error('invoice_pdf') is-invalid @enderror"
                                                   id="case_invoice_pdf_replace"
                                                   name="invoice_pdf"
                                                   accept="application/pdf,.pdf"
                                                   required>
                                            @error('invoice_pdf')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                <i class="bi bi-upload"></i> Wgraj ponownie
                                            </button>
                                        </div>
                                    </form>
                                @else
                                    <form method="POST"
                                          action="{{ route('accounting.collections.invoice-pdf.upload', $case) }}"
                                          enctype="multipart/form-data"
                                          class="row g-2 align-items-end">
                                        @csrf
                                        <div class="col-12 col-md">
                                            <label class="form-label small mb-1" for="case_invoice_pdf">Wgraj PDF faktury (max 5 MB)</label>
                                            <input type="file"
                                                   class="form-control form-control-sm @error('invoice_pdf') is-invalid @enderror"
                                                   id="case_invoice_pdf"
                                                   name="invoice_pdf"
                                                   accept="application/pdf,.pdf"
                                                   required>
                                            @error('invoice_pdf')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-upload"></i> Wgraj PDF
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            </div>

                            {{-- Szkolenie --}}
                            <div class="mb-3">
                                <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-1">
                                    <div class="small text-uppercase fw-bold" style="letter-spacing: .03em;">Szkolenie</div>
                                    @if($courseDateTime || $courseInstructor !== '')
                                        <div class="small text-muted text-end ms-auto">
                                            @if($courseDateTime)
                                                <span><i class="bi bi-calendar3"></i> {{ $courseDateTime }}</span>
                                            @endif
                                            @if($courseDateTime && $courseInstructor !== '')
                                                <span class="mx-1">·</span>
                                            @endif
                                            @if($courseInstructor !== '')
                                                <span><i class="bi bi-person"></i> {{ $courseInstructor }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                @if($course)
                                    <a href="{{ route('courses.show', $course->id) }}" class="fw-semibold text-decoration-none">
                                        {{ $courseTitle }}
                                    </a>
                                @else
                                    <span class="fw-semibold">{{ $courseTitle }}</span>
                                @endif
                            </div>

                            {{-- Kontakty i strony FV --}}
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <div class="border rounded-2 h-100 p-2 bg-body-tertiary bg-opacity-25">
                                        <div class="text-muted small text-uppercase mb-1" style="letter-spacing: .03em;">Zamawiający</div>
                                        <div class="fw-semibold">{{ $ordererName !== '' ? $ordererName : '—' }}</div>
                                        @if($ordererEmail !== '')
                                            <div class="small text-truncate">
                                                <i class="bi bi-envelope"></i>
                                                <a href="mailto:{{ $ordererEmail }}" class="text-decoration-none">{{ $ordererEmail }}</a>
                                            </div>
                                        @endif
                                        @if($ordererPhoneFmt)
                                            <div class="small">
                                                <i class="bi bi-telephone"></i>
                                                <a href="tel:{{ $ordererPhoneFmt['tel'] }}" class="text-decoration-none text-nowrap">{{ $ordererPhoneFmt['display'] }}</a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-2 h-100 p-2 bg-body-tertiary bg-opacity-25">
                                        <div class="text-muted small text-uppercase mb-1" style="letter-spacing: .03em;">Uczestnik</div>
                                        <div class="fw-semibold">{{ $participantName !== '' ? $participantName : '—' }}</div>
                                        @if($participantEmail !== '')
                                            <div class="small text-truncate">
                                                <i class="bi bi-envelope"></i>
                                                <a href="mailto:{{ $participantEmail }}" class="text-decoration-none">{{ $participantEmail }}</a>
                                                @if($ordererEmail !== '' && strcasecmp($ordererEmail, $participantEmail) === 0)
                                                    <span class="badge text-bg-light border ms-1">jak zamawiający</span>
                                                @endif
                                            </div>
                                        @endif
                                        @if($participantPhoneFmt)
                                            <div class="small">
                                                <i class="bi bi-telephone"></i>
                                                <a href="tel:{{ $participantPhoneFmt['tel'] }}" class="text-decoration-none text-nowrap">{{ $participantPhoneFmt['display'] }}</a>
                                            </div>
                                        @elseif($ordererPhoneFmt && ($participantEmail === '' || strcasecmp($ordererEmail, $participantEmail) === 0))
                                            <div class="small text-muted">
                                                <i class="bi bi-telephone"></i> ten sam telefon co zamawiający
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-2 h-100 p-2">
                                        <div class="text-muted small text-uppercase mb-1" style="letter-spacing: .03em;">Nabywca</div>
                                        @if($hasBuyer)
                                            <div class="fw-semibold">{{ $order->buyer_name ?: '—' }}</div>
                                            @if(filled($order->buyer_address))
                                                <div class="small">{{ $order->buyer_address }}</div>
                                            @endif
                                            @if(filled($order->buyer_postal_code) || filled($order->buyer_city))
                                                <div class="small">{{ trim(($order->buyer_postal_code ?? '').' '.($order->buyer_city ?? '')) }}</div>
                                            @endif
                                            @if(filled($order->buyer_nip))
                                                <div class="small">NIP: {{ preg_replace('/[^0-9]/', '', (string) $order->buyer_nip) }}</div>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-2 h-100 p-2">
                                        <div class="text-muted small text-uppercase mb-1" style="letter-spacing: .03em;">Odbiorca</div>
                                        @if($hasRecipient)
                                            <div class="fw-semibold">{{ $order->recipient_name ?: '—' }}</div>
                                            @if(filled($order->recipient_address))
                                                <div class="small">{{ $order->recipient_address }}</div>
                                            @endif
                                            @if(filled($order->recipient_postal_code) || filled($order->recipient_city))
                                                <div class="small">{{ trim(($order->recipient_postal_code ?? '').' '.($order->recipient_city ?? '')) }}</div>
                                            @endif
                                            @if(filled($order->recipient_nip))
                                                <div class="small">NIP: {{ preg_replace('/[^0-9]/', '', (string) $order->recipient_nip) }}</div>
                                            @endif
                                            @if($order->isKsefAdditionalEntityEnabled())
                                                <div class="small text-muted">
                                                    Podmiot3:
                                                    {{ \App\Models\FormOrder::ksefAdditionalEntityRoleLabel($order->ksef_additional_entity_role) }}
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Opiekunowie --}}
                            <div class="d-flex flex-wrap justify-content-between gap-2 small pt-2 border-top">
                                <div>
                                    <span class="text-muted">Utworzył:</span>
                                    {{ $case->createdBy?->name ?: '—' }}
                                </div>
                                <div class="ms-auto text-end">
                                    <span class="text-muted">Opiekun:</span>
                                    {{ $case->assignedTo?->name ?: '—' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Status operacyjny</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('accounting.collections.update', $case) }}" class="row g-2">
                                @csrf
                                @method('PUT')
                                <div class="col-6">
                                    <label class="form-label small mb-1" for="status">Status</label>
                                    <select class="form-select form-select-sm" id="status" name="status">
                                        @foreach($statusLabels as $value => $label)
                                            <option value="{{ $value }}" @selected($case->status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1" for="priority">Priorytet</label>
                                    <select class="form-select form-select-sm" id="priority" name="priority">
                                        <option value="low" @selected($case->priority === 'low')>Niski</option>
                                        <option value="normal" @selected($case->priority === 'normal')>Normalny</option>
                                        <option value="high" @selected($case->priority === 'high')>Wysoki</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1" for="customer_segment">Segment</label>
                                    <select class="form-select form-select-sm" id="customer_segment" name="customer_segment">
                                        @foreach($segmentLabels as $value => $label)
                                            <option value="{{ $value }}" @selected($case->customer_segment === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1" for="next_action_at">Następny kontakt</label>
                                    <input type="datetime-local" class="form-control form-control-sm" id="next_action_at" name="next_action_at"
                                           value="{{ $case->next_action_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i') }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1" for="summary">Podsumowanie</label>
                                    <textarea class="form-control form-control-sm" id="summary" name="summary" rows="2">{{ $case->summary }}</textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1" for="vip_reason">Powód VIP / delikatnej obsługi</label>
                                    <input type="text" class="form-control form-control-sm" id="vip_reason" name="vip_reason" value="{{ $case->vip_reason }}">
                                </div>
                                <div class="col-12 d-flex align-items-center gap-3 flex-wrap">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" id="manual_vip" name="manual_vip" value="1" @checked($case->manual_vip)>
                                        <label class="form-check-label" for="manual_vip">VIP ręcznie</label>
                                    </div>
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" id="do_not_auto_dun" name="do_not_auto_dun" value="1" @checked($case->do_not_auto_dun)>
                                        <label class="form-check-label" for="do_not_auto_dun">Bez automatycznego monitu</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-save"></i> Zapisz
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header fw-semibold d-flex justify-content-between align-items-center gap-2 py-2"
                             id="caseBankPaymentsHeader"
                             role="button"
                             tabindex="0"
                             aria-expanded="false"
                             aria-controls="caseBankPaymentsCollapse"
                             style="cursor: pointer;">
                            <div class="d-inline-flex align-items-center gap-2 user-select-none">
                                <i class="bi bi-chevron-right case-bank-payments-chevron" aria-hidden="true"></i>
                                <span>Wpłaty z wyciągu</span>
                                @if(($bankPayments ?? collect())->isNotEmpty())
                                    <span class="badge text-bg-secondary">{{ $bankPayments->count() }}</span>
                                @endif
                            </div>
                            <a href="{{ route('accounting.bank-imports.index') }}"
                               class="btn btn-sm btn-outline-secondary"
                               id="caseBankPaymentsImportLink">Import wyciągu</a>
                        </div>
                        <div id="caseBankPaymentsCollapse" class="collapse">
                            <div class="card-body border-bottom">
                                <form id="bankTransferSearchForm" class="row g-2 align-items-end">
                                    <div class="col-12 col-lg-5">
                                        <label for="bank_search" class="form-label small mb-1">Szukaj przelewu</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text"
                                                   id="bank_search"
                                                   name="bank_search"
                                                   class="form-control"
                                                   value=""
                                                   placeholder="Nadawca, opis, NIP, FV, KSeF, konto"
                                                   maxlength="128"
                                                   autocomplete="off">
                                            <button type="button"
                                                    class="btn btn-outline-secondary"
                                                    id="bankTransferSearchClearBtn"
                                                    title="Wyczyść pole wyszukiwania"
                                                    data-bs-toggle="tooltip"
                                                    data-bs-title="Wyczyść pole wyszukiwania"
                                                    aria-label="Wyczyść pole wyszukiwania">
                                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-6 col-lg-3">
                                        <label for="bank_amount" class="form-label small mb-1">Kwota</label>
                                        <input type="number"
                                               step="0.01"
                                               min="0"
                                               id="bank_amount"
                                               name="bank_amount"
                                               class="form-control form-control-sm"
                                               value="{{ $bankTransferAmount !== null ? number_format((float) $bankTransferAmount, 2, '.', '') : '' }}">
                                    </div>
                                    <div class="col-6 col-lg-4 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary btn-sm" id="bankTransferSearchBtn">
                                            <i class="bi bi-search"></i> Szukaj przelewu
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="bankTransferSearchResetBtn">Reset</button>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   value="1"
                                                   id="bank_unlinked_only"
                                                   name="bank_unlinked_only"
                                                   checked>
                                            <label class="form-check-label small" for="bank_unlinked_only">
                                                Szukaj tylko w nieprzypisanych
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   value="1"
                                                   id="bank_after_order"
                                                   name="bank_after_order"
                                                   @checked($bankAfterOrderDate ?? true)>
                                            <label class="form-check-label small" for="bank_after_order">
                                                Tylko przelewy z datą operacji ≥ data zamówienia
                                                @if($order?->order_date)
                                                    ({{ $order->order_date->format('Y-m-d') }})
                                                @endif
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   value="1"
                                                   id="bank_search_exact"
                                                   name="bank_search_exact">
                                            <label class="form-check-label small" for="bank_search_exact">
                                                Szukaj dokładnie wpisanego numeru (bez dopasowania fragmentu)
                                            </label>
                                        </div>
                                        <div class="form-text" id="bankTransferSearchStatus">
                                            Wpisz frazę i kliknij „Szukaj przelewu”. Domyślnie wyniki obejmują tylko wpływy bez zaakceptowanego/ignorowanego powiązania. Przy numerze FV/KSeF zaznacz dokładne dopasowanie (lupka przy FV/KSeF robi to automatycznie).
                                        </div>
                                    </div>
                                </form>

                                <div class="mt-3" id="bankTransferSearchResults">
                                    <div class="text-muted small">Brak wyników — wykonaj wyszukiwanie.</div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                @if(($bankPayments ?? collect())->isEmpty())
                                    <div class="p-3 text-muted small">Brak zaakceptowanych wpłat z wyciągu bankowego dla tej sprawy.</div>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Data operacji</th>
                                                    <th class="text-end">Kwota</th>
                                                    <th>Opis</th>
                                                    <th>Zaakceptował</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($bankPayments as $payment)
                                                    @php $tx = $payment->transaction; @endphp
                                                    <tr>
                                                        <td class="small">{{ $tx?->operation_date?->format('Y-m-d') ?? '—' }}</td>
                                                        <td class="text-end fw-semibold text-nowrap">
                                                            {{ $tx ? number_format((float) $tx->amount, 2, ',', ' ').' '.$tx->currency : '—' }}
                                                        </td>
                                                        <td class="small text-break" style="max-width: 28rem;">{{ \Illuminate\Support\Str::limit($tx?->description ?? '—', 160) }}</td>
                                                        <td class="small">
                                                            {{ $payment->acceptedBy?->name ?? '—' }}
                                                            <div class="text-muted">{{ $payment->accepted_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</div>
                                                        </td>
                                                        <td class="text-end text-nowrap">
                                                            <button type="button"
                                                                    class="btn btn-sm btn-outline-danger"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#bankPaymentUnlinkModal"
                                                                    data-unlink-url="{{ route('accounting.collections.bank-matches.unlink', [$case, $payment]) }}"
                                                                    data-unlink-summary="{{ $tx ? number_format((float) $tx->amount, 2, ',', ' ').' '.$tx->currency.' · '.($tx->operation_date?->format('Y-m-d') ?? '—') : 'przelew #'.$payment->id }}">
                                                                Cofnij
                                                            </button>
                                                            @if($tx)
                                                                <a class="btn btn-sm btn-outline-primary" href="{{ route('accounting.bank-imports.show', $tx->bank_statement_import_id) }}">Import</a>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Dodaj działanie</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('accounting.collections.actions.store', $case) }}" class="row g-2">
                                @csrf
                                <div class="col-6 col-lg-4">
                                    <label class="form-label small mb-1" for="action_type">Typ</label>
                                    <select class="form-select form-select-sm" id="action_type" name="action_type" required>
                                        @foreach($actionTypeLabels as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-lg-4">
                                    <label class="form-label small mb-1" for="promised_payment_at">Obietnica do</label>
                                    <input type="date" class="form-control form-control-sm" id="promised_payment_at" name="promised_payment_at">
                                </div>
                                <div class="col-12 col-lg-4">
                                    <label class="form-label small mb-1" for="action_next_action_at">Następny kontakt</label>
                                    <input type="datetime-local" class="form-control form-control-sm" id="action_next_action_at" name="next_action_at">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1" for="note">Notatka</label>
                                    <textarea class="form-control form-control-sm" id="note" name="note" rows="3" placeholder="Co ustalono, z kim rozmawiano, jaki następny krok?"></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="bi bi-plus-circle"></i> Dodaj działanie
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Dodaj alternatywny kontakt</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('accounting.collections.contacts.store', $case) }}" class="row g-2">
                                @csrf
                                <div class="col-6 col-lg-3">
                                    <label class="form-label small mb-1" for="contact_type">Typ</label>
                                    <select class="form-select form-select-sm" id="contact_type" name="contact_type" required>
                                        @foreach($contactTypeLabels as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-lg-5">
                                    <label class="form-label small mb-1" for="value">Wartość</label>
                                    <input type="text" class="form-control form-control-sm" id="value" name="value" required>
                                </div>
                                <div class="col-12 col-lg-4">
                                    <label class="form-label small mb-1" for="source">Źródło</label>
                                    <input type="text" class="form-control form-control-sm" id="source" name="source" placeholder="np. strona szkoły">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1" for="contact_notes">Notatka</label>
                                    <input type="text" class="form-control form-control-sm" id="contact_notes" name="notes">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-outline-success btn-sm">
                                        <i class="bi bi-person-plus"></i> Dodaj kontakt
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-xl-6">
                    <div class="card">
                        <div class="card-header fw-semibold">Historia działań</div>
                        <div class="card-body">
                            @forelse($case->actions as $action)
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between gap-2">
                                        <span class="fw-semibold">{{ $action->typeLabel() }}</span>
                                        <span class="small text-muted">{{ $action->happened_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') ?: '—' }}</span>
                                    </div>
                                    @if($action->promised_payment_at)
                                        <div class="small text-success">Obietnica płatności do: {{ $action->promised_payment_at->format('d.m.Y') }}</div>
                                    @endif
                                    @if($action->next_action_at)
                                        <div class="small text-primary">Następny kontakt: {{ $action->next_action_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</div>
                                    @endif
                                    <div class="small">{{ $action->note ?: '—' }}</div>
                                    <div class="small text-muted">
                                        <i class="bi bi-person"></i>
                                        {{ $action->user?->name ?: 'System / brak użytkownika' }}
                                    </div>
                                </div>
                            @empty
                                <div class="text-muted">Brak działań.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="card mb-3">
                        <div class="card-header fw-semibold">Kontakty</div>
                        <div class="card-body">
                            @forelse($case->contacts as $contact)
                                <div class="border-bottom pb-2 mb-2 d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <span class="badge text-bg-light border">{{ $contact->typeLabel() }}</span>
                                        <span class="fw-semibold">{{ $contact->value }}</span>
                                        @if($contact->source)
                                            <span class="small text-muted">Źródło: {{ $contact->source }}</span>
                                        @endif
                                        @if($contact->notes)
                                            <div class="small">{{ $contact->notes }}</div>
                                        @endif
                                        <div class="small text-muted">
                                            <i class="bi bi-person"></i>
                                            {{ $contact->createdBy?->name ?: 'System / brak użytkownika' }}
                                        </div>
                                    </div>
                                    <button type="button"
                                            class="btn btn-outline-danger btn-sm flex-shrink-0 case-contact-delete-btn"
                                            data-action="{{ route('accounting.collections.contacts.destroy', [$case, $contact]) }}"
                                            data-summary="{{ $contact->typeLabel() }}: {{ $contact->value }}"
                                            title="Usuń kontakt">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            @empty
                                <div class="text-muted">Brak dodatkowych kontaktów.</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header fw-semibold">Historia powiązanych zamówień</div>
                        <div class="px-3 pt-2 small text-muted border-bottom">
                            Identyfikacja: <span class="text-body">{{ $customerIdentitySummary ?? '—' }}</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Faktura</th>
                                        <th>Szkolenie</th>
                                        <th class="text-end">Kwota</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($relatedOrders as $relatedOrder)
                                        @php
                                            $linkReasons = $relatedOrder->link_reasons ?? [];
                                            $isCurrentOrder = (int) $relatedOrder->id === (int) ($order->id ?? 0);
                                            /** @var \App\Models\DebtCase|null $relatedDebtCase */
                                            $relatedDebtCase = $relatedOrder->related_debt_case ?? null;
                                            $isCurrentCase = $relatedDebtCase && (int) $relatedDebtCase->id === (int) $case->id;
                                            $relatedCaseIsActive = $relatedDebtCase && $relatedDebtCase->status !== \App\Models\DebtCase::STATUS_CLOSED;
                                        @endphp
                                        <tr @class(['table-primary' => $isCurrentOrder])>
                                            <td>
                                                <div>
                                                    <a href="{{ route('form-orders.show', $relatedOrder->id) }}">#{{ $relatedOrder->id }}</a>
                                                </div>
                                                @if($relatedDebtCase)
                                                    <div class="mt-1">
                                                        @php
                                                            $caseBadgeTooltip = $isCurrentCase
                                                                ? 'To zamówienie należy do obecnie otwartej karty sprawy.'
                                                                : ($relatedCaseIsActive
                                                                    ? 'Inna niezamknięta sprawa windykacyjna dla tego zamówienia. Kliknij, aby otworzyć.'
                                                                    : 'Zamknięta sprawa windykacyjna dla tego zamówienia. Kliknij, aby otworzyć.');
                                                        @endphp
                                                        <a href="{{ route('accounting.collections.show', $relatedDebtCase) }}"
                                                           class="text-decoration-none">
                                                            <span @class([
                                                                'badge',
                                                                'text-bg-dark' => $isCurrentCase,
                                                                'text-bg-danger' => ! $isCurrentCase && $relatedCaseIsActive,
                                                                'text-bg-success' => ! $isCurrentCase && ! $relatedCaseIsActive,
                                                            ])
                                                                  data-bs-toggle="tooltip"
                                                                  data-bs-title="{{ $caseBadgeTooltip }}">
                                                                @if($isCurrentCase)
                                                                    ta sprawa
                                                                @else
                                                                    #{{ $relatedDebtCase->id }} · {{ $relatedDebtCase->statusLabel() }}
                                                                @endif
                                                            </span>
                                                        </a>
                                                    </div>
                                                @endif
                                                @if(count($linkReasons) > 0)
                                                    <div class="mt-1">
                                                        @foreach($linkReasons as $reason)
                                                            @php
                                                                $badgeClass = match ($reason['strength'] ?? '') {
                                                                    'high' => 'text-bg-primary',
                                                                    'medium' => 'text-bg-warning',
                                                                    default => 'text-bg-light border',
                                                                };
                                                                $reasonExplain = match ($reason['key'] ?? '') {
                                                                    'recipient_nip' => 'Powiązane, bo ma ten sam NIP odbiorcy',
                                                                    'recipient_profile' => 'Powiązane, bo ma te same dane odbiorcy (nazwa, adres, kod, miasto)',
                                                                    'buyer_nip' => 'Powiązane, bo ma ten sam NIP nabywcy',
                                                                    'orderer_email' => 'Powiązane, bo ma ten sam e-mail zamawiającego',
                                                                    default => 'Powód powiązania z historią klienta',
                                                                };
                                                                $reasonTooltip = ! empty($reason['value'])
                                                                    ? $reasonExplain.': '.$reason['value']
                                                                    : $reasonExplain.'.';
                                                            @endphp
                                                            <span class="badge {{ $badgeClass }} me-1 mb-1"
                                                                  data-bs-toggle="tooltip"
                                                                  data-bs-title="{{ $reasonTooltip }}">
                                                                {{ $reason['label'] }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                            <td>{{ $relatedOrder->invoice_number ?: '—' }}</td>
                                            <td>{{ $relatedOrder->product_name ?: '—' }}</td>
                                            <td class="text-end">{{ number_format((float) ($relatedOrder->product_price ?? 0), 2, ',', ' ') }} zł</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-muted text-center py-3">Brak powiązanych zamówień.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer small text-muted">
                            Powiązania według reguły klienta (odbiorca → nabywca → e-mail zamawiającego). Łącznie: {{ $profile['related_orders_count'] }} zamówień,
                            {{ number_format((float) $profile['related_orders_total'], 2, ',', ' ') }} zł.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($caseHasInvoicePdf ?? $case->hasInvoicePdf())
    <div class="modal fade" id="caseInvoicePdfPreviewModal" tabindex="-1" aria-labelledby="caseInvoicePdfPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="caseInvoicePdfPreviewModalLabel">
                        Podgląd PDF faktury
                        @if($case->invoice_pdf_original_name)
                            <span class="text-muted fw-normal">· {{ $case->invoice_pdf_original_name }}</span>
                        @endif
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body p-0" style="min-height: 70vh;">
                    <iframe
                        title="Podgląd PDF faktury"
                        src="{{ route('accounting.collections.invoice-pdf.preview', $case) }}"
                        class="w-100 border-0"
                        style="height: 70vh;"></iframe>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('accounting.collections.invoice-pdf.preview', $case) }}"
                       class="btn btn-outline-primary"
                       target="_blank"
                       rel="noopener">Otwórz w nowej karcie</a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="caseInvoicePdfDeleteModal" tabindex="-1" aria-labelledby="caseInvoicePdfDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('accounting.collections.invoice-pdf.destroy', $case) }}">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h5 class="modal-title" id="caseInvoicePdfDeleteModalLabel">Usuń PDF faktury</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Czy na pewno usunąć PDF faktury ze sprawy <strong>#{{ $case->id }}</strong>?</p>
                        <div class="border rounded p-2 bg-light small fw-semibold">
                            {{ $case->invoice_pdf_original_name ?: 'faktura.pdf' }}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-danger">Usuń PDF</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    @if($case->canSoftDeleteAsMistake())
    <div class="modal fade" id="deleteMistakenDebtCaseModal" tabindex="-1" aria-labelledby="deleteMistakenDebtCaseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('accounting.collections.destroy', $case) }}">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteMistakenDebtCaseModalLabel">
                            <i class="bi bi-trash"></i> Usunąć błędną sprawę?
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">
                            Soft-delete sprawy <strong>#{{ $case->id }}</strong> (zamówienie
                            <strong>#{{ $order->id }}</strong>). Używaj tylko gdy sprawa powstała przez pomyłkę.
                        </p>
                        <div class="mb-3">
                            <label for="delete_mistaken_case_reason" class="form-label small">Powód (opcjonalnie)</label>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="delete_mistaken_case_reason"
                                   name="reason"
                                   maxlength="500"
                                   placeholder="np. utworzono bez FV / zły numer zamówienia">
                        </div>
                        <div class="alert alert-warning small mb-0">
                            Nie usuwa zamówienia ani faktury. Przy zaakceptowanym przelewie z wyciągu usuwanie jest zablokowane.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Wróć</button>
                        <button type="submit" class="btn btn-danger">Usuń sprawę</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <div class="modal fade" id="debtReminderModal" tabindex="-1" aria-labelledby="debtReminderModalLabel" aria-hidden="true"
         data-templates='@json($reminderTemplatePayloads ?? [])'
         data-recipients='@json($reminderRecipientOptions ?? [])'>
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="debtReminderModalLabel">
                        <i class="bi bi-envelope me-2"></i>Wyślij przypomnienie / ponaglenie
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    @if($showVipAlert || $case->do_not_auto_dun)
                        <div class="alert alert-warning small">
                            @if($showVipAlert)
                                <div class="fw-semibold mb-1">VIP / lojalny klient — rozważ kontakt osobisty przed formalnym monitem.</div>
                            @endif
                            @if($case->do_not_auto_dun)
                                <div>Sprawa ma włączone „Bez automatycznego monitu” — ręczna wysyłka jest nadal możliwa.</div>
                            @endif
                        </div>
                    @endif

                    <form id="formDebtReminder"
                          method="POST"
                          action="{{ route('accounting.collections.send-reminder', $case) }}"
                          enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold" for="debtReminderTemplate">Szablon</label>
                            <select class="form-select" id="debtReminderTemplate" name="template">
                                @foreach(($reminderTemplateLabels ?? []) as $value => $label)
                                    <option value="{{ $value }}" @selected(old('template', \App\Services\DebtReminderTemplateService::TEMPLATE_REMINDER) === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold" for="debtReminderSubject">Temat</label>
                            <input type="text"
                                   class="form-control @error('subject') is-invalid @enderror"
                                   id="debtReminderSubject"
                                   name="subject"
                                   value="{{ old('subject') }}"
                                   maxlength="255"
                                   required>
                            @error('subject')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold" for="debtReminderBody">Treść wiadomości (możesz edytować przed wysłaniem)</label>
                            <textarea class="form-control font-monospace @error('body') is-invalid @enderror"
                                      id="debtReminderBody"
                                      name="body"
                                      rows="14"
                                      spellcheck="true"
                                      required>{{ old('body') }}</textarea>
                            @error('body')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <hr>
                        <div class="mb-3">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <label class="form-label fw-bold mb-0">Odbiorcy</label>
                                @if(!empty($reminderRecipientOptions))
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Zaznaczanie odbiorców">
                                        <button type="button" class="btn btn-outline-secondary" id="debtReminderSelectAllRecipients">Zaznacz wszystkich</button>
                                        <button type="button" class="btn btn-outline-secondary" id="debtReminderClearRecipients">Wyczyść</button>
                                    </div>
                                @endif
                            </div>
                            @php
                                $oldRecipientEmails = collect(old('recipient_emails', []))
                                    ->map(fn ($email) => mb_strtolower(trim((string) $email)))
                                    ->filter()
                                    ->all();
                            @endphp
                            @forelse(($reminderRecipientOptions ?? []) as $option)
                                @php
                                    $optionEmailLower = mb_strtolower($option['email']);
                                    $optionDomId = 'debtReminderRecipient_'.preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $option['key']);
                                    $isChecked = in_array($optionEmailLower, $oldRecipientEmails, true)
                                        || (empty($oldRecipientEmails) && ! old('recipient_email'));
                                @endphp
                                <div class="form-check">
                                    <input class="form-check-input debt-reminder-recipient-checkbox"
                                           type="checkbox"
                                           name="recipient_emails[]"
                                           value="{{ $option['email'] }}"
                                           id="{{ $optionDomId }}"
                                           @checked($isChecked)>
                                    <label class="form-check-label" for="{{ $optionDomId }}">
                                        <span class="fw-semibold">{{ $option['label'] }}</span>
                                        <span class="text-muted">({{ $option['email'] }})</span>
                                    </label>
                                </div>
                            @empty
                                <div class="form-text text-warning mb-0">Brak gotowych adresów — wpisz dodatkowy e-mail poniżej.</div>
                            @endforelse
                            @error('recipient_emails')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Możesz zaznaczyć kilku odbiorców — jedna wiadomość pójdzie na wszystkie zaznaczone adresy.</div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold" for="debtReminderRecipientEmail">Dodatkowy e-mail (opcjonalnie)</label>
                                <input type="email"
                                       class="form-control @error('recipient_email') is-invalid @enderror"
                                       id="debtReminderRecipientEmail"
                                       name="recipient_email"
                                       value="{{ old('recipient_email') }}"
                                       autocomplete="email"
                                       placeholder="adres@przykład.pl">
                                @error('recipient_email')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold" for="debtReminderTestEmail">E-mail testowy</label>
                                <input type="email"
                                       class="form-control @error('test_email') is-invalid @enderror"
                                       id="debtReminderTestEmail"
                                       name="test_email"
                                       value="{{ old('test_email', $reminderDefaultTestEmail ?? '') }}"
                                       autocomplete="email"
                                       placeholder="adres@przykład.pl">
                                @error('test_email')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            @if($reminderCanAttachCasePdf ?? false)
                                <div class="form-check mb-2">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           value="1"
                                           id="debtReminderAttachCasePdf"
                                           name="attach_case_pdf"
                                           @checked(old('attach_case_pdf', true))>
                                    <label class="form-check-label" for="debtReminderAttachCasePdf">
                                        Załącz PDF faktury ze sprawy
                                        @if($case->invoice_pdf_original_name)
                                            <span class="text-muted">({{ $case->invoice_pdf_original_name }})</span>
                                        @endif
                                    </label>
                                </div>
                            @endif
                            @if($reminderCanAttachIfirmaPdf ?? false)
                                <div class="form-check mb-2">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           value="1"
                                           id="debtReminderAttachIfirma"
                                           name="attach_ifirma_pdf"
                                           @checked(old('attach_ifirma_pdf'))>
                                    <label class="form-check-label" for="debtReminderAttachIfirma">
                                        Załącz PDF faktury z iFirma
                                    </label>
                                </div>
                            @elseif(!($reminderCanAttachCasePdf ?? false))
                                <div class="form-text text-muted mb-2">Brak ID/numeru FV — nie można pobrać PDF z iFirma.</div>
                            @endif
                            <label class="form-label fw-bold" for="debtReminderAttachment">Dodatkowy załącznik (PDF)</label>
                            <input type="file"
                                   class="form-control @error('attachment') is-invalid @enderror"
                                   id="debtReminderAttachment"
                                   name="attachment"
                                   accept="application/pdf,.pdf">
                            @error('attachment')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Opcjonalnie, max 5 MB.</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" name="send_target" value="recipient" class="btn btn-primary">
                                <i class="bi bi-envelope me-1"></i>Wyślij e-mail do dłużnika
                            </button>
                            <button type="submit" name="send_target" value="test" class="btn btn-outline-primary">
                                <i class="bi bi-flask me-1"></i>Wyślij e-mail testowy
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bankPaymentUnlinkModal" tabindex="-1" aria-labelledby="bankPaymentUnlinkModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="" id="bankPaymentUnlinkForm">
                    @csrf
                    <div class="modal-header text-bg-danger">
                        <h5 class="modal-title" id="bankPaymentUnlinkModalLabel">Cofnij przypisanie przelewu</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">
                            Odpiąć przelew od sprawy <strong>#{{ $case->id }}</strong>
                            (FV {{ $case->invoice_number ?: $order->invoice_number ?: '—' }})?
                        </p>
                        <div class="border rounded p-2 bg-light small mb-3" id="bankPaymentUnlinkSummary">—</div>
                        <div class="alert alert-warning small mb-0">
                            <ul class="mb-0 ps-3">
                                <li>Przelew wróci do kolejki nieprzypisanych wpływów.</li>
                                <li>Jeśli sprawa jest zamknięta — zostanie <strong>otwarta ponownie</strong>.</li>
                                <li>System <strong>spróbuje usunąć wpłatę w iFirma</strong>. Oficjalne API iFirma dokumentuje tylko dodawanie wpłat — gdy usunięcie się nie uda, popraw status ręcznie w panelu iFirma.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Wróć</button>
                        <button type="submit" class="btn btn-danger">Cofnij przypisanie</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bankTransactionLinkConfirmModal" tabindex="-1" aria-labelledby="bankTransactionLinkConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="" id="bankTransactionLinkConfirmForm">
                    @csrf
                    <input type="hidden" name="register_ifirma_payment" value="0" id="bankTransactionLinkRegisterIfirma">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bankTransactionLinkConfirmModalLabel">Potwierdź powiązanie przelewu</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">
                            Powiązać wybrany przelew ze sprawą
                            <strong>#{{ $case->id }}</strong>
                            (FV {{ $case->invoice_number ?: $order->invoice_number ?: '—' }})?
                        </p>
                        <div class="border rounded p-2 bg-light small">
                            <div class="fw-semibold" id="bankTransactionLinkSummary">—</div>
                            <div class="text-muted text-break" id="bankTransactionLinkDescription">—</div>
                        </div>
                        <div class="alert alert-info small mt-3 mb-0 d-none" id="bankTransactionLinkIfirmaInfo">
                            Po lokalnym powiązaniu system spróbuje zarejestrować wpłatę w iFirma i odświeżyć status sprawy.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-primary" id="bankTransactionLinkSubmit">Powiąż lokalnie</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="caseContactDeleteModal" tabindex="-1" aria-labelledby="caseContactDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="" id="caseContactDeleteForm">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h5 class="modal-title" id="caseContactDeleteModalLabel">Usuń kontakt</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Czy na pewno usunąć ten kontakt ze sprawy?</p>
                        <div class="border rounded p-2 bg-light small fw-semibold" id="caseContactDeleteSummary">—</div>
                        <div class="alert alert-warning small mt-3 mb-0">
                            Tej operacji nie da się cofnąć.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-danger">Usuń kontakt</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        #caseBankPaymentsHeader .case-bank-payments-chevron {
            display: inline-block;
            transition: transform 0.15s ease-in-out;
            pointer-events: none;
        }
        #caseBankPaymentsHeader[aria-expanded="true"] .case-bank-payments-chevron {
            transform: rotate(90deg);
        }
        .case-copy-value {
            cursor: copy;
        }
        .case-copy-value:hover {
            color: var(--bs-primary) !important;
        }
        .case-fill-bank-search:hover {
            color: var(--bs-primary) !important;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.bootstrap && window.bootstrap.Tooltip) {
                document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                    window.bootstrap.Tooltip.getOrCreateInstance(el);
                });
            }

            (function initDebtReminderModal() {
                var modalEl = document.getElementById('debtReminderModal');
                if (!modalEl) {
                    return;
                }

                var templates = {};
                try {
                    templates = JSON.parse(modalEl.getAttribute('data-templates') || '{}');
                } catch (e) {
                    templates = {};
                }

                var templateSelect = document.getElementById('debtReminderTemplate');
                var subjectInput = document.getElementById('debtReminderSubject');
                var bodyInput = document.getElementById('debtReminderBody');
                var selectAllBtn = document.getElementById('debtReminderSelectAllRecipients');
                var clearBtn = document.getElementById('debtReminderClearRecipients');
                var keepEditedContent = {{ old('subject') || old('body') ? 'true' : 'false' }};

                function recipientCheckboxes() {
                    return modalEl.querySelectorAll('.debt-reminder-recipient-checkbox');
                }

                function applyTemplate(force) {
                    if (!templateSelect || !subjectInput || !bodyInput) {
                        return;
                    }
                    var key = templateSelect.value;
                    var payload = templates[key];
                    if (!payload) {
                        return;
                    }
                    if (force || !subjectInput.value) {
                        subjectInput.value = payload.subject || '';
                    }
                    if (force || !bodyInput.value) {
                        bodyInput.value = payload.body || '';
                    }
                }

                if (templateSelect) {
                    templateSelect.addEventListener('change', function () {
                        applyTemplate(true);
                    });
                }

                if (selectAllBtn) {
                    selectAllBtn.addEventListener('click', function () {
                        recipientCheckboxes().forEach(function (el) {
                            el.checked = true;
                        });
                    });
                }
                if (clearBtn) {
                    clearBtn.addEventListener('click', function () {
                        recipientCheckboxes().forEach(function (el) {
                            el.checked = false;
                        });
                    });
                }

                applyTemplate(!keepEditedContent);

                @if($errors->hasAny(['template', 'subject', 'body', 'recipient_email', 'recipient_emails', 'test_email', 'attachment', 'send_target']))
                    if (window.bootstrap && window.bootstrap.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    }
                @endif
            })();

            var caseNavControls = document.getElementById('caseNavControls');
            var caseNavActiveOnly = document.getElementById('case_nav_active_only');
            var caseNavPrev = document.getElementById('caseNavPrev');
            var caseNavNext = document.getElementById('caseNavNext');
            var caseNavActiveOnlyStorageKey = 'accounting_collections_nav_active_only_v1';

            function applyCaseNavButton(btn, url, id, directionLabel) {
                if (!btn) {
                    return;
                }
                if (url) {
                    btn.href = url;
                    btn.classList.remove('disabled');
                    btn.removeAttribute('aria-disabled');
                    btn.removeAttribute('tabindex');
                    btn.title = directionLabel + ' sprawa #' + id;
                } else {
                    btn.href = '#';
                    btn.classList.add('disabled');
                    btn.setAttribute('aria-disabled', 'true');
                    btn.setAttribute('tabindex', '-1');
                    btn.title = 'Brak ' + (directionLabel === 'Nowsza' ? 'nowszej' : 'starszej') + ' sprawy';
                }
            }

            function syncCaseNavLinks() {
                if (!caseNavControls || !caseNavActiveOnly) {
                    return;
                }
                var activeOnly = caseNavActiveOnly.checked;
                var prevUrl = activeOnly
                    ? (caseNavControls.getAttribute('data-prev-active-url') || '')
                    : (caseNavControls.getAttribute('data-prev-all-url') || '');
                var nextUrl = activeOnly
                    ? (caseNavControls.getAttribute('data-next-active-url') || '')
                    : (caseNavControls.getAttribute('data-next-all-url') || '');
                var prevId = activeOnly
                    ? (caseNavControls.getAttribute('data-prev-active-id') || '')
                    : (caseNavControls.getAttribute('data-prev-all-id') || '');
                var nextId = activeOnly
                    ? (caseNavControls.getAttribute('data-next-active-id') || '')
                    : (caseNavControls.getAttribute('data-next-all-id') || '');

                applyCaseNavButton(caseNavPrev, prevUrl, prevId, 'Nowsza');
                applyCaseNavButton(caseNavNext, nextUrl, nextId, 'Starsza');
            }

            function saveCaseNavActiveOnly() {
                if (!caseNavActiveOnly) {
                    return;
                }
                try {
                    localStorage.setItem(caseNavActiveOnlyStorageKey, caseNavActiveOnly.checked ? '1' : '0');
                } catch (e) {
                    // ignore
                }
            }

            function restoreCaseNavActiveOnly() {
                if (!caseNavActiveOnly) {
                    return;
                }
                try {
                    var raw = localStorage.getItem(caseNavActiveOnlyStorageKey);
                    if (raw === '0') {
                        caseNavActiveOnly.checked = false;
                    } else if (raw === '1') {
                        caseNavActiveOnly.checked = true;
                    }
                } catch (e) {
                    // keep default checked
                }
                syncCaseNavLinks();
            }

            if (caseNavActiveOnly) {
                restoreCaseNavActiveOnly();
                caseNavActiveOnly.addEventListener('change', function () {
                    saveCaseNavActiveOnly();
                    syncCaseNavLinks();
                });
            }
            [caseNavPrev, caseNavNext].forEach(function (btn) {
                if (!btn) {
                    return;
                }
                btn.addEventListener('click', function (event) {
                    if (btn.classList.contains('disabled')) {
                        event.preventDefault();
                    }
                });
            });

            function fallbackCopyText(text) {
                var textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.setAttribute('readonly', '');
                textArea.style.position = 'fixed';
                textArea.style.top = '0';
                textArea.style.left = '0';
                textArea.style.opacity = '0';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                var ok = false;
                try {
                    ok = document.execCommand('copy');
                } catch (e) {
                    ok = false;
                }
                document.body.removeChild(textArea);
                return ok;
            }

            function copyText(text) {
                if (navigator.clipboard && window.isSecureContext) {
                    return navigator.clipboard.writeText(text);
                }
                return fallbackCopyText(text)
                    ? Promise.resolve()
                    : Promise.reject(new Error('Nie udało się skopiować'));
            }

            function flashCopied(el) {
                var tip = window.bootstrap && window.bootstrap.Tooltip
                    ? window.bootstrap.Tooltip.getOrCreateInstance(el)
                    : null;
                var original = el.getAttribute('data-bs-title') || el.getAttribute('title') || 'Kliknij, aby skopiować';
                el.setAttribute('data-bs-title', 'Skopiowano');
                el.setAttribute('title', 'Skopiowano');
                if (tip) {
                    tip.setContent({ '.tooltip-inner': 'Skopiowano' });
                    tip.show();
                }
                el.classList.add('text-success');
                setTimeout(function () {
                    el.setAttribute('data-bs-title', original);
                    el.setAttribute('title', original);
                    el.classList.remove('text-success');
                    if (tip) {
                        tip.setContent({ '.tooltip-inner': original });
                        tip.hide();
                    }
                }, 1200);
            }

            document.querySelectorAll('.case-copy-value').forEach(function (el) {
                el.addEventListener('click', function () {
                    var text = (el.getAttribute('data-copy-text') || '').trim();
                    if (!text) return;
                    copyText(text).then(function () {
                        flashCopied(el);
                    }).catch(function () {
                        // cichy fallback — bez native alert
                    });
                });
            });

            var unlinkModalEl = document.getElementById('bankPaymentUnlinkModal');
            var unlinkForm = document.getElementById('bankPaymentUnlinkForm');
            var unlinkSummary = document.getElementById('bankPaymentUnlinkSummary');
            if (unlinkModalEl && unlinkForm) {
                unlinkModalEl.addEventListener('show.bs.modal', function (event) {
                    var btn = event.relatedTarget;
                    if (!btn) return;
                    unlinkForm.setAttribute('action', btn.getAttribute('data-unlink-url') || '');
                    if (unlinkSummary) {
                        unlinkSummary.textContent = btn.getAttribute('data-unlink-summary') || '—';
                    }
                });
            }

            var contactDeleteModalEl = document.getElementById('caseContactDeleteModal');
            var contactDeleteForm = document.getElementById('caseContactDeleteForm');
            var contactDeleteSummary = document.getElementById('caseContactDeleteSummary');
            if (contactDeleteModalEl && contactDeleteForm && contactDeleteSummary && window.bootstrap) {
                var contactDeleteModal = window.bootstrap.Modal.getOrCreateInstance(contactDeleteModalEl);
                document.querySelectorAll('.case-contact-delete-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        contactDeleteForm.setAttribute('action', btn.getAttribute('data-action') || '');
                        contactDeleteSummary.textContent = btn.getAttribute('data-summary') || '—';
                        contactDeleteModal.show();
                    });
                });
            }

            var modalEl = document.getElementById('bankTransactionLinkConfirmModal');
            var form = document.getElementById('bankTransactionLinkConfirmForm');
            var registerInput = document.getElementById('bankTransactionLinkRegisterIfirma');
            var summaryEl = document.getElementById('bankTransactionLinkSummary');
            var descriptionEl = document.getElementById('bankTransactionLinkDescription');
            var infoEl = document.getElementById('bankTransactionLinkIfirmaInfo');
            var submitBtn = document.getElementById('bankTransactionLinkSubmit');
            var searchForm = document.getElementById('bankTransferSearchForm');
            var searchInput = document.getElementById('bank_search');
            var amountInput = document.getElementById('bank_amount');
            var afterOrderInput = document.getElementById('bank_after_order');
            var unlinkedOnlyInput = document.getElementById('bank_unlinked_only');
            var exactSearchInput = document.getElementById('bank_search_exact');
            var searchStatus = document.getElementById('bankTransferSearchStatus');
            var searchResults = document.getElementById('bankTransferSearchResults');
            var searchResetBtn = document.getElementById('bankTransferSearchResetBtn');
            var searchClearBtn = document.getElementById('bankTransferSearchClearBtn');
            var searchUrl = @json(route('accounting.collections.bank-transactions.search', $case));
            var defaultAmount = amountInput ? amountInput.value : '';
            var bankPaymentsHeader = document.getElementById('caseBankPaymentsHeader');
            var bankPaymentsCollapseEl = document.getElementById('caseBankPaymentsCollapse');
            var bankPaymentsImportLink = document.getElementById('caseBankPaymentsImportLink');
            var defaultSearchHint = 'Wpisz frazę i kliknij „Szukaj przelewu”. Domyślnie wyniki obejmują tylko wpływy bez zaakceptowanego/ignorowanego powiązania. Przy numerze FV/KSeF zaznacz dokładne dopasowanie (lupka przy FV/KSeF robi to automatycznie).';
            var bankFiltersStorageKey = 'accounting_collections_bank_filters_v1';

            function saveBankFilters() {
                try {
                    localStorage.setItem(bankFiltersStorageKey, JSON.stringify({
                        unlinked_only: !!(unlinkedOnlyInput && unlinkedOnlyInput.checked),
                        after_order: !!(afterOrderInput && afterOrderInput.checked),
                        search_exact: !!(exactSearchInput && exactSearchInput.checked),
                    }));
                } catch (e) {
                    // ignore
                }
            }

            function restoreBankFilters() {
                try {
                    var raw = localStorage.getItem(bankFiltersStorageKey);
                    if (!raw) {
                        return;
                    }
                    var data = JSON.parse(raw);
                    if (!data || typeof data !== 'object') {
                        return;
                    }
                    if (unlinkedOnlyInput && typeof data.unlinked_only === 'boolean') {
                        unlinkedOnlyInput.checked = data.unlinked_only;
                    }
                    if (afterOrderInput && typeof data.after_order === 'boolean') {
                        afterOrderInput.checked = data.after_order;
                    }
                    if (exactSearchInput && typeof data.search_exact === 'boolean') {
                        exactSearchInput.checked = data.search_exact;
                    }
                } catch (e) {
                    // keep HTML defaults
                }
            }

            restoreBankFilters();
            [unlinkedOnlyInput, afterOrderInput, exactSearchInput].forEach(function (input) {
                if (!input) {
                    return;
                }
                input.addEventListener('change', saveBankFilters);
            });

            function setBankPaymentsExpanded(expanded) {
                if (!bankPaymentsHeader || !bankPaymentsCollapseEl) {
                    return;
                }
                bankPaymentsCollapseEl.classList.toggle('show', expanded);
                bankPaymentsHeader.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            }

            if (bankPaymentsHeader && bankPaymentsCollapseEl) {
                function toggleBankPayments() {
                    setBankPaymentsExpanded(!bankPaymentsCollapseEl.classList.contains('show'));
                }

                bankPaymentsHeader.addEventListener('click', function (event) {
                    if (event.target.closest('#caseBankPaymentsImportLink')) {
                        return;
                    }
                    event.preventDefault();
                    toggleBankPayments();
                });
                bankPaymentsHeader.addEventListener('keydown', function (event) {
                    if (event.key !== 'Enter' && event.key !== ' ') {
                        return;
                    }
                    if (event.target.closest('#caseBankPaymentsImportLink')) {
                        return;
                    }
                    event.preventDefault();
                    toggleBankPayments();
                });

                if (bankPaymentsImportLink) {
                    bankPaymentsImportLink.addEventListener('click', function (event) {
                        event.stopPropagation();
                    });
                }
            }

            function esc(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function setSearchStatus(message) {
                if (searchStatus) searchStatus.textContent = message;
            }

            function renderCandidates(candidates) {
                if (!searchResults) return;
                if (!candidates.length) {
                    searchResults.innerHTML = '<div class="text-muted small">Brak kandydatów dla podanych kryteriów.</div>';
                    return;
                }

                var rows = candidates.map(function (c) {
                    var actionsHtml;
                    if (c.is_linkable === false) {
                        actionsHtml = '<span class="badge text-bg-secondary">'
                            + esc(c.link_status_label || 'Przypisany')
                            + '</span>';
                    } else {
                        actionsHtml = '<div class="d-flex flex-column flex-md-row gap-1 justify-content-end">'
                            + '<button type="button" class="btn btn-outline-primary btn-sm bank-link-confirm-btn"'
                            + ' data-action="' + esc(c.link_url) + '"'
                            + ' data-register-ifirma="0"'
                            + ' data-summary="' + esc(c.summary) + '"'
                            + ' data-description="' + esc(c.description_confirm) + '">Powiąż lokalnie</button>'
                            + (c.amount_matches
                                ? '<button type="button" class="btn btn-success btn-sm bank-link-confirm-btn"'
                                  + ' data-action="' + esc(c.link_url) + '"'
                                  + ' data-register-ifirma="1"'
                                  + ' data-summary="' + esc(c.summary) + '"'
                                  + ' data-description="' + esc(c.description_confirm) + '">+ wpłata iFirma</button>'
                                : '')
                            + '</div>';
                    }

                    return '<tr>'
                        + '<td class="small">' + esc(c.operation_date || '—') + '</td>'
                        + '<td class="text-end fw-semibold text-nowrap">'
                        + esc(c.amount_formatted) + ' ' + esc(c.currency)
                        + (!c.amount_matches ? '<div class="small text-warning">inna kwota</div>' : '')
                        + '</td>'
                        + '<td class="small text-break" style="max-width: 34rem;">'
                        + '<div class="fw-semibold">' + esc(c.account_label || '—') + '</div>'
                        + '<div>' + esc(c.description_short || '—') + '</div>'
                        + '</td>'
                        + '<td class="small">'
                        + '<a href="' + esc(c.import_url) + '" class="text-decoration-none">Import #' + esc(c.import_id) + '</a>'
                        + '<div class="text-muted">' + esc(c.import_filename || '') + '</div>'
                        + '</td>'
                        + '<td class="text-end">' + actionsHtml + '</td></tr>';
                }).join('');

                searchResults.innerHTML =
                    '<div class="table-responsive"><table class="table table-sm align-middle mb-0">'
                    + '<thead class="table-light"><tr>'
                    + '<th>Data</th><th class="text-end">Kwota</th><th>Nadawca / opis</th><th>Import</th><th class="text-end">Akcja</th>'
                    + '</tr></thead><tbody>' + rows + '</tbody></table></div>';
            }

            async function runBankTransferSearch() {
                var q = (searchInput && searchInput.value ? searchInput.value : '').trim();
                if (q.length < 2) {
                    setSearchStatus('Wpisz co najmniej 2 znaki w pole wyszukiwania.');
                    return;
                }

                setSearchStatus('Szukam…');
                var params = new URLSearchParams();
                params.set('bank_search', q);
                if (amountInput && amountInput.value.trim() !== '') {
                    params.set('bank_amount', amountInput.value.trim());
                }
                if (afterOrderInput && afterOrderInput.checked) {
                    params.set('bank_after_order', '1');
                } else {
                    params.set('bank_after_order', '0');
                }
                if (unlinkedOnlyInput && unlinkedOnlyInput.checked) {
                    params.set('bank_unlinked_only', '1');
                } else {
                    params.set('bank_unlinked_only', '0');
                }
                if (exactSearchInput && exactSearchInput.checked) {
                    params.set('bank_search_exact', '1');
                } else {
                    params.set('bank_search_exact', '0');
                }

                try {
                    var response = await fetch(searchUrl + '?' + params.toString(), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    var payload = await response.json();
                    if (!response.ok) {
                        throw new Error(payload.message || 'Błąd wyszukiwania');
                    }
                    var candidates = payload.candidates || [];
                    renderCandidates(candidates);
                    setSearchStatus(candidates.length
                        ? ('Znaleziono: ' + candidates.length)
                        : 'Brak kandydatów dla podanych kryteriów.');
                } catch (e) {
                    if (searchResults) {
                        searchResults.innerHTML = '<div class="text-danger small">Nie udało się wyszukać przelewów.</div>';
                    }
                    setSearchStatus(e.message || 'Nie udało się wyszukać.');
                }
            }

            function fillBankSearchFromCaseValue(text) {
                var value = String(text || '').trim();
                if (!value || !searchInput) {
                    return;
                }
                setBankPaymentsExpanded(true);
                searchInput.value = value;
                if (exactSearchInput) {
                    exactSearchInput.checked = true;
                    saveBankFilters();
                }
                searchInput.focus();
                runBankTransferSearch();
            }

            document.querySelectorAll('.case-fill-bank-search').forEach(function (el) {
                el.addEventListener('click', function () {
                    fillBankSearchFromCaseValue(el.getAttribute('data-bank-search-text') || '');
                });
            });

            if (searchForm) {
                searchForm.addEventListener('submit', function (event) {
                    event.preventDefault();
                    runBankTransferSearch();
                });
            }
            if (searchClearBtn) {
                searchClearBtn.addEventListener('click', function () {
                    if (searchInput) {
                        searchInput.value = '';
                        searchInput.focus();
                    }
                    if (searchResults) {
                        searchResults.innerHTML = '<div class="text-muted small">Brak wyników — wykonaj wyszukiwanie.</div>';
                    }
                    setSearchStatus(defaultSearchHint);
                });
            }
            if (searchResetBtn) {
                searchResetBtn.addEventListener('click', function () {
                    if (searchInput) searchInput.value = '';
                    if (amountInput) amountInput.value = defaultAmount || '';
                    if (afterOrderInput) afterOrderInput.checked = true;
                    if (unlinkedOnlyInput) unlinkedOnlyInput.checked = true;
                    if (exactSearchInput) exactSearchInput.checked = false;
                    saveBankFilters();
                    if (searchResults) {
                        searchResults.innerHTML = '<div class="text-muted small">Brak wyników — wykonaj wyszukiwanie.</div>';
                    }
                    setSearchStatus(defaultSearchHint);
                });
            }

            if (!modalEl || !form || !registerInput || !summaryEl || !descriptionEl || !infoEl || !submitBtn || !window.bootstrap) {
                return;
            }

            var modal = new window.bootstrap.Modal(modalEl);
            document.addEventListener('click', function (event) {
                var btn = event.target.closest('.bank-link-confirm-btn');
                if (!btn) return;

                var registerIfirma = btn.getAttribute('data-register-ifirma') === '1';
                form.setAttribute('action', btn.getAttribute('data-action') || '');
                registerInput.value = registerIfirma ? '1' : '0';
                summaryEl.textContent = btn.getAttribute('data-summary') || '—';
                descriptionEl.textContent = btn.getAttribute('data-description') || '—';
                infoEl.classList.toggle('d-none', !registerIfirma);
                submitBtn.textContent = registerIfirma ? 'Powiąż + wpłata iFirma' : 'Powiąż lokalnie';
                submitBtn.className = registerIfirma ? 'btn btn-success' : 'btn btn-primary';
                modal.show();
            });
        });
    </script>
</x-app-layout>
