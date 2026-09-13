<?php

namespace App\Services;

use App\Models\CoursePriceVariant;
use App\Models\PriceOfferHistory;
use App\Models\ProductPrice;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PriceOmnibusService
{
    public function sync(ProductPrice|CoursePriceVariant $subject): void
    {
        $type = $this->subjectType($subject);
        $now = CarbonImmutable::now('UTC');

        if ($subject->is_active === false) {
            $this->closeOpen($type, (int) $subject->id, $now);

            return;
        }

        $live = $this->liveOffer($subject, $now);
        $open = $this->openRow($type, (int) $subject->id);

        if (
            $open
            && (float) $open->offered_price === $live['price']
            && $open->kind === $live['kind']
        ) {
            return;
        }

        $this->closeOpen($type, (int) $subject->id, $now);

        PriceOfferHistory::query()->create([
            'subject_type' => $type,
            'subject_id' => $subject->id,
            'offered_price' => $live['price'],
            'kind' => $live['kind'],
            'effective_from' => $now,
            'effective_to' => null,
        ]);
    }

    public function lowestFor(ProductPrice|CoursePriceVariant $subject, ?CarbonInterface $at = null): ?string
    {
        $at = CarbonImmutable::instance($at ?? now('UTC'))->utc();
        if (! $this->isPromotionNow($subject, $at)) {
            return null;
        }

        $type = $this->subjectType($subject);
        $live = $this->liveOffer($subject, $at);
        $reductionStart = $this->reductionStartedAt($type, (int) $subject->id, $live, $at);
        $windowFrom = $reductionStart->subDays(30);
        $lowest = $this->lowestInWindow($type, (int) $subject->id, $windowFrom, $reductionStart);

        if ($lowest === null) {
            return number_format((float) $subject->price, 2, '.', '');
        }

        return number_format($lowest, 2, '.', '');
    }

    public function exclude(PriceOfferHistory $row, int $userId, string $reason): void
    {
        $row->update([
            'excluded_from_omnibus' => true,
            'excluded_reason' => trim($reason) !== '' ? trim($reason) : 'Korekta ręczna',
            'excluded_by' => $userId,
            'excluded_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    public function restore(PriceOfferHistory $row): void
    {
        $row->update([
            'excluded_from_omnibus' => false,
            'excluded_reason' => null,
            'excluded_by' => null,
            'excluded_at' => null,
        ]);
    }

    /**
     * @return Collection<int, PriceOfferHistory>
     */
    public function history(ProductPrice|CoursePriceVariant $subject, int $limit = 30): Collection
    {
        return PriceOfferHistory::query()
            ->where('subject_type', $this->subjectType($subject))
            ->where('subject_id', $subject->id)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function close(ProductPrice|CoursePriceVariant $subject): void
    {
        $this->closeOpen($this->subjectType($subject), (int) $subject->id, CarbonImmutable::now('UTC'));
    }

    public function syncAll(): int
    {
        $count = 0;

        ProductPrice::query()->each(function (ProductPrice $price) use (&$count): void {
            $this->sync($price);
            $count++;
        });

        CoursePriceVariant::query()->each(function (CoursePriceVariant $variant) use (&$count): void {
            $this->sync($variant);
            $count++;
        });

        return $count;
    }

    /**
     * @return array{price: float, kind: string}
     */
    public function liveOffer(ProductPrice|CoursePriceVariant $subject, ?CarbonInterface $at = null): array
    {
        $at = CarbonImmutable::instance($at ?? now('UTC'))->utc();
        $promo = $this->isPromotionNow($subject, $at);
        $price = $subject instanceof ProductPrice
            ? (float) $subject->currentPrice($at)
            : $subject->getCurrentPrice();

        return [
            'price' => $price,
            'kind' => $promo ? PriceOfferHistory::KIND_PROMOTIONAL : PriceOfferHistory::KIND_REGULAR,
        ];
    }

    private function isPromotionNow(ProductPrice|CoursePriceVariant $subject, CarbonInterface $at): bool
    {
        if ($subject instanceof ProductPrice) {
            return $subject->isPromotionActive($at);
        }

        return $subject->isPromotionActive();
    }

    /**
     * @param  array{price: float, kind: string}  $live
     */
    private function reductionStartedAt(string $type, int $id, array $live, CarbonImmutable $at): CarbonImmutable
    {
        $rows = PriceOfferHistory::query()
            ->where('subject_type', $type)
            ->where('subject_id', $id)
            ->where('excluded_from_omnibus', false)
            ->where('effective_from', '<=', $at)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        $start = $at;
        foreach ($rows as $row) {
            if (
                $row->kind === PriceOfferHistory::KIND_PROMOTIONAL
                && (float) $row->offered_price === $live['price']
            ) {
                $start = CarbonImmutable::instance($row->effective_from)->utc();

                continue;
            }

            break;
        }

        return $start;
    }

    private function lowestInWindow(string $type, int $id, CarbonImmutable $from, CarbonImmutable $to): ?float
    {
        $rows = PriceOfferHistory::query()
            ->where('subject_type', $type)
            ->where('subject_id', $id)
            ->where('excluded_from_omnibus', false)
            ->where('effective_from', '<', $to)
            ->where(function ($query) use ($from) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>', $from);
            })
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        return (float) $rows->min(fn (PriceOfferHistory $row) => (float) $row->offered_price);
    }

    private function openRow(string $type, int $id): ?PriceOfferHistory
    {
        return PriceOfferHistory::query()
            ->where('subject_type', $type)
            ->where('subject_id', $id)
            ->whereNull('effective_to')
            ->orderByDesc('id')
            ->first();
    }

    private function closeOpen(string $type, int $id, CarbonImmutable $at): void
    {
        PriceOfferHistory::query()
            ->where('subject_type', $type)
            ->where('subject_id', $id)
            ->whereNull('effective_to')
            ->update(['effective_to' => $at, 'updated_at' => $at]);
    }

    private function subjectType(ProductPrice|CoursePriceVariant $subject): string
    {
        return $subject instanceof ProductPrice
            ? PriceOfferHistory::SUBJECT_PRODUCT_PRICE
            : PriceOfferHistory::SUBJECT_COURSE_VARIANT;
    }
}
