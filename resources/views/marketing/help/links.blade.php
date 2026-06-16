<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-semibold fs-4 text-dark">
            {{ __('Linki kampanii i parametry UTM') }}
        </h2>
    </x-slot>

    <div class="px-3 py-3">
        <div class="container col-lg-9">
            <div class="mb-3">
                <a href="{{ route('marketing-campaigns.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Kampanie marketingowe
                </a>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h4 class="h5 fw-semibold mb-3">Po co są parametry UTM?</h4>
                    <p class="mb-3">
                        Parametry w adresie URL (<code>utm_source</code>, <code>utm_medium</code>, <code>utm_campaign</code>, opcjonalnie <code>utm_content</code>)
                        pozwalają rozpoznać, <strong>skąd przyszedł użytkownik</strong> i która kampania wygenerowała zamówienie.
                        W adm kod kampanii trafia do pola <code>fb_source</code> w zamówieniu; w Google Analytics widać te same wartości w raportach.
                    </p>

                    <table class="table table-sm table-bordered align-middle mb-4">
                        <thead class="table-light">
                            <tr>
                                <th>Parametr</th>
                                <th>Skąd bierze wartość</th>
                                <th>Przykład</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <tr>
                                <td><code>utm_source</code></td>
                                <td>Typ źródła w adm — <strong>platforma</strong>, nie adres e-mail</td>
                                <td><code>newsletter</code>, <code>facebook</code>, <code>pnedu</code></td>
                            </tr>
                            <tr>
                                <td><code>utm_medium</code></td>
                                <td>Domyślnie z typu źródła; opcjonalnie nadpisanie w kampanii</td>
                                <td><code>email</code>, <code>paid</code>, <code>social</code></td>
                            </tr>
                            <tr>
                                <td><code>utm_campaign</code></td>
                                <td><strong>Kod kampanii</strong> w adm</td>
                                <td><code>1241</code></td>
                            </tr>
                            <tr>
                                <td><code>utm_content</code></td>
                                <td>Opcjonalnie w kampanii — wariant linku w tej samej akcji</td>
                                <td><code>cta-hero</code>, <code>remarketing</code></td>
                            </tr>
                        </tbody>
                    </table>

                    <h4 class="h5 fw-semibold mb-3">Trzy warianty linku z generatora</h4>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="card h-100 border-primary-subtle">
                                <div class="card-body small">
                                    <span class="badge bg-primary mb-2">1 · Pełny UTM</span>
                                    <p class="mb-0">Długi adres z parametrami. Używaj w newsletterach HTML i gdy potrzebujesz pełnej widoczności UTM w GA.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 border-success-subtle">
                                <div class="card-body small">
                                    <span class="badge bg-success mb-2">2 · Krótki <code>/l/{kod}</code></span>
                                    <p class="mb-0">Np. <code>pnedu.pl/l/1241</code>. Po kliknięciu przekierowanie 302 na pełny link UTM. Wygodny w opisie YouTube, poście Facebook, SMS.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 border-secondary-subtle">
                                <div class="card-body small">
                                    <span class="badge bg-secondary mb-2">3 · Legacy <code>fb</code></span>
                                    <p class="mb-0">Stary format <code>?fb=1241</code>. Tylko w już opublikowanych materiałach — w nowych kampaniach wybieraj UTM lub krótki.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h4 class="h5 fw-semibold mb-3">Co się dzieje po kliknięciu?</h4>
                    <ol class="small mb-4">
                        <li class="mb-2">Użytkownik trafia na stronę szkolenia (opis lub formularz — zależnie od ustawień kampanii).</li>
                        <li class="mb-2">pnedu.pl zapisuje kod kampanii w sesji i cookie (okno <strong>7 dni</strong>).</li>
                        <li class="mb-2">Przy wysłaniu zamówienia kod trafia do <code>form_orders.fb_source</code> — widzisz go w lejku i na liście zamówień.</li>
                        <li>Wejścia na opis/formularz liczy <code>course_page_stats_daily</code> (lejek w adm).</li>
                    </ol>

                    <h4 class="h5 fw-semibold mb-3">Zasady na co dzień</h4>
                    <ul class="small mb-0">
                        <li>Nowe kampanie → kieruj na <strong>konkretne szkolenie</strong>, nie na stronę główną.</li>
                        <li>Social media / YouTube → preferuj <strong>link krótki</strong>; newsletter → pełny UTM.</li>
                        <li><code>utm_source</code> = platforma (<code>newsletter</code>), nie adres nadawcy — nadawcę opisz w nazwie typu źródła.</li>
                        <li>Jeden kod kampanii = <code>utm_campaign</code> = segment <code>/l/{kod}</code> = historyczne <code>fb</code>.</li>
                    </ul>
                </div>
            </div>

            <div class="card border-light bg-light">
                <div class="card-body small text-muted">
                    <i class="bi bi-file-earmark-text"></i>
                    Dokumentacja techniczna dla developerów: <code>docs/MARKETING.md</code> w repozytorium pneadm
                    (architektura, audyt typów źródeł, roadmap GA4).
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
