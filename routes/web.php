<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/courses', [App\Http\Controllers\CoursesController::class, 'index'])->name('courses.index');
Route::get('/courses/create', [App\Http\Controllers\CoursesController::class, 'create'])->name('courses.create');
Route::post('/courses', [App\Http\Controllers\CoursesController::class, 'store'])->name('courses.store');
Route::delete('/courses/{id}', [App\Http\Controllers\CoursesController::class, 'destroy'])->name('courses.destroy');
Route::get('/courses/{id}/edit', [App\Http\Controllers\CoursesController::class, 'edit'])->name('courses.edit');
Route::put('/courses/{id}', [App\Http\Controllers\CoursesController::class, 'update'])->name('courses.update');

Route::prefix('courses/{course}/participants')->group(function () {
    Route::get('/', [App\Http\Controllers\ParticipantController::class, 'index'])->name('participants.index'); // Lista uczestników
    Route::get('/create', [App\Http\Controllers\ParticipantController::class, 'create'])->name('participants.create'); // Formularz dodawania
    Route::post('/', [App\Http\Controllers\ParticipantController::class, 'store'])->name('participants.store'); // Dodawanie uczestnika
    Route::get('/{participant}/edit', [App\Http\Controllers\ParticipantController::class, 'edit'])->name('participants.edit'); // Edycja uczestnika
    Route::put('/{participant}', [App\Http\Controllers\ParticipantController::class, 'update'])->name('participants.update'); // Aktualizacja
    Route::delete('/{participant}', [App\Http\Controllers\ParticipantController::class, 'destroy'])->name('participants.destroy'); // Usuwanie
});


Route::get('/instructors', [App\Http\Controllers\InstructorsController::class, 'index'])->name('courses.instructors.index');
Route::post('/instructors', [App\Http\Controllers\InstructorsController::class, 'store'])->name('courses.instructors.store');
Route::get('/instructors/create', [App\Http\Controllers\InstructorsController::class, 'create'])->name('courses.instructors.create');
Route::get('/instructors/{id}/edit', [App\Http\Controllers\InstructorsController::class, 'edit'])->name('courses.instructors.edit');
Route::post('/instructors/{id}/update', [App\Http\Controllers\InstructorsController::class, 'update'])->name('courses.instructors.update');
Route::post('/instructors/{id}/delete', [App\Http\Controllers\InstructorsController::class, 'destroy'])->name('courses.instructors.destroy');





Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
