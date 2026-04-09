<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CoursePriceVariant;
use App\Models\FormOrder;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoursePriceVariantController extends Controller
{
    /**
     * Wyświetl formularz tworzenia nowego wariantu cenowego
     */
    public function create($courseId)
    {
        $course = Course::findOrFail($courseId);

        return view('course-price-variants.create', compact('course'));
    }

    /**
     * Zapisuje nowy wariant cenowy
     */
    public function store(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'price' => 'required|numeric|min:0',
            'is_promotion' => 'boolean',
            'promotion_price' => 'nullable|numeric|min:0|required_if:is_promotion,1',
            'promotion_type' => 'required|in:disabled,unlimited,time_limited',
            'promotion_start' => 'nullable|date|required_if:promotion_type,time_limited',
            'promotion_end' => 'nullable|date|after:promotion_start|required_if:promotion_type,time_limited',
            'access_type' => 'required|in:1,2,3,4,5',
            'access_start_datetime' => 'nullable|date|required_if:access_type,2,4,5',
            'access_end_datetime' => 'nullable|date|after:access_start_datetime|required_if:access_type,2,4',
            'access_duration_value' => 'nullable|integer|min:1|required_if:access_type,3,5',
            'access_duration_unit' => 'nullable|in:hours,days,months,years|required_if:access_type,3,5',
        ]);

        try {
            DB::beginTransaction();

            $variant = new CoursePriceVariant($validated);
            $variant->course_id = $course->id;
            $variant->save();

            DB::commit();

            return redirect()->route('courses.show', $course->id)
                ->with('success', 'Wariant cenowy został pomyślnie utworzony.');

        } catch (Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Wystąpił błąd podczas tworzenia wariantu cenowego: '.$e->getMessage());
        }
    }

    /**
     * Wyświetl formularz edycji wariantu cenowego
     */
    public function edit($courseId, $id)
    {
        $course = Course::findOrFail($courseId);
        $variant = CoursePriceVariant::where('course_id', $courseId)->findOrFail($id);

        return view('course-price-variants.edit', compact('course', 'variant'));
    }

    /**
     * Aktualizuje wariant cenowy
     */
    public function update(Request $request, $courseId, $id)
    {
        $course = Course::findOrFail($courseId);
        $variant = CoursePriceVariant::where('course_id', $courseId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'price' => 'required|numeric|min:0',
            'is_promotion' => 'boolean',
            'promotion_price' => 'nullable|numeric|min:0|required_if:is_promotion,1',
            'promotion_type' => 'required|in:disabled,unlimited,time_limited',
            'promotion_start' => 'nullable|date|required_if:promotion_type,time_limited',
            'promotion_end' => 'nullable|date|after:promotion_start|required_if:promotion_type,time_limited',
            'access_type' => 'required|in:1,2,3,4,5',
            'access_start_datetime' => 'nullable|date|required_if:access_type,2,4,5',
            'access_end_datetime' => 'nullable|date|after:access_start_datetime|required_if:access_type,2,4',
            'access_duration_value' => 'nullable|integer|min:1|required_if:access_type,3,5',
            'access_duration_unit' => 'nullable|in:hours,days,months,years|required_if:access_type,3,5',
        ]);

        try {
            DB::beginTransaction();

            $variant->fill($validated);
            $variant->save();

            DB::commit();

            return redirect()->route('courses.show', $course->id)
                ->with('success', 'Wariant cenowy został pomyślnie zaktualizowany.');

        } catch (Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Wystąpił błąd podczas aktualizacji wariantu cenowego: '.$e->getMessage());
        }
    }

    /**
     * Usuwa wariant cenowy (soft delete)
     */
    public function destroy($courseId, $id)
    {
        $course = Course::findOrFail($courseId);
        $variant = CoursePriceVariant::where('course_id', $courseId)->findOrFail($id);

        try {
            // Sprawdź czy kurs istnieje i nie jest usunięty (soft delete)
            if ($course->trashed() || ! $course->exists) {
                return response()->json([
                    'success' => false,
                    'error' => 'Nie można usunąć wariantu cenowego - kurs nie istnieje lub został usunięty.',
                ], 400);
            }

            if (FormOrder::withTrashed()->where('course_price_variant_id', $variant->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Nie można usunąć tego wariantu — występuje w co najmniej jednym zamówieniu (form_orders). Użyj dezaktywacji: wariant pozostanie w bazie dla historii zamówień, ale nie będzie dostępny do wyboru na stronie.',
                    'can_deactivate_only' => true,
                ], 422);
            }

            $variant->delete();

            return response()->json([
                'success' => true,
                'message' => 'Wariant cenowy został usunięty.',
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas usuwania wariantu cenowego: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Przywraca wariant cenowy z kosza (restore)
     */
    public function restore($courseId, $id)
    {
        $course = Course::findOrFail($courseId);

        try {
            // Sprawdź czy kurs istnieje i nie jest usunięty (soft delete)
            if ($course->trashed() || ! $course->exists) {
                return response()->json([
                    'success' => false,
                    'error' => 'Nie można przywrócić wariantu cenowego - kurs nie istnieje lub został usunięty.',
                ], 400);
            }

            $variant = CoursePriceVariant::withTrashed()
                ->where('course_id', $courseId)
                ->findOrFail($id);

            $variant->restore();

            return response()->json([
                'success' => true,
                'message' => 'Wariant cenowy został przywrócony.',
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas przywracania wariantu cenowego: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dezaktywuje wariant (niewidoczny w wyborze na stronie publicznej), gdy nie można go usunąć z powodu zamówień.
     */
    public function deactivate($courseId, $id)
    {
        $course = Course::findOrFail($courseId);
        $variant = CoursePriceVariant::where('course_id', $courseId)->findOrFail($id);

        if ($course->trashed() || ! $course->exists) {
            return response()->json([
                'success' => false,
                'error' => 'Nie można zmienić wariantu — kurs nie istnieje lub został usunięty.',
            ], 400);
        }

        try {
            $variant->is_active = false;
            $variant->save();

            return response()->json([
                'success' => true,
                'message' => 'Wariant został dezaktywowany i nie jest dostępny do wyboru przy nowych zamówieniach.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas dezaktywacji wariantu: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ponowna aktywacja wariantu cenowego.
     */
    public function activate($courseId, $id)
    {
        $course = Course::findOrFail($courseId);
        $variant = CoursePriceVariant::where('course_id', $courseId)->findOrFail($id);

        if ($course->trashed() || ! $course->exists) {
            return response()->json([
                'success' => false,
                'error' => 'Nie można zmienić wariantu — kurs nie istnieje lub został usunięty.',
            ], 400);
        }

        try {
            $variant->is_active = true;
            $variant->save();

            return response()->json([
                'success' => true,
                'message' => 'Wariant został ponownie aktywowany i jest dostępny do wyboru.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Wystąpił błąd podczas aktywacji wariantu: '.$e->getMessage(),
            ], 500);
        }
    }
}
