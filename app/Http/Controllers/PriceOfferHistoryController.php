<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CoursePriceVariant;
use App\Models\OnlineCourse;
use App\Models\PriceOfferHistory;
use App\Models\ProductPrice;
use App\Services\PriceOmnibusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PriceOfferHistoryController extends Controller
{
    public function excludeProductPrice(
        Request $request,
        OnlineCourse $online_course,
        ProductPrice $product_price,
        PriceOfferHistory $history,
        PriceOmnibusService $omnibus
    ): RedirectResponse {
        $this->assertProductHistory($online_course, $product_price, $history);
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $omnibus->exclude($history, (int) $request->user()->id, (string) $request->input('reason'));

        return back()->with('success', 'Wpis historii cen został wyłączony z wyliczenia Omnibus.');
    }

    public function restoreProductPrice(
        OnlineCourse $online_course,
        ProductPrice $product_price,
        PriceOfferHistory $history,
        PriceOmnibusService $omnibus
    ): RedirectResponse {
        $this->assertProductHistory($online_course, $product_price, $history);
        $omnibus->restore($history);

        return back()->with('success', 'Wpis historii cen znów liczy się do Omnibus.');
    }

    public function excludeCourseVariant(
        Request $request,
        int $courseId,
        int $id,
        PriceOfferHistory $history,
        PriceOmnibusService $omnibus
    ): RedirectResponse {
        $this->assertCourseHistory($courseId, $id, $history);
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $omnibus->exclude($history, (int) $request->user()->id, (string) $request->input('reason'));

        return back()->with('success', 'Wpis historii cen został wyłączony z wyliczenia Omnibus.');
    }

    public function restoreCourseVariant(
        int $courseId,
        int $id,
        PriceOfferHistory $history,
        PriceOmnibusService $omnibus
    ): RedirectResponse {
        $this->assertCourseHistory($courseId, $id, $history);
        $omnibus->restore($history);

        return back()->with('success', 'Wpis historii cen znów liczy się do Omnibus.');
    }

    private function assertProductHistory(OnlineCourse $course, ProductPrice $price, PriceOfferHistory $history): void
    {
        $offer = $course->salesProduct?->defaultOffer;
        abort_unless($offer && (int) $price->product_offer_id === (int) $offer->id, 404);
        abort_unless(
            $history->subject_type === PriceOfferHistory::SUBJECT_PRODUCT_PRICE
            && (int) $history->subject_id === (int) $price->id,
            404
        );
    }

    private function assertCourseHistory(int $courseId, int $variantId, PriceOfferHistory $history): void
    {
        Course::query()->findOrFail($courseId);
        $variant = CoursePriceVariant::query()
            ->where('course_id', $courseId)
            ->whereKey($variantId)
            ->firstOrFail();
        abort_unless(
            $history->subject_type === PriceOfferHistory::SUBJECT_COURSE_VARIANT
            && (int) $history->subject_id === (int) $variant->id,
            404
        );
    }
}
