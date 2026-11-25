<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->timestamp('trainer_declined_at')->nullable()->after('trainer_note');
            $table->text('trainer_decline_reason')->nullable()->after('trainer_declined_at');
        });
    }

    public function down(): void
    {
        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->dropColumn(['trainer_declined_at', 'trainer_decline_reason']);
        });
    }
};
