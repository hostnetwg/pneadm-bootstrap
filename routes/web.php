<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CoursesController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\InstructorsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/courses', [CoursesController::class, 'index'])->name('courses.index');
    Route::get('/courses/create', [CoursesController::class, 'create'])->name('courses.create');
    Route::post('/courses', [CoursesController::class, 'store'])->name('courses.store');
    Route::delete('/courses/{id}', [CoursesController::class, 'destroy'])->name('courses.destroy');
    Route::get('/courses/{id}/edit', [CoursesController::class, 'edit'])->name('courses.edit');
    Route::put('/courses/{id}', [CoursesController::class, 'update'])->name('courses.update');

    Route::prefix('courses/{course}/participants')->group(function () {
        Route::get('/', [ParticipantController::class, 'index'])->name('participants.index'); // Lista uczestników
        Route::get('/create', [ParticipantController::class, 'create'])->name('participants.create'); // Formularz dodawania
        Route::post('/', [ParticipantController::class, 'store'])->name('participants.store'); // Dodawanie uczestnika
        Route::get('/{participant}/edit', [ParticipantController::class, 'edit'])->name('participants.edit'); // Edycja uczestnika
        Route::put('/{participant}', [ParticipantController::class, 'update'])->name('participants.update'); // Aktualizacja
        Route::delete('/{participant}', [ParticipantController::class, 'destroy'])->name('participants.destroy'); // Usuwanie
    });

    Route::get('/certificates/generate/{participant}', [CertificateController::class, 'generate'])
        ->name('certificates.generate');


    Route::get('participants/{participant}/certificate', [CertificateController::class, 'store'])->name('certificates.store');

    Route::get('/instructors', [InstructorsController::class, 'index'])->name('courses.instructors.index');
    Route::post('/instructors', [InstructorsController::class, 'store'])->name('courses.instructors.store');
    Route::get('/instructors/create', [InstructorsController::class, 'create'])->name('courses.instructors.create');
    Route::get('/instructors/{id}/edit', [InstructorsController::class, 'edit'])->name('courses.instructors.edit');
    // Route::post('/instructors/{id}/update', [InstructorsController::class, 'update'])->name('courses.instructors.update');
    Route::put('/instructors/{id}', [InstructorsController::class, 'update'])->name('courses.instructors.update');
    Route::delete('/instructors/{id}', [InstructorsController::class, 'destroy'])->name('courses.instructors.destroy');



    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
