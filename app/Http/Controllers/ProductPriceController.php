<?php

namespace App\Http\Controllers;

use App\Models\OnlineCourse;
use App\Models\ProductOffer;
use App\Models\ProductPrice;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductPriceController extends Controller
{
    public function store(Request $request, OnlineCourse $online_course): RedirectResponse
    {
        $offer = $this->offerForCourse($online_course);
        $data = $this->validatedPriceData($request);
        $data['product_offer_id'] = $offer->id;

        ProductPrice::query()->create($data);

        return redirect()
            ->route('online-courses.sales.edit', $online_course)
            ->with('success', 'Wariant cenowy został dodany.');
    }

    public function update(
        Request $request,
        OnlineCourse $online_course,
        ProductPrice $product_price
    ): RedirectResponse {
        $this->assertPriceBelongsToCourse($online_course, $product_price);
        $product_price->update($this->validatedPriceData($request));

        return redirect()
            ->route('online-courses.sales.edit', $online_course)
            ->with('success', 'Wariant cenowy został zaktualizowany.');
    }

    public function destroy(
        OnlineCourse $online_course,
        ProductPrice $product_price
    ): RedirectResponse {
        $this->assertPriceBelongsToCourse($online_course, $product_price);
        $product_price->delete();

        return redirect()
            ->route('online-courses.sales.edit', $online_course)
            ->with('success', 'Wariant cenowy został przeniesiony do kosza.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPriceData(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'is_complimentary' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'tax_treatment' => ['required', 'string', 'in:exempt,standard'],
            'tax_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_if:tax_treatment,standard'],
            'tax_exemption_basis' => ['nullable', 'string', 'max:500'],
            'is_promotion' => ['required', 'boolean'],
            'promotion_price' => ['nullable', 'numeric', 'min:0', 'lt:price', 'required_if:is_promotion,1'],
            'promotion_starts_at' => ['nullable', 'date', 'required_with:promotion_ends_at'],
            'promotion_ends_at' => ['nullable', 'date', 'after:promotion_starts_at'],
            'show_promotion_countdown' => ['nullable', 'boolean'],
            'access_policy' => ['required', 'string', 'in:unlimited,duration_from_grant,fixed_until'],
            'access_starts_at' => ['nullable', 'date'],
            'access_note' => ['nullable', 'string', 'max:2000'],
            'access_duration_value' => ['nullable', 'integer', 'min:1', 'max:999', 'required_if:access_policy,duration_from_grant'],
            'access_duration_unit' => ['nullable', 'string', 'in:days,months,years', 'required_if:access_policy,duration_from_grant'],
            'access_expires_at' => ['nullable', 'date', 'required_if:access_policy,fixed_until'],
        ]);

        $validated['name'] = trim($validated['name']);
        $validated['description'] = $this->nullableTrim($validated['description'] ?? null);
        $validated['currency'] = 'PLN';
        $validated['tax_rate'] = $validated['tax_treatment'] === ProductPrice::TAX_STANDARD
            ? round(((float) $validated['tax_rate_percent']) / 100, 4)
            : null;
        $validated['tax_exemption_basis'] = $validated['tax_treatment'] === ProductPrice::TAX_EXEMPT
            ? $this->nullableTrim($validated['tax_exemption_basis'] ?? null)
            : null;
        unset($validated['tax_rate_percent']);
        $validated['is_complimentary'] = (bool) ($validated['is_complimentary'] ?? false);

        if ($validated['is_complimentary']) {
            $validated['price'] = '0.00';
            $validated['is_promotion'] = false;
            $validated['promotion_price'] = null;
            $validated['promotion_starts_at'] = null;
            $validated['promotion_ends_at'] = null;
            $validated['show_promotion_countdown'] = false;
        } elseif (! (bool) $validated['is_promotion']) {
            $validated['promotion_price'] = null;
            $validated['promotion_starts_at'] = null;
            $validated['promotion_ends_at'] = null;
            $validated['show_promotion_countdown'] = false;
        } else {
            $validated['promotion_starts_at'] = $this->warsawDateToUtc($validated['promotion_starts_at'] ?? null);
            $validated['promotion_ends_at'] = $this->warsawDateToUtc($validated['promotion_ends_at'] ?? null);
            $validated['show_promotion_countdown'] = (bool) ($validated['show_promotion_countdown'] ?? false)
                && $validated['promotion_ends_at'] !== null;
        }

        if ($validated['access_policy'] !== ProductPrice::ACCESS_DURATION_FROM_GRANT) {
            $validated['access_duration_value'] = null;
            $validated['access_duration_unit'] = null;
        }

        $validated['access_starts_at'] = $this->warsawDateToUtc($validated['access_starts_at'] ?? null);
        $validated['access_note'] = $this->nullableTrim($validated['access_note'] ?? null);

        if ($validated['access_policy'] !== ProductPrice::ACCESS_FIXED_UNTIL) {
            $validated['access_expires_at'] = null;
        } else {
            $validated['access_expires_at'] = $this->warsawDateToUtc($validated['access_expires_at']);
        }

        return $validated;
    }

    private function offerForCourse(OnlineCourse $course): ProductOffer
    {
        $product = $course->salesProduct()->first();
        $offer = $product?->defaultOffer()->first();

        abort_unless($offer, 422, 'Najpierw zapisz ustawienia sprzedaży kursu.');

        return $offer;
    }

    private function assertPriceBelongsToCourse(OnlineCourse $course, ProductPrice $price): void
    {
        $offer = $this->offerForCourse($course);

        abort_unless((int) $price->product_offer_id === (int) $offer->id, 404);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function warsawDateToUtc(mixed $value): ?CarbonImmutable
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value, 'Europe/Warsaw')->utc();
    }
}
