<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign(['participant_id']);
            $table->dropForeign(['course_id']);
        });

        DB::statement('ALTER TABLE certificates MODIFY participant_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE certificates MODIFY course_id BIGINT UNSIGNED NULL');

        Schema::table('certificates', function (Blueprint $table) {
            $table->foreign('participant_id')->references('id')->on('participants')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();

            $table->foreignId('online_course_id')
                ->nullable()
                ->after('course_id')
                ->constrained('online_courses')
                ->cascadeOnDelete();
            $table->foreignId('online_course_enrollment_id')
                ->nullable()
                ->after('online_course_id')
                ->constrained('online_course_enrollments')
                ->cascadeOnDelete();

            $table->string('holder_first_name')->nullable()->after('online_course_enrollment_id');
            $table->string('holder_last_name')->nullable()->after('holder_first_name');
            $table->date('holder_birth_date')->nullable()->after('holder_last_name');
            $table->string('holder_birth_place')->nullable()->after('holder_birth_date');
            $table->string('holder_email_normalized')->nullable()->after('holder_birth_place');

            $table->unique(
                ['online_course_id', 'online_course_enrollment_id'],
                'certificates_online_course_enrollment_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropUnique('certificates_online_course_enrollment_unique');
            $table->dropForeign(['online_course_id']);
            $table->dropForeign(['online_course_enrollment_id']);
            $table->dropColumn([
                'online_course_id',
                'online_course_enrollment_id',
                'holder_first_name',
                'holder_last_name',
                'holder_birth_date',
                'holder_birth_place',
                'holder_email_normalized',
            ]);
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign(['participant_id']);
            $table->dropForeign(['course_id']);
        });

        DB::statement('ALTER TABLE certificates MODIFY participant_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE certificates MODIFY course_id BIGINT UNSIGNED NOT NULL');

        Schema::table('certificates', function (Blueprint $table) {
            $table->foreign('participant_id')->references('id')->on('participants')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }
};
