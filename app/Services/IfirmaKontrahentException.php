<?php

namespace App\Services;

use RuntimeException;

/**
 * Dedykowany wyjątek dla błędów gate'ingu w IfirmaKontrahentBuilder.
 *
 * Rozróżnia warunki „gate” (kontroler → HTTP 400), gdy dana ścieżka
 * wystawiania dokumentu wymaga aktywnego Podmiotu3 (ksef_entity_source =
 * 'recipient'), a konfiguracja zamówienia na to nie pozwala.
 *
 * Błędy walidacji konfiguracji Podmiotu3 (nieobsługiwana rola / id_type /
 * brak NIP dla JST / brak danych recipient_*) są nadal rzucane jako bazowy
 * RuntimeException z IfirmaAdditionalEntityMapper (kontroler → HTTP 422).
 */
class IfirmaKontrahentException extends RuntimeException {}
