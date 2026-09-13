<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domyślny format i szablon zaświadczeń dla nowo dodawanych szkoleń w serii.
     * Nie backfilluje kursów już przypisanych.
     */
    public function up(): void
    {
        Schema::table('course_series', function (Blueprint $table) {
            $table->string('certificate_format', 255)
                ->nullable()
                ->after('sort_order');
            $table->foreignId('certificate_template_id')
                ->nullable()
                ->after('certificate_format')
                ->constrained('certificate_templates')
                ->nullOnDelete();
        });

        $templateId = DB::table('certificate_templates')->where('id', 5)->value('id');

        DB::table('course_series')
            ->where('slug', 'tik-w-pracy-nauczyciela')
            ->update([
                'certificate_format' => '{nr}/{course_id}/{year}/TIK',
                'certificate_template_id' => $templateId,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('course_series', function (Blueprint $table) {
            $table->dropConstrainedForeignId('certificate_template_id');
            $table->dropColumn('certificate_format');
        });
    }
};
