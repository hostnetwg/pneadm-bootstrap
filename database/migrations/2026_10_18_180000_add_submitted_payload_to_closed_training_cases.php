<?php

use App\Services\ClosedTrainingSubmissionBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('closed_training_cases', 'submitted_payload')) {
            Schema::table('closed_training_cases', function (Blueprint $table) {
                $table->json('submitted_payload')->nullable()->after('message');
            });
        }

        app(ClosedTrainingSubmissionBackfill::class)->backfillUntouched();
    }

    public function down(): void
    {
        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->dropColumn('submitted_payload');
        });
    }
};
