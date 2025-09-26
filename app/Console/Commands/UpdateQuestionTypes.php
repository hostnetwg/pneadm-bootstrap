<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SurveyQuestion;

class UpdateQuestionTypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'surveys:update-question-types {--survey-id= : ID konkretnej ankiety}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aktualizuje typy pytań na podstawie analizy odpowiedzi';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $surveyId = $this->option('survey-id');
        
        if ($surveyId) {
            $questions = SurveyQuestion::whereHas('survey', function($query) use ($surveyId) {
                $query->where('id', $surveyId);
            })->get();
            $this->info("Aktualizacja typów pytań dla ankiety ID: {$surveyId}");
        } else {
            $questions = SurveyQuestion::all();
            $this->info("Aktualizacja typów pytań dla wszystkich ankiet");
        }
        
        $updated = 0;
        $total = $questions->count();
        
        $this->info("Znaleziono {$total} pytań do analizy...");
        
        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();
        
        foreach ($questions as $question) {
            $oldType = $question->question_type;
            $question->updateQuestionTypeFromResponses();
            $question->refresh();
            
            if ($oldType !== $question->question_type) {
                $updated++;
                $this->line("\nPytanie ID {$question->id}: {$oldType} → {$question->question_type}");
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        $this->info("Zaktualizowano {$updated} z {$total} pytań.");
        
        return Command::SUCCESS;
    }
}
