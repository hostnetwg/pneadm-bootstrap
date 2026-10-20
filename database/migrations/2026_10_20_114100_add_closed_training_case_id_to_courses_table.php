<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('closed_training_case_id')
                ->nullable()
                ->unique()
                ->after('training_offer_id')
                ->constrained('closed_training_cases')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_training_case_id');
        });
    }
};
