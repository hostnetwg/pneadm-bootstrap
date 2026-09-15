<?php

namespace App\Models\Analytics;

use App\Models\FormOrder;
use App\Models\Product;

class AnalyticsEvent extends AnalyticsModel
{
    public const UPDATED_AT = null;

    protected $table = 'analytics_events';

    protected $casts = [
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Tytuł do UI: snapshot szkolenia albo nazwa kursu online / produktu z zamówienia lub ścieżki /kursy/{slug}.
     */
    public function displayProductTitle(): string
    {
        if (filled($this->course_title_snapshot)) {
            return (string) $this->course_title_snapshot;
        }

        $fromOrder = $this->productTitleFromFormOrder();
        if ($fromOrder !== null) {
            return $fromOrder;
        }

        return $this->productTitleFromPath() ?? '';
    }

    public static function productSlugFromPath(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        $pathOnly = parse_url($path, PHP_URL_PATH);
        $path = is_string($pathOnly) && $pathOnly !== '' ? $pathOnly : $path;
        if (! preg_match('#^/kursy/([a-z0-9\-]+)(?:/|$)#i', $path, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function productTitleFromFormOrder(): ?string
    {
        $orderId = $this->form_order_id !== null ? (int) $this->form_order_id : 0;
        if ($orderId <= 0) {
            return null;
        }

        static $names = [];
        if (! array_key_exists($orderId, $names)) {
            $names[$orderId] = FormOrder::query()->whereKey($orderId)->value('product_name');
        }

        $name = FormOrder::plainProductName(is_string($names[$orderId]) ? $names[$orderId] : null, '');

        return $name !== '' ? $name : null;
    }

    private function productTitleFromPath(): ?string
    {
        $slug = self::productSlugFromPath($this->path);
        if ($slug === null) {
            return null;
        }

        static $names = [];
        if (! array_key_exists($slug, $names)) {
            $names[$slug] = Product::query()->where('slug', $slug)->value('name');
        }

        $name = is_string($names[$slug]) ? trim($names[$slug]) : '';

        return $name !== '' ? $name : null;
    }
}
