<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\CourseVideo;
use Illuminate\Support\Facades\Validator;

class CourseVideoController extends Controller
{
    /**
     * Wyświetl formularz do dodawania/edycji nagrania
     */
    public function create(Course $course)
    {
        return view('course-videos.create', compact('course'));
    }

    /**
     * Zapisz nowe nagranie
     */
    public function store(Request $request, Course $course)
    {
        $validator = Validator::make($request->all(), [
            'video_url' => 'required|url',
            'platform' => 'required|in:youtube,vimeo',
            'title' => 'nullable|string|max:255',
            'order' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Wystąpiły błędy walidacji',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $video = CourseVideo::create([
                'course_id' => $course->id,
                'video_url' => $request->video_url,
                'platform' => $request->platform,
                'title' => $request->title,
                'order' => $request->order ?? 0
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Nagranie zostało dodane pomyślnie.',
                'video' => $video
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Nie udało się dodać nagrania: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Wyświetl formularz edycji nagrania
     */
    public function edit(Course $course, CourseVideo $video)
    {
        if ($video->course_id !== $course->id) {
            abort(404);
        }

        return view('course-videos.edit', compact('course', 'video'));
    }

    /**
     * Zaktualizuj nagranie
     */
    public function update(Request $request, Course $course, CourseVideo $video)
    {
        if ($video->course_id !== $course->id) {
            abort(404);
        }

        $validator = Validator::make($request->all(), [
            'video_url' => 'required|url',
            'platform' => 'required|in:youtube,vimeo',
            'title' => 'nullable|string|max:255',
            'order' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Wystąpiły błędy walidacji',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $video->update([
                'video_url' => $request->video_url,
                'platform' => $request->platform,
                'title' => $request->title,
                'order' => $request->order ?? 0
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Nagranie zostało zaktualizowane pomyślnie.',
                'video' => $video
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Nie udało się zaktualizować nagrania: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Usuń nagranie
     */
    public function destroy(Course $course, CourseVideo $video)
    {
        if ($video->course_id !== $course->id) {
            abort(404);
        }

        try {
            $video->delete();

            return response()->json([
                'success' => true,
                'message' => 'Nagranie zostało usunięte pomyślnie.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Nie udało się usunąć nagrania: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Pobierz listę nagrań dla kursu (AJAX)
     */
    public function index(Course $course)
    {
        $videos = $course->videos()->orderBy('order')->get()->map(function($video) {
            return [
                'id' => $video->id,
                'course_id' => $video->course_id,
                'video_url' => $video->video_url,
                'platform' => $video->platform,
                'title' => $video->title,
                'order' => $video->order,
                'embed_url' => $video->getEmbedUrl()
            ];
        });

        return response()->json([
            'success' => true,
            'videos' => $videos
        ]);
    }
}

