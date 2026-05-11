<?php

namespace App\Http\Controllers;

use App\Models\OnlineCourse;
use App\Models\OnlineCourseModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OnlineCourseModuleController extends Controller
{
    public function store(Request $request, OnlineCourse $online_course): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $max = (int) $online_course->modules()->max('sort_order');

        OnlineCourseModule::query()->create([
            'online_course_id' => $online_course->id,
            'title' => $validated['title'],
            'sort_order' => $max + 1,
        ]);

        return redirect()->route('online-courses.edit', $online_course)->with('success', 'Moduł został dodany.');
    }

    public function update(Request $request, OnlineCourse $online_course, OnlineCourseModule $module): RedirectResponse
    {
        abort_unless($module->online_course_id === $online_course->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $module->update($validated);

        return redirect()->route('online-courses.edit', $online_course)->with('success', 'Moduł zapisany.');
    }

    public function destroy(OnlineCourse $online_course, OnlineCourseModule $module): RedirectResponse
    {
        abort_unless($module->online_course_id === $online_course->id, 404);
        $module->delete();

        return redirect()->route('online-courses.edit', $online_course)->with('success', 'Moduł usunięty (wraz z lekcjami).');
    }
}
