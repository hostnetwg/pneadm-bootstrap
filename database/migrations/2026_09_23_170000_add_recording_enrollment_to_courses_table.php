<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (! Schema::hasColumn('courses', 'recording_enrollment_open')) {
                $table->boolean('recording_enrollment_open')->default(false);
            }
            if (! Schema::hasColumn('courses', 'recording_enrollment_ends_at')) {
                $table->dateTime('recording_enrollment_ends_at')->nullable();
            }
            if (! Schema::hasColumn('courses', 'recording_enrollment_token')) {
                $table->string('recording_enrollment_token', 64)->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'recording_enrollment_token')) {
                $table->dropUnique(['recording_enrollment_token']);
            }
            $table->dropColumn([
                'recording_enrollment_open',
                'recording_enrollment_ends_at',
                'recording_enrollment_token',
            ]);
        });
    }
};
