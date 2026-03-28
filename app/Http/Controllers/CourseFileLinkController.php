<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseFileLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CourseFileLinkController extends Controller
{
    public function index(Course $course)
    {
        $links = $course->fileLinks()->orderBy('order')->get()->map(function ($link) {
            return [
                'id' => $link->id,
                'course_id' => $link->course_id,
                'url' => $link->url,
                'title' => $link->title,
                'order' => $link->order,
            ];
        });

        return response()->json([
            'success' => true,
            'file_links' => $links,
        ]);
    }

    public function store(Request $request, Course $course)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:2048',
            'title' => 'nullable|string|max:255',
            'order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Wystąpiły błędy walidacji',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $link = CourseFileLink::create([
                'course_id' => $course->id,
                'url' => $request->url,
                'title' => $request->title,
                'order' => $request->order ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Link został dodany pomyślnie.',
                'file_link' => $link,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Nie udało się dodać linku: '.$e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Course $course, CourseFileLink $fileLink)
    {
        if ($fileLink->course_id !== $course->id) {
            abort(404);
        }

        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:2048',
            'title' => 'nullable|string|max:255',
            'order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Wystąpiły błędy walidacji',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $fileLink->update([
                'url' => $request->url,
                'title' => $request->title,
                'order' => $request->order ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Link został zaktualizowany pomyślnie.',
                'file_link' => $fileLink,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Nie udało się zaktualizować linku: '.$e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Course $course, CourseFileLink $fileLink)
    {
        if ($fileLink->course_id !== $course->id) {
            abort(404);
        }

        try {
            $fileLink->delete();

            return response()->json([
                'success' => true,
                'message' => 'Link został usunięty pomyślnie.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Nie udało się usunąć linku: '.$e->getMessage(),
            ], 500);
        }
    }
}
