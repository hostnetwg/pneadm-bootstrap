<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Support\CourseCalendarEventBuilder;
use Illuminate\Http\Response;

class CourseCalendarIcsController extends Controller
{
    public function __invoke(Course $course): Response
    {
        if ($course->start_date === null) {
            abort(404);
        }

        $course->loadMissing(['instructor', 'location', 'closedTrainingCase']);

        $filename = 'szkolenie-'.$course->id.'.ics';

        return response(CourseCalendarEventBuilder::for($course)->toIcs(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8; method=PUBLISH',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
