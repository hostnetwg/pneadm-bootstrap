<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('certificate_registration_open')->default(false)->after('certificate_download_status');
            $table->dateTime('certificate_registration_starts_at')->nullable()->after('certificate_registration_open');
            $table->dateTime('certificate_registration_ends_at')->nullable()->after('certificate_registration_starts_at');
            $table->string('certificate_registration_token', 64)->nullable()->unique()->after('certificate_registration_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'certificate_registration_open',
                'certificate_registration_starts_at',
                'certificate_registration_ends_at',
                'certificate_registration_token',
            ]);
        });
    }
};
