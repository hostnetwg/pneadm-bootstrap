<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-semibold fs-4 text-dark mb-0">
                {{ __('Przykładowy podgląd artykułu') }}
            </h2>
            <div class="d-flex gap-2">
                <a href="{{ route('articles.create') }}" class="btn btn-primary">Dodaj artykuł</a>
                <a href="{{ route('articles.index') }}" class="btn btn-outline-secondary">Lista artykułów</a>
            </div>
        </div>
    </x-slot>

    <div class="py-3">
        <div class="container-fluid px-4">
            <div class="alert alert-info">
                To przykładowy, statyczny podgląd układu wpisu. Właściwe artykuły dodasz przez formularz `Artykuły -> Lista artykułów -> Dodaj artykuł`.
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-body p-4">
                            <div class="bg-primary bg-gradient text-white rounded p-5 mb-4 text-center">
                                <div class="small text-white-50 mb-2">Przykładowa grafika główna</div>
                                <div class="display-6 fw-bold">Nowoczesna edukacja w praktyce</div>
                            </div>

                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge bg-success">Opublikowany</span>
                                <span class="badge bg-light text-dark">Komentarze wyłączone</span>
                            </div>

                            <h1 class="display-6 fw-bold mb-3">Jak przygotować szkołę do pracy z nowymi technologiami?</h1>

                            <div class="text-muted mb-4">
                                Opublikowano: {{ now()->format('Y-m-d H:i') }}
                                <span class="mx-1">|</span> Autor: Zespół PNE
                            </div>

                            <p class="lead">
                                To miejsce na krótki opis artykułu. Pojawi się na liście bloga i może zostać użyte jako opis SEO.
                            </p>

                            <div class="article-preview-content">
                                <p>
                                    Artykuły publikowane z panelu będą wyświetlane publicznie na stronie <strong>pnedu.pl/blog</strong>.
                                    Ten podgląd pokazuje układ treści, meta informacji oraz przyszłego miejsca na komentarze.
                                </p>
                                <h2 class="h4">Co warto opisać w artykule?</h2>
                                <ul>
                                    <li>praktyczne wskazówki dla nauczycieli i dyrektorów,</li>
                                    <li>case studies ze szkoleń i wdrożeń,</li>
                                    <li>omówienia narzędzi oraz trendów edukacyjnych.</li>
                                </ul>
                                <p>
                                    Pełna treść artykułu jest zapisywana jako podstawowy HTML, więc można używać nagłówków, akapitów, list, linków i tabel.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Przykładowe SEO</h5>
                        </div>
                        <div class="card-body">
                            <dl class="mb-0">
                                <dt>Meta title</dt>
                                <dd>Jak przygotować szkołę do pracy z nowymi technologiami?</dd>

                                <dt>Meta description</dt>
                                <dd>Praktyczne wskazówki dla szkół, które chcą wdrażać nowe technologie w sposób bezpieczny i uporządkowany.</dd>

                                <dt>Adres publiczny</dt>
                                <dd><code>/blog/jak-przygotowac-szkole-do-pracy-z-nowymi-technologiami</code></dd>
                            </dl>
                        </div>
                    </div>

                    <div class="card border-primary">
                        <div class="card-body">
                            <h5 class="card-title">Następny krok</h5>
                            <p class="text-muted">
                                Dodaj pierwszy artykuł jako szkic, sprawdź podgląd, a potem zmień status na opublikowany.
                            </p>
                            <a href="{{ route('articles.create') }}" class="btn btn-primary">Dodaj artykuł</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
