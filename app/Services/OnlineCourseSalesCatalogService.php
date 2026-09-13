<?php

namespace App\Services;

use App\Models\OnlineCourse;
use App\Models\Product;
use App\Models\ProductOffer;
use Illuminate\Support\Facades\DB;

class OnlineCourseSalesCatalogService
{
    public function saveSettings(OnlineCourse $course, array $data): ProductOffer
    {
        return DB::transaction(function () use ($course, $data) {
            $product = Product::query()->firstOrNew([
                'type' => Product::TYPE_ONLINE_COURSE,
                'resource_id' => $course->id,
            ]);

            $salesEnabled = (bool) $data['sales_enabled'];
            $isPublic = (bool) $data['is_public'];

            $product->fill([
                'name' => $course->title,
                'slug' => $data['product_slug'],
                'sku' => $this->nullableTrim($data['sku'] ?? null),
                'fulfillment_type' => Product::FULFILLMENT_ONLINE_COURSE_ACCESS,
                'is_active' => $salesEnabled || $isPublic,
                'requires_shipping' => false,
                'meta_title' => $this->nullableTrim($data['meta_title'] ?? null),
                'meta_description' => $this->nullableTrim($data['meta_description'] ?? null),
            ]);
            $product->save();

            $offer = ProductOffer::query()->firstOrNew([
                'product_id' => $product->id,
                'code' => ProductOffer::DEFAULT_CODE,
                'sales_channel' => ProductOffer::CHANNEL_PNEDU,
            ]);
            $offer->fill([
                'is_active' => $salesEnabled,
                'is_public' => $isPublic,
                'allow_multiple_recipients' => true,
                'allow_deferred_invoice' => (bool) $data['allow_deferred_invoice'],
                'allow_payu' => (bool) $data['allow_payu'],
                'allow_paynow' => (bool) $data['allow_paynow'],
                'satisfaction_guarantee_days' => array_key_exists('satisfaction_guarantee_days', $data)
                    ? max(0, (int) $data['satisfaction_guarantee_days'])
                    : ($offer->satisfaction_guarantee_days ?? 30),
            ]);
            $offer->save();

            return $offer->load(['product', 'prices']);
        });
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
