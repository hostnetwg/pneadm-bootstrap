<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_ai_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('general_model', 64);
            $table->string('general_reasoning_effort', 16);
            $table->string('research_model', 64);
            $table->string('research_reasoning_effort', 16);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('growth_ai_settings')->insert([
            'id' => 1,
            'general_model' => 'gpt-6.1-sol',
            'general_reasoning_effort' => 'medium',
            'research_model' => 'gpt-6.1-sol',
            'research_reasoning_effort' => 'high',
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_ai_settings');
    }
};
