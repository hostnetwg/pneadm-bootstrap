<?php

namespace App\Http\Controllers;

use App\Models\OnlineCourse;
use App\Models\OnlineCourseEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnlineCourseEnrollmentController extends Controller
{
    public function index(OnlineCourse $online_course): View
    {
        $enrollments = $online_course->enrollments()->orderBy('email')->paginate(30);

        return view('online-courses.enrollments.index', compact('online_course', 'enrollments'));
    }

    public function create(OnlineCourse $online_course): View
    {
        return view('online-courses.enrollments.create', compact('online_course'));
    }

    public function store(Request $request, OnlineCourse $online_course): RedirectResponse
    {
        $data = $this->validated($request);

        OnlineCourseEnrollment::query()->updateOrCreate(
            [
                'online_course_id' => $online_course->id,
                'email' => $data['email'],
            ],
            [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'access_expires_at' => $data['access_expires_at'],
                'access_source' => $data['access_source'],
                'notes' => $data['notes'],
            ]
        );

        return redirect()->route('online-courses.enrollments.index', $online_course)->with('success', 'Przypisanie dostępu zapisane (dodano lub zaktualizowano).');
    }

    public function edit(OnlineCourse $online_course, OnlineCourseEnrollment $enrollment): View
    {
        abort_unless($enrollment->online_course_id === $online_course->id, 404);

        return view('online-courses.enrollments.edit', compact('online_course', 'enrollment'));
    }

    public function update(Request $request, OnlineCourse $online_course, OnlineCourseEnrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->online_course_id === $online_course->id, 404);

        $data = $this->validated($request);

        if ($data['email'] !== $enrollment->email
            && OnlineCourseEnrollment::query()
                ->where('online_course_id', $online_course->id)
                ->where('email', $data['email'])
                ->exists()
        ) {
            return redirect()->back()->withInput()->withErrors(['email' => 'Ten adres jest już przypisany do tego kursu.']);
        }

        $enrollment->update($data);

        return redirect()->route('online-courses.enrollments.index', $online_course)->with('success', 'Przypisanie zaktualizowane.');
    }

    public function destroy(OnlineCourse $online_course, OnlineCourseEnrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->online_course_id === $online_course->id, 404);
        $enrollment->delete();

        return redirect()->route('online-courses.enrollments.index', $online_course)->with('success', 'Dostęp został usunięty.');
    }

    /**
     * @return array{email:string,first_name:?string,last_name:?string,access_expires_at:?\Carbon\Carbon,access_source:string,notes:?string}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc,dns'],
            'first_name' => ['nullable', 'string', 'max:190'],
            'last_name' => ['nullable', 'string', 'max:190'],
            'access_expires_at' => ['nullable', 'date'],
            'access_source' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['email'] = OnlineCourseEnrollment::normalizeEmail($validated['email']) ?? '';
        $validated['access_source'] = trim((string) ($validated['access_source'] ?? '')) ?: 'manual';
        $validated['access_expires_at'] = $validated['access_expires_at'] ?? null;

        return [
            'email' => $validated['email'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'access_expires_at' => $validated['access_expires_at'],
            'access_source' => $validated['access_source'],
            'notes' => $validated['notes'],
        ];
    }
}
