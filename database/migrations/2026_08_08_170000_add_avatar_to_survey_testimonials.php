<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_testimonials', function (Blueprint $table) {
            if (! Schema::hasColumn('survey_testimonials', 'avatar_type')) {
                $table->string('avatar_type', 20)->nullable()->after('author_city');
            }
            if (! Schema::hasColumn('survey_testimonials', 'avatar_preset')) {
                $table->string('avatar_preset', 50)->nullable()->after('avatar_type');
            }
            if (! Schema::hasColumn('survey_testimonials', 'avatar_path')) {
                $table->string('avatar_path', 500)->nullable()->after('avatar_preset');
            }
        });
    }

    public function down(): void
    {
        Schema::table('survey_testimonials', function (Blueprint $table) {
            foreach (['avatar_path', 'avatar_preset', 'avatar_type'] as $col) {
                if (Schema::hasColumn('survey_testimonials', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
