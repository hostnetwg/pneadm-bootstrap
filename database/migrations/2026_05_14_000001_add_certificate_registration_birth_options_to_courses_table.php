<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (! Schema::hasColumn('courses', 'certificate_registration_collect_birth_data')) {
                $table->boolean('certificate_registration_collect_birth_data')
                    ->default(false)
                    ->after('certificate_registration_token');
            }
            if (! Schema::hasColumn('courses', 'certificate_registration_birth_data_required')) {
                $table->boolean('certificate_registration_birth_data_required')
                    ->default(false)
                    ->after('certificate_registration_collect_birth_data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'certificate_registration_birth_data_required')) {
                $table->dropColumn('certificate_registration_birth_data_required');
            }
            if (Schema::hasColumn('courses', 'certificate_registration_collect_birth_data')) {
                $table->dropColumn('certificate_registration_collect_birth_data');
            }
        });
    }
};
