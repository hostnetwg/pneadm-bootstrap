<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (! Schema::hasColumn('courses', 'post_end_access_rule')) {
                $table->string('post_end_access_rule', 16)
                    ->default('duration')
                    ->after('post_end_access_duration_unit')
                    ->comment('Reguła dostępu po zakończeniu szkolenia: duration, unlimited');
            }
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'post_end_access_rule')) {
                $table->dropColumn('post_end_access_rule');
            }
        });
    }
};
