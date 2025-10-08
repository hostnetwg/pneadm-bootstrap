<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (!Schema::hasColumn('courses', 'access_duration_days')) {
                $table->integer('access_duration_days')->nullable()->after('description');
            }
            if (!Schema::hasColumn('courses', 'access_notes')) {
                $table->text('access_notes')->nullable()->after('access_duration_days');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['access_duration_days', 'access_notes']);
        });
    }
};
