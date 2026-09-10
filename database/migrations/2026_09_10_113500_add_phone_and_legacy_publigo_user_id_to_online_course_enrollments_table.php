<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_course_enrollments', function (Blueprint $table) {
            if (! Schema::hasColumn('online_course_enrollments', 'phone')) {
                $table->string('phone', 50)->nullable()->after('last_name');
            }
            if (! Schema::hasColumn('online_course_enrollments', 'legacy_publigo_user_id')) {
                $table->string('legacy_publigo_user_id', 32)->nullable()->after('access_source');
                $table->index('legacy_publigo_user_id', 'idx_oc_enroll_publigo_user');
            }
        });
    }

    public function down(): void
    {
        Schema::table('online_course_enrollments', function (Blueprint $table) {
            if (Schema::hasColumn('online_course_enrollments', 'legacy_publigo_user_id')) {
                $table->dropIndex('idx_oc_enroll_publigo_user');
                $table->dropColumn('legacy_publigo_user_id');
            }
            if (Schema::hasColumn('online_course_enrollments', 'phone')) {
                $table->dropColumn('phone');
            }
        });
    }
};
