<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Dodaje status zaświadczeń: download_enabled | in_preparation | no_certificate.
     * Jeżeli istnieje stara kolumna certificates_download_enabled – przepisuje wartości i usuwa ją.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('certificate_download_status', 32)
                ->default('in_preparation')
                ->after('certificate_template_id')
                ->comment('download_enabled | in_preparation | no_certificate');
        });

        if (Schema::hasColumn('courses', 'certificates_download_enabled')) {
            DB::table('courses')->update([
                'certificate_download_status' => DB::raw(
                    "CASE WHEN COALESCE(certificates_download_enabled, 0) = 1 THEN 'download_enabled' ELSE 'in_preparation' END"
                ),
            ]);
            Schema::table('courses', function (Blueprint $table) {
                $table->dropColumn('certificates_download_enabled');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('certificates_download_enabled')->default(false)->after('certificate_template_id');
        });

        DB::table('courses')->update([
            'certificates_download_enabled' => DB::raw(
                "CASE WHEN certificate_download_status = 'download_enabled' THEN 1 ELSE 0 END"
            ),
        ]);

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('certificate_download_status');
        });
    }
};
