<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_courses', function (Blueprint $table) {
            $table->unsignedSmallInteger('certificate_duration_minutes')
                ->nullable()
                ->after('certificate_issue_date')
                ->comment('Czas trwania szkolenia na zaświadczeniu (minuty); używane przy {czas_trwania} w szablonie');
        });
    }

    public function down(): void
    {
        Schema::table('online_courses', function (Blueprint $table) {
            $table->dropColumn('certificate_duration_minutes');
        });
    }
};
