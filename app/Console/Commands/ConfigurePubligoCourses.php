<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Course;

class ConfigurePubligoCourses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'publigo:configure-courses 
                            {--course-id= : ID kursu do skonfigurowania}
                            {--publigo-id= : ID produktu z Publigo.pl}
                            {--list : Lista kursów z source_id_old = "certgen_Publigo"}
                            {--all : Skonfiguruj wszystkie kursy bez source_id_old}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Konfiguracja kursów dla webhooków Publigo.pl';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('list')) {
            $this->listPubligoCourses();
            return 0;
        }

        if ($this->option('all')) {
            $this->configureAllCourses();
            return 0;
        }

        $courseId = $this->option('course-id');
        $publigoId = $this->option('publigo-id');

        if (!$courseId || !$publigoId) {
            $this->error('Musisz podać --course-id i --publigo-id');
            $this->info('Przykład: php artisan publigo:configure-courses --course-id=1 --publigo-id=PRODUCT_123');
            return 1;
        }

        $course = Course::find($courseId);
        if (!$course) {
            $this->error("Kurs o ID {$courseId} nie istnieje");
            return 1;
        }

        $course->update([
            'source_id_old' => 'certgen_Publigo',
            'id_old' => $publigoId
        ]);

        $this->info("✅ Kurs '{$course->title}' został skonfigurowany dla Publigo");
        $this->info("   source_id_old: certgen_Publigo");
        $this->info("   id_old: {$publigoId}");

        return 0;
    }

    /**
     * Lista kursów skonfigurowanych dla Publigo
     */
    private function listPubligoCourses()
    {
        $courses = Course::where('source_id_old', 'certgen_Publigo')
                        ->orderBy('start_date', 'desc')
                        ->get();

        if ($courses->count() === 0) {
            $this->info('Brak kursów skonfigurowanych dla Publigo');
            return;
        }

        $this->info('Kursy skonfigurowane dla Publigo:');
        $this->newLine();

        $headers = ['ID', 'Tytuł', 'Data rozpoczęcia', 'ID Publigo', 'Data utworzenia'];
        $rows = [];

        foreach ($courses as $course) {
            $rows[] = [
                $course->id,
                $course->title,
                $course->start_date ? $course->start_date->format('Y-m-d') : 'Brak daty',
                $course->id_old ?? 'Brak',
                $course->created_at->format('Y-m-d H:i')
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * Konfiguracja wszystkich kursów bez source_id_old
     */
    private function configureAllCourses()
    {
        $courses = Course::whereNull('source_id_old')
                        ->orWhere('source_id_old', '')
                        ->orderBy('start_date', 'desc')
                        ->get();

        if ($courses->count() === 0) {
            $this->info('Wszystkie kursy są już skonfigurowane');
            return;
        }

        $this->info("Znaleziono {$courses->count()} kursów bez source_id_old:");
        $this->newLine();

        $headers = ['ID', 'Tytuł', 'Data rozpoczęcia', 'Obecne id_old'];
        $rows = [];

        foreach ($courses as $course) {
            $rows[] = [
                $course->id,
                $course->title,
                $course->start_date ? $course->start_date->format('Y-m-d') : 'Brak daty',
                $course->id_old ?? 'Brak'
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();

        if (!$this->confirm('Czy chcesz skonfigurować wszystkie te kursy dla Publigo?')) {
            $this->info('Operacja anulowana');
            return;
        }

        $publigoId = $this->ask('Podaj ID produktu z Publigo (zostanie użyte dla wszystkich kursów)');
        
        if (!$publigoId) {
            $this->error('Musisz podać ID produktu z Publigo');
            return;
        }

        $updated = 0;
        foreach ($courses as $course) {
            $course->update([
                'source_id_old' => 'certgen_Publigo',
                'id_old' => $publigoId . '_' . $course->id
            ]);
            $updated++;
        }

        $this->info("✅ Skonfigurowano {$updated} kursów dla Publigo");
        $this->info("   source_id_old: certgen_Publigo");
        $this->info("   id_old: {$publigoId}_[ID_KURSU]");
    }
}
