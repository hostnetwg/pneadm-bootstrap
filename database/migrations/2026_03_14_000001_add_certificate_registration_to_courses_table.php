<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (!Schema::hasColumn('courses', 'certificate_registration_open')) {
                $table->boolean('certificate_registration_open')->default(false);
            }
            if (!Schema::hasColumn('courses', 'certificate_registration_starts_at')) {
                $table->dateTime('certificate_registration_starts_at')->nullable();
            }
            if (!Schema::hasColumn('courses', 'certificate_registration_ends_at')) {
                $table->dateTime('certificate_registration_ends_at')->nullable();
            }
            if (!Schema::hasColumn('courses', 'certificate_registration_token')) {
                $table->string('certificate_registration_token', 64)->nullable()->unique();
            }
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
