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
use App\Http\Controllers\RSPOController;
use App\Http\Controllers\RSPOImportController;
use App\Http\Controllers\IfirmaController;
use App\Http\Controllers\FormOrdersController;
use App\Http\Controllers\MarketingCampaignController;
use App\Http\Controllers\MarketingSourceTypeController;
use App\Http\Controllers\WebhookPubligoController;
use App\Http\Controllers\ZamowieniaController;
use App\Http\Controllers\ZamowieniaProdController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\SendyController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\SurveyImportController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\UserPreferencesController;
use App\Http\Controllers\CoursePriceVariantController;
use App\Http\Controllers\AccountingController;

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'check.user.status'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/refresh', [DashboardController::class, 'refresh'])->name('dashboard.refresh');
    
    // User Preferences API
    Route::prefix('api/user')->name('api.user.')->group(function () {
        Route::get('preferences', [UserPreferencesController::class, 'get'])->name('preferences.get');
        Route::post('preferences', [UserPreferencesController::class, 'update'])->name('preferences.update');
    });

    // Admin Panel
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UsersController::class);
        Route::patch('users/{user}/toggle-status', [UsersController::class, 'toggleStatus'])->name('users.toggle-status');
        
        // Statystyki
        Route::get('statistics', [StatisticsController::class, 'index'])->name('statistics.index');
        
        // Zarządzanie szablonami certyfikatów
        Route::resource('certificate-templates', CertificateTemplateController::class);
        Route::get('certificate-templates/{certificateTemplate}/preview', [CertificateTemplateController::class, 'preview'])->name('certificate-templates.preview');
        Route::get('certificate-templates/{certificateTemplate}/clone', [CertificateTemplateController::class, 'clone'])->name('certificate-templates.clone');
        Route::post('certificate-templates/{id}/restore', [CertificateTemplateController::class, 'restore'])->name('certificate-templates.restore');
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

    /* RSPO - Rejestr Szkół i Placówek Oświatowych */
    Route::prefix('rspo')->name('rspo.')->group(function () {
        Route::get('/search', [RSPOController::class, 'search'])->name('search');
        
        // Import do Sendy
        Route::prefix('import')->name('import.')->group(function () {
            Route::get('/', [RSPOImportController::class, 'index'])->name('index');
            Route::post('/import', [RSPOImportController::class, 'import'])->name('import');
        });
    });

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
    
    // iFirma.pl - integracja z oprogramowaniem księgowym
    Route::prefix('ifirma')->name('ifirma.')->group(function () {
        Route::get('/test-connection', [IfirmaController::class, 'testConnection'])->name('test-connection');
    });
    
    Route::get('/import-publigo', [CoursesController::class, 'importFromPubligo'])->name('courses.importPubligo');      
    
    // Baza Certgen - dane dla webhook
    Route::prefix('certgen')->name('certgen.')->group(function () {
        // Formularze zamówień z tabeli zamowienia_PROD
        Route::get('/zamowienia-prod', [ZamowieniaProdController::class, 'index'])->name('zamowienia_prod.index');
        Route::get('/zamowienia-prod/create', [ZamowieniaProdController::class, 'create'])->name('zamowienia_prod.create');
        Route::post('/zamowienia-prod', [ZamowieniaProdController::class, 'store'])->name('zamowienia_prod.store');
        Route::get('/zamowienia-prod/{id}', [ZamowieniaProdController::class, 'show'])->name('zamowienia_prod.show');
        Route::get('/zamowienia-prod/{id}/edit', [ZamowieniaProdController::class, 'edit'])->name('zamowienia_prod.edit');
        Route::put('/zamowienia-prod/{id}', [ZamowieniaProdController::class, 'update'])->name('zamowienia_prod.update');
        Route::delete('/zamowienia-prod/{id}', [ZamowieniaProdController::class, 'destroy'])->name('zamowienia_prod.destroy');
        
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
        Route::get('/zamowienia/{id}/edit', [ZamowieniaController::class, 'edit'])->name('zamowienia.edit');
        Route::put('/zamowienia/{id}', [ZamowieniaController::class, 'update'])->name('zamowienia.update');
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


    // Form Orders - nowa tabela w bazie pneadm
    Route::middleware(['auth', 'verified', 'check.user.status'])
    ->prefix('form-orders')
    ->name('form-orders.')
    ->group(function () {
        Route::get('/', [FormOrdersController::class, 'index'])->name('index');
        Route::get('/create', [FormOrdersController::class, 'create'])->name('create');
        Route::post('/', [FormOrdersController::class, 'store'])->name('store');
        Route::get('/duplicates', [FormOrdersController::class, 'duplicates'])->name('duplicates');
        Route::get('/{id}', [FormOrdersController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [FormOrdersController::class, 'edit'])->name('edit');
        Route::put('/{id}', [FormOrdersController::class, 'update'])->name('update');
        Route::delete('/{id}', [FormOrdersController::class, 'destroy'])->name('destroy');
        Route::delete('/duplicates/{id}', [FormOrdersController::class, 'destroyDuplicate'])->name('duplicates.destroy');
        Route::delete('/duplicates/group/{email}/{productId}', [FormOrdersController::class, 'destroyAllDuplicatesForGroup'])->name('duplicates.destroy-group');
        Route::delete('/duplicates/group/{email}/{productId}/keep/{keepOrderId}', [FormOrdersController::class, 'destroyDuplicatesKeepSelected'])->name('duplicates.keep-selected');
        Route::post('/duplicates/{id}/mark-completed', [FormOrdersController::class, 'markAsCompleted'])->name('duplicates.mark-completed');
        Route::post('/duplicates/{id}/update-notes', [FormOrdersController::class, 'updateNotes'])->name('duplicates.update-notes');
        Route::post('/{id}/publigo/create', [FormOrdersController::class, 'createPubligoOrder'])->name('publigo.create');
        Route::post('/{id}/publigo/reset', [FormOrdersController::class, 'resetPubligoStatus'])->name('publigo.reset');
        Route::get('/{id}/ifirma/check-invoice', [FormOrdersController::class, 'checkInvoiceStatus'])->name('ifirma.check-invoice');
        Route::post('/{id}/ifirma/proforma', [FormOrdersController::class, 'createIfirmaProForma'])->name('ifirma.proforma');
        Route::post('/{id}/ifirma/invoice', [FormOrdersController::class, 'createIfirmaInvoice'])->name('ifirma.invoice');
    });

    // Marketing Campaigns - źródła pozyskania
    Route::middleware(['auth', 'verified', 'check.user.status'])
    ->prefix('marketing-campaigns')
    ->name('marketing-campaigns.')
    ->group(function () {
        Route::get('/', [MarketingCampaignController::class, 'index'])->name('index');
        Route::get('/create', [MarketingCampaignController::class, 'create'])->name('create');
        Route::post('/', [MarketingCampaignController::class, 'store'])->name('store');
        Route::get('/{marketingCampaign}', [MarketingCampaignController::class, 'show'])->name('show');
        Route::get('/{marketingCampaign}/edit', [MarketingCampaignController::class, 'edit'])->name('edit');
        Route::put('/{marketingCampaign}', [MarketingCampaignController::class, 'update'])->name('update');
        Route::delete('/{marketingCampaign}', [MarketingCampaignController::class, 'destroy'])->name('destroy');
    });

    // Marketing Source Types - zarządzanie typami źródeł
    Route::middleware(['auth', 'verified', 'check.user.status'])
    ->prefix('marketing-source-types')
    ->name('marketing-source-types.')
    ->group(function () {
        Route::get('/', [MarketingSourceTypeController::class, 'index'])->name('index');
        Route::get('/create', [MarketingSourceTypeController::class, 'create'])->name('create');
        Route::post('/', [MarketingSourceTypeController::class, 'store'])->name('store');
        Route::get('/{marketingSourceType}', [MarketingSourceTypeController::class, 'show'])->name('show');
        Route::get('/{marketingSourceType}/edit', [MarketingSourceTypeController::class, 'edit'])->name('edit');
        Route::put('/{marketingSourceType}', [MarketingSourceTypeController::class, 'update'])->name('update');
        Route::delete('/{marketingSourceType}', [MarketingSourceTypeController::class, 'destroy'])->name('destroy');
    });


/**/

    Route::get('/courses', [CoursesController::class, 'index'])->name('courses.index');
    Route::resource('courses/series', \App\Http\Controllers\CourseSeriesController::class)->names([
        'index' => 'courses.series.index',
        'create' => 'courses.series.create',
        'store' => 'courses.series.store',
        'show' => 'courses.series.show',
        'edit' => 'courses.series.edit',
        'update' => 'courses.series.update',
        'destroy' => 'courses.series.destroy',
    ]);
    Route::put('/courses/series/{series}/update-courses', [\App\Http\Controllers\CourseSeriesController::class, 'updateCourses'])->name('courses.series.update-courses');
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

    // Nagrania wideo dla kursów
    Route::prefix('courses/{course}/videos')->name('courses.videos.')->group(function () {
        Route::get('/', [\App\Http\Controllers\CourseVideoController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\CourseVideoController::class, 'store'])->name('store');
        Route::put('/{video}', [\App\Http\Controllers\CourseVideoController::class, 'update'])->name('update');
        Route::delete('/{video}', [\App\Http\Controllers\CourseVideoController::class, 'destroy'])->name('destroy');
    });
    
    // Warianty cenowe kursów
    Route::prefix('courses/{courseId}/price-variants')->name('courses.price-variants.')->group(function () {
        Route::get('/create', [CoursePriceVariantController::class, 'create'])->name('create');
        Route::post('/', [CoursePriceVariantController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [CoursePriceVariantController::class, 'edit'])->name('edit');
        Route::put('/{id}', [CoursePriceVariantController::class, 'update'])->name('update');
        Route::delete('/{id}', [CoursePriceVariantController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/restore', [CoursePriceVariantController::class, 'restore'])->name('restore');
    });

    // Uzupełnienie danych uczestników
    Route::prefix('data-completion')->name('data-completion.')->group(function () {
        Route::get('/test', [\App\Http\Controllers\DataCompletionController::class, 'test'])->name('test');
        Route::get('/collect', [\App\Http\Controllers\DataCompletionController::class, 'collect'])->name('collect');
        Route::get('/simulate-test/{courseId}', [\App\Http\Controllers\DataCompletionController::class, 'simulateTest'])->name('simulate-test');
        Route::post('/send-test-email/{courseId}', [\App\Http\Controllers\DataCompletionController::class, 'sendTestEmail'])->name('send-test-email');
        Route::post('/send-for-course/{courseId}', [\App\Http\Controllers\DataCompletionController::class, 'sendForCourse'])->name('send-for-course');
        Route::post('/refresh-bd-certgen-stats', [\App\Http\Controllers\DataCompletionController::class, 'refreshBDCertgenEducationStats'])->name('refresh-bd-certgen-stats');
        Route::get('/conflicts', [\App\Http\Controllers\DataCompletionController::class, 'conflicts'])->name('conflicts');
        Route::post('/unify-conflict', [\App\Http\Controllers\DataCompletionController::class, 'unifyConflict'])->name('unify-conflict');
    });

    // Lista wszystkich uczestników
    Route::get('/participants', [ParticipantController::class, 'all'])->name('participants.all');
    Route::get('/participants/emails', [ParticipantController::class, 'emailsList'])->name('participants.emails-list');
    Route::post('/participants/collect-emails', [ParticipantController::class, 'collectEmails'])->name('participants.collect-emails');
    Route::put('/participants/emails/{participantEmail}', [ParticipantController::class, 'updateEmail'])->name('participants.emails.update');
    Route::delete('/participants/emails/{participantEmail}', [ParticipantController::class, 'destroyEmail'])->name('participants.emails.destroy');
    
    Route::prefix('courses/{course}/participants')->group(function () {
        Route::get('/', [ParticipantController::class, 'index'])->name('participants.index'); // Lista uczestników
        Route::get('/create', [ParticipantController::class, 'create'])->name('participants.create'); // Formularz dodawania
        Route::post('/', [ParticipantController::class, 'store'])->name('participants.store'); // Dodawanie uczestnika
        Route::post('/import', [ParticipantController::class, 'import'])->name('participants.import'); // Import CSV
        Route::post('/import-certificates', [CertificateController::class, 'importFromPubligo'])->name('certificates.import'); // Import certyfikatów z Publigo
        Route::get('/{participant}/edit', [ParticipantController::class, 'edit'])->name('participants.edit'); // Edycja uczestnika
        Route::put('/{participant}', [ParticipantController::class, 'update'])->name('participants.update'); // Aktualizacja
        Route::delete('/{participant}', [ParticipantController::class, 'destroy'])->name('participants.destroy'); // Usuwanie
        Route::get('/download-pdf', [ParticipantController::class, 'downloadParticipantsList'])->name('participants.download-pdf'); // Pobieranie listy PDF
        Route::get('/download-registry', [ParticipantController::class, 'downloadCertificateRegistry'])->name('participants.download-registry'); // Pobieranie rejestru zaświadczeń
    });

    Route::get('/certificates/generate/{participant}', [CertificateController::class, 'generate'])->name('certificates.generate');
    Route::delete('/certificates/{certificate}', [CertificateController::class, 'destroy'])->name('certificates.destroy');
    Route::get('/courses/{course}/certificates/bulk-generate', [CertificateController::class, 'bulkGenerate'])->name('certificates.bulk-generate');
    Route::get('/courses/{course}/certificates/bulk-generate-all', [CertificateController::class, 'bulkGenerateAll'])->name('certificates.bulk-generate-all');
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

    // Kosz (Soft Delete Management)
    Route::prefix('trash')->name('trash.')->group(function () {
        Route::get('/', [TrashController::class, 'index'])->name('index');
        Route::post('/restore/{table}/{id}', [TrashController::class, 'restore'])->name('restore');
        Route::delete('/force-delete/{table}/{id}', [TrashController::class, 'forceDelete'])->name('force-delete');
        Route::delete('/empty-table/{table}', [TrashController::class, 'emptyTable'])->name('empty-table');
        Route::delete('/empty-all', [TrashController::class, 'emptyAll'])->name('empty-all');
    });

    // Logi aktywności (Activity Logs)
    Route::prefix('activity-logs')->name('activity-logs.')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index'])->name('index');
        Route::get('/statistics', [ActivityLogController::class, 'statistics'])->name('statistics');
        Route::get('/export', [ActivityLogController::class, 'export'])->name('export');
        Route::get('/user/{userId}', [ActivityLogController::class, 'userLogs'])->name('user-logs');
        Route::get('/model/{modelType}/{modelId}', [ActivityLogController::class, 'modelLogs'])->name('model-logs');
        Route::get('/{id}', [ActivityLogController::class, 'show'])->name('show');
    });

    // Księgowość - Raporty i Wprowadź dane
    Route::prefix('accounting')->name('accounting.')->group(function () {
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [AccountingController::class, 'reportsIndex'])->name('index');
        });
        Route::prefix('data-entry')->name('data-entry.')->group(function () {
            Route::get('/', [AccountingController::class, 'dataEntryIndex'])->name('index');
            Route::post('/', [AccountingController::class, 'dataEntryStore'])->name('store');
            Route::put('/{id}', [AccountingController::class, 'dataEntryUpdate'])->name('update');
            Route::delete('/{id}', [AccountingController::class, 'dataEntryDestroy'])->name('destroy');
        });
    });
});

require __DIR__.'/auth.php';

// Publiczny formularz uzupełniania danych (bez auth)
Route::get('/uzupelnij-dane/{token}', [\App\Http\Controllers\DataCompletionFormController::class, 'show'])->name('data-completion.form');
Route::post('/uzupelnij-dane/{token}', [\App\Http\Controllers\DataCompletionFormController::class, 'store'])->name('data-completion.form.store');

// Webhook dla Publigo.pl - bez CSRF protection
Route::post('/api/publigo/webhook', [PubligoController::class, 'webhook'])
    ->middleware('publigo.webhook')
    ->name('publigo.webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

Route::post('/api/publigo/webhook-test', [PubligoController::class, 'webhookTest'])
    ->name('publigo.webhook.test')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);


