<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CoursesController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\InstructorsController;
use App\Http\Controllers\EducationController;
use App\Http\Controllers\NODNSzkoleniaController;
use App\Http\Controllers\PubligoController;

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');


/**/
    // Dostęp do drugiej bazy danych CERTGEN
    Route::get('/nodn-szkolenia', [NODNSzkoleniaController::class, 'index'])->name('archiwum.certgen_szkolenia.index');
    //Route::get('/nodn-szkolenia/export', [NODNSzkoleniaController::class, 'exportToCourses'])->name('nodn.szkolenia.export');
    Route::post('/nodn-szkolenia/export-selected', [NODNSzkoleniaController::class, 'exportSelectedCourses'])->name('nodn.szkolenia.export.selected');    

    Route::get('/nodn/szkolenia/{id}/export-participants', [NODNSzkoleniaController::class, 'exportParticipants'])->name('exportParticipants');
    Route::get('/nodn/szkolenia/export/{id}', [NODNSzkoleniaController::class, 'exportCourse'])->name('exportCourse');


    Route::get('/education', [EducationController::class, 'index'])->name('education.index');
    // trasa dla eksportu danych
    Route::get('/education/export', [EducationController::class, 'exportToCourses'])->name('education.export');
    Route::get('/education/export-participants/{id}', [EducationController::class, 'exportParticipants'])->name('education.exportParticipants');            

    /* Certgen:publigo */
    
    Route::get('/archiwum/certgen-publigo', [PubligoController::class, 'index'])->name('archiwum.certgen_publigo.index');    

    Route::get('/archiwum/certgen-publigo/create', [PubligoController::class, 'create'])->name('certgen_publigo.create');
    Route::post('/archiwum/certgen-publigo/store', [PubligoController::class, 'store'])->name('certgen_publigo.store');
    Route::delete('/archiwum/certgen-publigo/{id}', [PubligoController::class, 'destroy'])->name('certgen_publigo.destroy');
    Route::get('/archiwum/certgen-publigo/{id}/edit', [PubligoController::class, 'edit'])->name('certgen_publigo.edit');
    Route::put('/archiwum/certgen-publigo/{id}/update', [PubligoController::class, 'update'])->name('certgen_publigo.update');
    
    // Zarządzanie webhookami Publigo
    Route::get('/publigo/webhooks', [PubligoController::class, 'webhooks'])->name('publigo.webhooks');
    Route::get('/publigo/webhooks/logs', [PubligoController::class, 'webhookLogs'])->name('publigo.webhooks.logs');
    Route::post('/publigo/test-webhook', [PubligoController::class, 'testWebhook'])->name('publigo.test-webhook');
    
    Route::get('/import-publigo', [CoursesController::class, 'importFromPubligo'])->name('courses.importPubligo');      
    
    // ClickMeeting – lista zaplanowanych szkoleń
    Route::middleware(['auth', 'verified'])          // lub inny zestaw middleware
        ->get('/clickmeeting/trainings', [\App\Http\Controllers\ClickMeetingTrainingController::class, 'index'])
        ->name('clickmeeting.trainings.index');


/**/

    Route::get('/courses', [CoursesController::class, 'index'])->name('courses.index');
    Route::get('/courses/create', [CoursesController::class, 'create'])->name('courses.create');
    Route::post('/courses', [CoursesController::class, 'store'])->name('courses.store');
    Route::delete('/courses/{id}', [CoursesController::class, 'destroy'])->name('courses.destroy');
    Route::get('/courses/{id}/edit', [CoursesController::class, 'edit'])->name('courses.edit');
    Route::put('/courses/{id}', [CoursesController::class, 'update'])->name('courses.update');
    Route::post('/courses/import', [CoursesController::class, 'import'])->name('courses.import');   

    Route::prefix('courses/{course}/participants')->group(function () {
        Route::get('/', [ParticipantController::class, 'index'])->name('participants.index'); // Lista uczestników
        Route::get('/create', [ParticipantController::class, 'create'])->name('participants.create'); // Formularz dodawania
        Route::post('/', [ParticipantController::class, 'store'])->name('participants.store'); // Dodawanie uczestnika
        Route::post('/import', [ParticipantController::class, 'import'])->name('participants.import'); // Import CSV
        Route::get('/{participant}/edit', [ParticipantController::class, 'edit'])->name('participants.edit'); // Edycja uczestnika
        Route::put('/{participant}', [ParticipantController::class, 'update'])->name('participants.update'); // Aktualizacja
        Route::delete('/{participant}', [ParticipantController::class, 'destroy'])->name('participants.destroy'); // Usuwanie
    });

    Route::get('/certificates/generate/{participant}', [CertificateController::class, 'generate'])->name('certificates.generate');
    Route::delete('/certificates/{certificate}', [CertificateController::class, 'destroy'])->name('certificates.destroy');


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

// Webhook dla Publigo.pl - bez CSRF protection
Route::post('/api/publigo/webhook', [PubligoController::class, 'webhook'])
    ->middleware('publigo.webhook')
    ->name('publigo.webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

Route::post('/api/publigo/webhook-test', [PubligoController::class, 'webhookTest'])
    ->name('publigo.webhook.test')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);


