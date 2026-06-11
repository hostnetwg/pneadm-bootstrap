<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_course_lessons', function (Blueprint $table) {
            $table->unsignedBigInteger('linked_course_id')->nullable()->after('sort_order');
            $table->foreign('linked_course_id')
                ->references('id')
                ->on('courses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('online_course_lessons', function (Blueprint $table) {
            $table->dropForeign(['linked_course_id']);
            $table->dropColumn('linked_course_id');
        });
    }
};
