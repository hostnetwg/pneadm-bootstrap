<div class="modal fade" id="importPubligoCsvModal" tabindex="-1" aria-labelledby="importPubligoCsvModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="importPubligoCsvModalLabel">
                    <i class="bi bi-file-earmark-spreadsheet me-2"></i>Import dostępów z CSV (Publigo)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <form action="{{ route('online-courses.enrollments.import', $online_course) }}" method="POST" enctype="multipart/form-data" data-loading-submit data-loading-text="Importuję…">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label fw-bold">Plik CSV z nowoczesna-edukacja.pl</label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv,text/csv,text/plain" required>
                        <div class="form-text">
                            Kolumny: <strong>ID</strong>, <strong>E-mail uczestnika</strong>, <strong>Imię i nazwisko</strong>, <strong>Numer telefonu</strong>, <strong>Dostęp wygasa</strong>.
                            Postęp lekcji nie jest importowany.
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="skip_expired" name="skip_expired">
                        <label class="form-check-label" for="skip_expired">
                            Pomiń osoby z już wygasłym dostępem
                        </label>
                        <div class="form-text">
                            Zaznacz, gdy w pliku są stare terminy (np. kurs na 1 rok). „Bez limitu” zawsze trafia do importu.
                        </div>
                    </div>
                    <div class="alert alert-info mb-0">
                        <ul class="mb-0 small">
                            <li>E-mail już przypisany do tego kursu jest pomijany (bez nadpisania).</li>
                            <li>Data wygaśnięcia jest brana z wiersza CSV (czas polski). „Bez limitu” = dostęp bezterminowy.</li>
                            <li>Nie tworzy kont na pnedu.pl i nie wysyła e-maili — tylko nadaje dostęp.</li>
                            <li>ID z Publigo i telefon są zapisywane przy dostępie.</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-success" data-loading-text="Importuję…">
                        <i class="bi bi-upload me-1"></i>Importuj
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
