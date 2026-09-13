<?php

namespace App\Http\Controllers;

use App\Models\OnlineCourse;
use App\Services\OnlineCourseSalesCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OnlineCourseSalesController extends Controller
{
    public function edit(OnlineCourse $online_course): View
    {
        $product = $online_course->salesProduct()->first();
        $product?->load('defaultOffer.prices');
        $offer = $product?->defaultOffer;

        return view('online-courses.sales.edit', [
            'course' => $online_course,
            'product' => $product,
            'offer' => $offer,
            'prices' => $offer?->prices ?? collect(),
        ]);
    }

    public function update(
        Request $request,
        OnlineCourse $online_course,
        OnlineCourseSalesCatalogService $catalog
    ): RedirectResponse {
        $request->merge([
            'product_slug' => Str::slug((string) $request->input('product_slug')),
        ]);

        $productId = $online_course->salesProduct()->value('id');
        $validated = Validator::make($request->all(), [
            'product_slug' => [
                'required',
                'string',
                'max:191',
                Rule::unique('products', 'slug')->ignore($productId),
            ],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')->ignore($productId),
            ],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'sales_enabled' => ['required', 'boolean'],
            'is_public' => ['required', 'boolean'],
            'allow_deferred_invoice' => ['required', 'boolean'],
            'allow_payu' => ['required', 'boolean'],
            'allow_paynow' => ['required', 'boolean'],
            'satisfaction_guarantee_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ])->validate();

        if (
            (bool) $validated['sales_enabled']
            && ! (bool) $validated['allow_deferred_invoice']
            && ! (bool) $validated['allow_payu']
            && ! (bool) $validated['allow_paynow']
        ) {
            throw ValidationException::withMessages([
                'allow_deferred_invoice' => 'Aktywna sprzedaż wymaga co najmniej jednego sposobu płatności.',
            ]);
        }

        $catalog->saveSettings($online_course, $validated);

        return redirect()
            ->route('online-courses.sales.edit', $online_course)
            ->with('success', 'Ustawienia sprzedaży kursu online zostały zapisane.');
    }
}
