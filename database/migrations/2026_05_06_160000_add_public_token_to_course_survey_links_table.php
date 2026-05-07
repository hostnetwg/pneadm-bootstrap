<?php

use App\Models\CourseSurveyLink;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_survey_links', function (Blueprint $table) {
            $table->string('public_token', 48)->nullable()->unique()->after('id');
        });

        CourseSurveyLink::query()
            ->whereNull('public_token')
            ->eachById(function (CourseSurveyLink $link) {
                $link->public_token = CourseSurveyLink::generateUniquePublicToken();
                $link->saveQuietly();
            });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE course_survey_links MODIFY public_token VARCHAR(48) NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('course_survey_links', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
        });
    }
};
