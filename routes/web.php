<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CoursesController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificateTemplateController;
use App\Http\Controllers\InstructorsController;
use App\Http\Controllers\EducationController;
use App\Http\Controllers\NODNSzkoleniaController;
use App\Http\Controllers\PubligoController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\WebhookPubligoController;
use App\Http\Controllers\ZamowieniaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\SendyController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\SurveyImportController;

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'check.user.status'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Admin Panel
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UsersController::class);
        Route::patch('users/{user}/toggle-status', [UsersController::class, 'toggleStatus'])->name('users.toggle-status');
        
        // Zarządzanie szablonami certyfikatów
        Route::resource('certificate-templates', CertificateTemplateController::class);
        Route::get('certificate-templates/{certificateTemplate}/preview', [CertificateTemplateController::class, 'preview'])->name('certificate-templates.preview');
        Route::post('certificate-templates/{certificateTemplate}/clone', [CertificateTemplateController::class, 'clone'])->name('certificate-templates.clone');
    });


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
    Route::get('/publigo/test-api', [PubligoController::class, 'testApi'])->name('publigo.test-api');
    Route::get('/publigo/products', [PubligoController::class, 'productsIndex'])->name('publigo.products.index');
    
    Route::get('/import-publigo', [CoursesController::class, 'importFromPubligo'])->name('courses.importPubligo');      
    
    // Baza Certgen - dane dla webhook
    Route::prefix('certgen')->name('certgen.')->group(function () {
        Route::get('/webhook-data', [WebhookPubligoController::class, 'index'])->name('webhook_data.index');
        Route::get('/webhook-data/create', [WebhookPubligoController::class, 'create'])->name('webhook_data.create');
        Route::post('/webhook-data', [WebhookPubligoController::class, 'store'])->name('webhook_data.store');
        Route::get('/webhook-data/{id}', [WebhookPubligoController::class, 'show'])->name('webhook_data.show');
        Route::get('/webhook-data/{id}/edit', [WebhookPubligoController::class, 'edit'])->name('webhook_data.edit');
        Route::put('/webhook-data/{id}', [WebhookPubligoController::class, 'update'])->name('webhook_data.update');
        Route::delete('/webhook-data/{id}', [WebhookPubligoController::class, 'destroy'])->name('webhook_data.destroy');
        
        // Baza Certgen - zamówienia
        Route::get('/zamowienia', [ZamowieniaController::class, 'index'])->name('zamowienia.index');
        Route::get('/zamowienia/{id}', [ZamowieniaController::class, 'show'])->name('zamowienia.show');
        Route::delete('/zamowienia/{id}', [ZamowieniaController::class, 'destroy'])->name('zamowienia.destroy');
    });
    
    // ClickMeeting – lista zaplanowanych szkoleń
    Route::middleware(['auth', 'verified', 'check.user.status'])          // lub inny zestaw middleware
        ->get('/clickmeeting/trainings', [\App\Http\Controllers\ClickMeetingTrainingController::class, 'index'])
        ->name('clickmeeting.trainings.index');

    // Sendy - listy mailingowe
    Route::prefix('sendy')->name('sendy.')->group(function () {
        Route::get('/', [SendyController::class, 'index'])->name('index');
        Route::get('/{listId}', [SendyController::class, 'show'])->name('show');
        Route::get('/api/refresh', [SendyController::class, 'refresh'])->name('refresh');
        Route::get('/api/test-connection', [SendyController::class, 'testConnection'])->name('test-connection');
        Route::post('/api/subscribe', [SendyController::class, 'subscribe'])->name('subscribe');
        Route::post('/api/unsubscribe', [SendyController::class, 'unsubscribe'])->name('unsubscribe');
        Route::post('/api/delete-subscriber', [SendyController::class, 'deleteSubscriber'])->name('delete-subscriber');
        Route::post('/api/check-status', [SendyController::class, 'checkSubscriptionStatus'])->name('check-status');
    });

    // Sprzedaż - zamówienia
    Route::middleware(['auth', 'verified', 'check.user.status'])
    ->prefix('sales')
    ->name('sales.')
    ->group(function () {
        Route::get('/', [SalesController::class, 'index'])->name('index');
        Route::get('/{id}', [SalesController::class, 'show'])->name('show');
        Route::put('/{id}', [SalesController::class, 'update'])->name('update');
        Route::post('/{id}/process', [SalesController::class, 'markAsProcessed'])->name('process');
        Route::post('/{id}/publigo', [SalesController::class, 'createPubligoOrder'])->name('publigo.create');
        Route::post('/{id}/publigo/reset', [SalesController::class, 'resetPubligoStatus'])->name('publigo.reset');
    });

    // Tymczasowy route do czyszczenia OPcache (USUŃ PO UŻYCIU!)
    Route::middleware(['auth'])->get('/dev/clear-opcache', function () {
        if (function_exists('opcache_reset')) {
            opcache_reset();
            return response()->json([
                'success' => true,
                'message' => 'OPcache został wyczyszczony!',
                'time' => now()->toDateTimeString()
            ]);
        }
        return response()->json([
            'success' => false,
            'message' => 'OPcache nie jest dostępny'
        ]);
    });


/**/

    Route::get('/courses', [CoursesController::class, 'index'])->name('courses.index');
    Route::get('/courses/pdf', [CoursesController::class, 'generatePdf'])->name('courses.pdf');
    Route::get('/courses/statistics', [CoursesController::class, 'generateCourseStatistics'])->name('courses.statistics');
    // Ustawiamy create PRZED trasami z parametrem {id}, aby uniknąć kolizji z /courses/{id}
    Route::get('/courses/create', [CoursesController::class, 'create'])->name('courses.create');
    Route::post('/courses', [CoursesController::class, 'store'])->name('courses.store');
    // Trasy z parametrem {id} ograniczamy do wartości numerycznych
    Route::get('/courses/{id}', [CoursesController::class, 'show'])->whereNumber('id')->name('courses.show');
    Route::delete('/courses/{id}', [CoursesController::class, 'destroy'])->whereNumber('id')->name('courses.destroy');
    Route::get('/courses/{id}/edit', [CoursesController::class, 'edit'])->whereNumber('id')->name('courses.edit');
    Route::put('/courses/{id}', [CoursesController::class, 'update'])->whereNumber('id')->name('courses.update');

    Route::prefix('courses/{course}/participants')->group(function () {
        Route::get('/', [ParticipantController::class, 'index'])->name('participants.index'); // Lista uczestników
        Route::get('/create', [ParticipantController::class, 'create'])->name('participants.create'); // Formularz dodawania
        Route::post('/', [ParticipantController::class, 'store'])->name('participants.store'); // Dodawanie uczestnika
        Route::post('/import', [ParticipantController::class, 'import'])->name('participants.import'); // Import CSV
        Route::get('/{participant}/edit', [ParticipantController::class, 'edit'])->name('participants.edit'); // Edycja uczestnika
        Route::put('/{participant}', [ParticipantController::class, 'update'])->name('participants.update'); // Aktualizacja
        Route::delete('/{participant}', [ParticipantController::class, 'destroy'])->name('participants.destroy'); // Usuwanie
        Route::get('/download-pdf', [ParticipantController::class, 'downloadParticipantsList'])->name('participants.download-pdf'); // Pobieranie listy PDF
    });

    Route::get('/certificates/generate/{participant}', [CertificateController::class, 'generate'])->name('certificates.generate');
    Route::delete('/certificates/{certificate}', [CertificateController::class, 'destroy'])->name('certificates.destroy');
    Route::get('/courses/{course}/certificates/bulk-generate', [CertificateController::class, 'bulkGenerate'])->name('certificates.bulk-generate');
    Route::get('/courses/{course}/certificates/bulk-delete', [CertificateController::class, 'bulkDelete'])->name('certificates.bulk-delete');


    Route::get('participants/{participant}/certificate', [CertificateController::class, 'store'])->name('certificates.store');

    Route::get('/instructors', [InstructorsController::class, 'index'])->name('courses.instructors.index');
    Route::post('/instructors', [InstructorsController::class, 'store'])->name('courses.instructors.store');
    Route::get('/instructors/create', [InstructorsController::class, 'create'])->name('courses.instructors.create');
    Route::get('/instructors/{id}', [InstructorsController::class, 'show'])->name('courses.instructors.show');
    Route::get('/instructors/{id}/edit', [InstructorsController::class, 'edit'])->name('courses.instructors.edit');
    // Route::post('/instructors/{id}/update', [InstructorsController::class, 'update'])->name('courses.instructors.update');
    Route::put('/instructors/{id}', [InstructorsController::class, 'update'])->name('courses.instructors.update');
    Route::delete('/instructors/{id}', [InstructorsController::class, 'destroy'])->name('courses.instructors.destroy');

    // Ankiety
    Route::get('/surveys/bulk-report', [SurveyController::class, 'generateBulkReport'])->name('surveys.bulk-report');
    Route::resource('surveys', SurveyController::class);
    Route::get('/courses/{course}/surveys', [SurveyController::class, 'courseSurveys'])->name('surveys.course');
    Route::get('/surveys/{survey}/report/form', [SurveyController::class, 'showReportForm'])->name('surveys.report.form');
    Route::post('/surveys/{survey}/report', [SurveyController::class, 'generateReport'])->name('surveys.report');
    Route::get('/surveys/{survey}/download-file', [SurveyController::class, 'downloadOriginalFile'])->name('surveys.download-file');
    Route::delete('/surveys/{survey}/original-file', [SurveyController::class, 'deleteOriginalFile'])->name('surveys.delete-original-file');
    Route::post('/surveys/search-course', [SurveyController::class, 'searchCourse'])->name('surveys.search-course');
    
    // Import ankiet
    Route::get('/courses/{course}/surveys/import', [SurveyImportController::class, 'showImportForm'])->name('surveys.import');
    Route::post('/courses/{course}/surveys/import', [SurveyImportController::class, 'import'])->name('surveys.import.store');

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


