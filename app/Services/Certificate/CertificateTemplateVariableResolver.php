<?php

namespace App\Services\Certificate;

use Carbon\Carbon;

/**
 * Podstawia zmienne w polach tekstowych szablonu zaświadczenia (np. event_text).
 *
 * Zmienne (wielkość liter nie ma znaczenia):
 * - {data_zakonczenia} — data ukończenia / wydania (effective_completion_date lub start_date kursu)
 * - {data_rozpoczecia} — data rozpoczęcia szkolenia (start_date)
 * - {data_konca} — data zakończenia szkolenia (end_date)
 * - {czas_trwania} — „w wymiarze X minut, ” gdy włączony show_duration i X > 0, inaczej pusty string
 * - {wymiar_minut} — sama liczba minut (bez tekstu otaczającego)
 */
class CertificateTemplateVariableResolver
{
  /**
   * Domyślny tekst wydarzenia (nowe szablony i event_text = null).
   */
  public const DEFAULT_EVENT_TEXT = 'zorganizowanym w dniu {data_zakonczenia} r. {czas_trwania}przez';

  /**
   * @return array<string, string> klucz zmiennej => opis PL
   */
  public static function variableHelp(): array
  {
    return [
      '{data_zakonczenia}' => 'Data ukończenia / wydania zaświadczenia (DD.MM.RRRR)',
      '{data_rozpoczecia}' => 'Data rozpoczęcia szkolenia (DD.MM.RRRR)',
      '{data_konca}' => 'Data zakończenia szkolenia (DD.MM.RRRR)',
      '{czas_trwania}' => 'Fragment „w wymiarze X minut, ” (pusty, gdy brak czasu lub wyłączone w szablonie)',
      '{wymiar_minut}' => 'Liczba minut trwania szkolenia',
    ];
  }

  /**
   * Wartość pola event_text w formularzu edycji (null / brak klucza → domyślny szablon ze zmiennymi).
   *
   * @param  array<string, mixed>  $blockConfig
   */
  public static function formEventTextValue(array $blockConfig): string
  {
    if (! array_key_exists('event_text', $blockConfig)) {
      return self::DEFAULT_EVENT_TEXT;
    }

    return $blockConfig['event_text'] ?? self::DEFAULT_EVENT_TEXT;
  }

  /**
   * @param  array{
   *     course?: object|null,
   *     effective_completion_date?: string|null,
   *     duration_minutes?: int,
   *     show_duration?: bool,
   * }  $context
   */
  public function resolveEventText(?string $eventText, array $context): ?string
  {
    if ($eventText === null) {
      $eventText = self::DEFAULT_EVENT_TEXT;
    }

    if ($eventText === '') {
      return null;
    }

    return $this->substitute($eventText, $context);
  }

  /**
   * @param  array{
   *     course?: object|null,
   *     effective_completion_date?: string|null,
   *     duration_minutes?: int,
   *     show_duration?: bool,
   * }  $context
   */
  public function substitute(string $template, array $context): string
  {
    $values = $this->buildVariableMap($context);

    return preg_replace_callback(
      '/\{([a-z0-9_]+)\}/i',
      static function (array $matches) use ($values): string {
        $key = strtolower($matches[1]);

        return $values[$key] ?? $matches[0];
      },
      $template
    ) ?? $template;
  }

  /**
   * @param  array{
   *     course?: object|null,
   *     effective_completion_date?: string|null,
   *     duration_minutes?: int,
   *     show_duration?: bool,
   * }  $context
   * @return array<string, string>
   */
  public function buildVariableMap(array $context): array
  {
    $course = $context['course'] ?? null;
    $durationMinutes = max(0, (int) ($context['duration_minutes'] ?? 0));
    $showDuration = (bool) ($context['show_duration'] ?? false);

    $completionDate = $this->resolveCompletionDate($course, $context['effective_completion_date'] ?? null);
    $startDate = $this->formatDate($course->start_date ?? null);
    $endDate = $this->formatDate($course->end_date ?? null);

    $durationPhrase = ($showDuration && $durationMinutes > 0)
      ? "w wymiarze {$durationMinutes} minut, "
      : '';

    return [
      'data_zakonczenia' => $completionDate,
      'data_rozpoczecia' => $startDate,
      'data_konca' => $endDate,
      'czas_trwania' => $durationPhrase,
      'wymiar_minut' => $durationMinutes > 0 ? (string) $durationMinutes : '',
    ];
  }

  protected function resolveCompletionDate(?object $course, ?string $effectiveCompletionDate): string
  {
    if ($effectiveCompletionDate) {
      return $this->formatDate($effectiveCompletionDate);
    }

    if ($course && ! empty($course->start_date)) {
      return $this->formatDate($course->start_date);
    }

    return Carbon::now()->format('d.m.Y');
  }

  protected function formatDate(mixed $value): string
  {
    if ($value === null || $value === '') {
      return '';
    }

    try {
      return Carbon::parse($value)->format('d.m.Y');
    } catch (\Throwable) {
      return '';
    }
  }
}
