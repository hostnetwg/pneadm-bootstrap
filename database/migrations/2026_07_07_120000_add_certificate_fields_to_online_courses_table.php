<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_courses', function (Blueprint $table) {
            $table->string('certificate_download_status', 32)
                ->default('no_certificate')
                ->after('legacy_publigo_product_id')
                ->comment('download_enabled | in_preparation | no_certificate');
            $table->foreignId('certificate_template_id')
                ->nullable()
                ->after('certificate_download_status')
                ->constrained('certificate_templates')
                ->nullOnDelete();
            $table->string('certificate_format', 191)
                ->default('{nr}/{online_course_id}/{year}/PNE-KO')
                ->after('certificate_template_id');
            $table->date('certificate_issue_date')
                ->nullable()
                ->after('certificate_format')
                ->comment('Globalna data na zaświadczeniu; null = data pierwszego pobrania');
            $table->boolean('certificate_collect_birth_data')
                ->default(false)
                ->after('certificate_issue_date');
            $table->boolean('certificate_birth_data_required')
                ->default(false)
                ->after('certificate_collect_birth_data');
            $table->unsignedTinyInteger('certificate_completion_threshold_percent')
                ->nullable()
                ->after('certificate_birth_data_required')
                ->comment('NULL = wyłączone; przyszły próg ukończenia lekcji');
        });
    }

    public function down(): void
    {
        Schema::table('online_courses', function (Blueprint $table) {
            $table->dropForeign(['certificate_template_id']);
            $table->dropColumn([
                'certificate_download_status',
                'certificate_template_id',
                'certificate_format',
                'certificate_issue_date',
                'certificate_collect_birth_data',
                'certificate_birth_data_required',
                'certificate_completion_threshold_percent',
            ]);
        });
    }
};
