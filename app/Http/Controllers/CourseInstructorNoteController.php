<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseInstructorNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseInstructorNoteController extends Controller
{
    public function update(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $note = trim((string) ($validated['note'] ?? ''));

        if ($note === '') {
            $course->instructorNote()->delete();

            return response()->json([
                'success' => true,
                'note' => null,
            ]);
        }

        $record = CourseInstructorNote::query()->updateOrCreate(
            ['course_id' => $course->id],
            ['body' => $note],
        );

        return response()->json([
            'success' => true,
            'note' => $record->body,
        ]);
    }
}
