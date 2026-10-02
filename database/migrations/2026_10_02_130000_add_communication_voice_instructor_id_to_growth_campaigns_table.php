<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('growth_campaigns', function (Blueprint $table) {
            $table->foreignId('communication_voice_instructor_id')
                ->nullable()
                ->after('primary_instructor_id')
                ->constrained('instructors')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('growth_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('communication_voice_instructor_id');
        });
    }
};
