<?php

namespace App\Services\GrowthOS;

use App\Models\Course;
use App\Models\CoursePriceVariant;
use App\Services\PriceOmnibusService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Deterministic paid-course block for Sendy PNE main-mail (DEC-050).
 * AI never chooses courses or prices.
 */
final class PaidCourseOfferBuilder
{
    public const MAX_COURSES = 5;

    public function __construct(
        private readonly PriceOmnibusService $omnibus,
    ) {}

    /**
     * @return array{captured_at: string, courses: list<array<string, mixed>>}
     */
    public function snapshot(?int $growthCampaignId = null, ?CarbonInterface $at = null): array
    {
        $at = $at ?? now();
        $courses = [];

        foreach ($this->candidateCourses($at) as $course) {
            $priced = $this->priceCard($course, $growthCampaignId);
            if ($priced === null) {
                continue;
            }
            $courses[] = $priced;
            if (count($courses) >= self::MAX_COURSES) {
                break;
            }
        }

        return [
            'captured_at' => $at->toIso8601String(),
            'courses' => $courses,
        ];
    }

    /**
     * @return Collection<int, Course>
     */
    private function candidateCourses(CarbonInterface $at): Collection
    {
        return Course::query()
            ->with(['instructor:id,first_name,last_name', 'priceVariants' => fn ($q) => $q->where('is_active', true)])
            ->where('is_paid', true)
            ->where('show_on_pnedu', true)
            ->where('is_active', true)
            ->where('end_date', '>=', $at)
            ->orderBy('start_date')
            ->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function priceCard(Course $course, ?int $growthCampaignId): ?array
    {
        /** @var Collection<int, CoursePriceVariant> $variants */
        $variants = $course->priceVariants
            ->filter(fn (CoursePriceVariant $variant): bool => (bool) $variant->is_active)
            ->values();

        if ($variants->isEmpty()) {
            return null;
        }

        $priced = $variants->map(fn (CoursePriceVariant $variant): array => [
            'variant' => $variant,
            'price' => $variant->getCurrentPrice(),
        ]);

        $lowest = $priced->sortBy('price')->first();
        if (! is_array($lowest)) {
            return null;
        }

        /** @var CoursePriceVariant $chosen */
        $chosen = $lowest['variant'];
        $price = (float) $lowest['price'];
        $many = $priced->count() > 1;
        $promoActive = $chosen->isPromotionActive();
        $omnibusLowest = $promoActive ? $this->omnibus->lowestFor($chosen) : null;

        $base = rtrim((string) config('marketing.pnedu_public_url'), '/');
        $url = $base.'/courses/'.$course->id;
        $url = $this->withUtm($url, $growthCampaignId, 'paid-course-'.$course->id);

        return [
            'id' => (int) $course->id,
            'title' => $this->plain((string) $course->title),
            'start_at' => $course->start_date?->toIso8601String(),
            'end_at' => $course->end_date?->toIso8601String(),
            'date_label' => $this->dateLabel($course),
            'time_label' => $this->timeLabel($course),
            'duration_label' => $this->durationLabel($course),
            'instructor_name' => $this->plain((string) ($course->instructor?->name ?? '')),
            'price_amount' => number_format($price, 2, '.', ''),
            'price_label' => ($many ? 'od ' : '').$this->formatPln($price).' brutto',
            'promotion_label' => $this->promotionLabel($chosen),
            'omnibus_lowest' => $omnibusLowest,
            'omnibus_label' => $omnibusLowest !== null
                ? 'Najniższa cena z 30 dni przed obniżką: '.$this->formatPln((float) $omnibusLowest).' brutto'
                : null,
            'url' => $url,
        ];
    }

    public function allTrainingsUrl(?int $growthCampaignId = null): string
    {
        $base = rtrim((string) config('marketing.pnedu_public_url'), '/');

        return $this->withUtm($base.'/szkolenia-indywidualne', $growthCampaignId, 'all-trainings');
    }

    private function withUtm(string $url, ?int $growthCampaignId, string $content): string
    {
        $query = [
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'growth-'.($growthCampaignId ?? '0'),
            'utm_content' => $content,
        ];

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query);
    }

    private function dateLabel(Course $course): string
    {
        if ($course->start_date === null) {
            return '';
        }

        return $course->start_date->locale('pl')->translatedFormat('j F Y');
    }

    private function timeLabel(Course $course): string
    {
        if ($course->start_date === null) {
            return '';
        }

        return $course->start_date->format('H:i');
    }

    private function durationLabel(Course $course): string
    {
        if ($course->start_date === null || $course->end_date === null) {
            return '';
        }

        $minutes = max(0, $course->start_date->diffInMinutes($course->end_date));
        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest > 0 ? $hours.' godz. '.$rest.' min' : $hours.' godz.';
    }

    private function promotionLabel(CoursePriceVariant $variant): ?string
    {
        if (! $variant->isPromotionActive()) {
            return null;
        }

        if ($variant->promotion_type === 'time_limited' && $variant->promotion_end !== null) {
            return 'Cena promocyjna do '.$variant->promotion_end->timezone(config('app.timezone'))
                ->format('d.m.Y, \g\o\d\z. H:i').'.';
        }

        if ($variant->promotion_type === 'unlimited') {
            return 'Cena promocyjna.';
        }

        return null;
    }

    private function formatPln(float $amount): string
    {
        return number_format($amount, 2, ',', ' ').' zł';
    }

    private function plain(string $value): string
    {
        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
