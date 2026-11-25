<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->text('trainer_note')->nullable()->after('trainer_panel_shared_at');
        });
    }

    public function down(): void
    {
        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->dropColumn('trainer_note');
        });
    }
};
