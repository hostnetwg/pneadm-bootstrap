<?php

namespace App\Services;

use App\Models\Course;
use App\Models\FormOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rozliczenia szkoleń zamkniętych przez form_orders (bez osobnej tabeli faktur).
 */
class CourseFormOrderBillingService
{
    public const STATUS_NOT_APPLICABLE = 'not_applicable';

    public const STATUS_NO_ORDERS = 'no_orders';

    public const STATUS_NO_INVOICE = 'no_invoice';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_COMPLETE = 'complete';

    public static function hasMeaningfulInvoice(?string $invoiceNumber): bool
    {
        $n = trim((string) $invoiceNumber);

        return $n !== '' && $n !== '0';
    }

    public static function pneduBaseUrl(): string
    {
        return rtrim((string) config('services.pnedu_frontend_url', ''), '/');
    }

    public static function newOrderFormUrl(Course $course, ?int $priceVariantId = null): string
    {
        $base = self::pneduBaseUrl();
        $url = $base.'/courses/'.$course->id.'/order-form';
        if ($priceVariantId !== null && $priceVariantId > 0) {
            $url .= '?price_variant_id='.$priceVariantId;
        }

        return $url;
    }

    public static function editOrderFormUrl(Course $course, string $ident): string
    {
        $ident = trim($ident);

        return self::pneduBaseUrl().'/courses/'.$course->id.'/order-form/'.rawurlencode($ident);
    }

    /**
     * Zapytanie o zamówienia powiązane ze szkoleniem (product_id lub legacy publigo_product_id).
     */
    public static function formOrdersForCourseQuery(Course $course): Builder
    {
        return FormOrder::query()
            ->where(function ($q) use ($course) {
                $q->where('product_id', $course->id);
                if ($course->id_old !== null && $course->id_old !== '') {
                    $q->orWhere('publigo_product_id', $course->id_old);
                }
            })
            ->orderByDesc('order_date')
            ->orderByDesc('id');
    }

    /**
     * @return Collection<int, FormOrder>
     */
    public static function formOrdersForCourse(Course $course): Collection
    {
        return self::formOrdersForCourseQuery($course)
            ->with('primaryParticipant')
            ->get();
    }

    /**
     * @param  Collection<int, FormOrder>  $orders
     */
    public static function resolveBillingStatus(Course $course, Collection $orders): string
    {
        if ($course->category !== 'closed' || ! $course->is_paid) {
            return self::STATUS_NOT_APPLICABLE;
        }

        if ($orders->isEmpty()) {
            return self::STATUS_NO_ORDERS;
        }

        $invoiced = $orders->filter(fn (FormOrder $o) => self::hasMeaningfulInvoice($o->invoice_number))->count();
        $total = $orders->count();

        if ($invoiced === 0) {
            return self::STATUS_NO_INVOICE;
        }

        if ($invoiced < $total) {
            return self::STATUS_PARTIAL;
        }

        return self::STATUS_COMPLETE;
    }

    /**
     * @param  array<int, int>  $courseIds
     * @return array<int, array{status: string, orders_total: int, orders_invoiced: int}>
     */
    public static function billingSummaryByCourseIds(array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }

        $rows = DB::connection('mysql')
            ->table('courses as c')
            ->leftJoin('form_orders as fo', function ($join) {
                $join->whereNull('fo.deleted_at')
                    ->where(function ($q) {
                        $q->whereColumn('fo.product_id', 'c.id')
                            ->orWhere(function ($q2) {
                                $q2->whereNotNull('c.id_old')
                                    ->where('c.id_old', '!=', '')
                                    ->whereColumn('fo.publigo_product_id', 'c.id_old');
                            });
                    });
            })
            ->whereIn('c.id', $courseIds)
            ->where('c.category', 'closed')
            ->where('c.is_paid', 1)
            ->groupBy('c.id')
            ->select(
                'c.id as course_id',
                DB::raw('COUNT(fo.id) as orders_total'),
                DB::raw("SUM(CASE WHEN fo.invoice_number IS NOT NULL AND fo.invoice_number != '' AND fo.invoice_number != '0' THEN 1 ELSE 0 END) as orders_invoiced")
            )
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $total = (int) $row->orders_total;
            $invoiced = (int) $row->orders_invoiced;
            $status = match (true) {
                $total === 0 => self::STATUS_NO_ORDERS,
                $invoiced === 0 => self::STATUS_NO_INVOICE,
                $invoiced < $total => self::STATUS_PARTIAL,
                default => self::STATUS_COMPLETE,
            };
            $out[(int) $row->course_id] = [
                'status' => $status,
                'orders_total' => $total,
                'orders_invoiced' => $invoiced,
            ];
        }

        return $out;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_NO_ORDERS => 'Brak zamówienia',
            self::STATUS_NO_INVOICE => 'Brak faktury',
            self::STATUS_PARTIAL => 'Faktura częściowa',
            self::STATUS_COMPLETE => 'FV OK',
            default => '',
        };
    }

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            self::STATUS_NO_ORDERS => 'bg-warning text-dark',
            self::STATUS_NO_INVOICE => 'bg-danger',
            self::STATUS_PARTIAL => 'bg-warning text-dark',
            self::STATUS_COMPLETE => 'bg-success',
            default => 'bg-secondary',
        };
    }

    /**
     * Aktywne warianty cenowe do linków formularza (kolejność: id rosnąco).
     *
     * @return Collection<int, \App\Models\CoursePriceVariant>
     */
    public static function activePriceVariantsForLinks(Course $course): Collection
    {
        if ($course->relationLoaded('priceVariants')) {
            return $course->priceVariants
                ->where('is_active', true)
                ->sortBy('id')
                ->values();
        }

        return $course->priceVariants()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }
}
