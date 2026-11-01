<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Jednorazowe uzupełnienie certificates.issue_date dla zaświadczeń z plikiem PDF,
 * które nie mają daty wystawienia (ani własnej, ani w kursie).
 *
 * Powód: gdy PDF zostanie skasowany (retencja 30 dni) i wygenerowany ponownie, a nigdzie nie ma
 * daty wystawienia, generator użyłby dzisiejszej daty (CertificateGeneratorService::
 * resolveEffectiveCompletionDate). Wpisujemy datę pierwszego wygenerowania pliku.
 *
 * Nie nadpisuje istniejących wartości ani dat ustawionych w kursie (courses.issue_date_certyficates,
 * online_courses.certificate_issue_date). Tylko dane, bez zmian schematu. Dalszą ochronę daje
 * CertificatePdfRetentionService (zamraża datę tuż przed skasowaniem pliku).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('certificates as c')
            ->leftJoin('courses as co', 'co.id', '=', 'c.course_id')
            ->leftJoin('online_courses as oc', 'oc.id', '=', 'c.online_course_id')
            ->whereNotNull('c.file_path')
            ->where('c.file_path', '!=', '')
            ->whereNull('c.issue_date')
            ->whereNotNull('c.generated_at')
            ->whereNull('co.issue_date_certyficates')
            ->whereNull('oc.certificate_issue_date')
            ->update(['c.issue_date' => DB::raw('DATE(c.generated_at)')]);
    }

    public function down(): void
    {
        // Nie do odwrócenia: nie da się odróżnić uzupełnionych dat od wpisanych wcześniej.
    }
};
