<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_courses', function (Blueprint $table) {
            $table->text('training_scope')
                ->nullable()
                ->after('description')
                ->comment('Zakres szkolenia / zagadnienia na zaświadczeniu (gdy włączone „Pokaż zakres szkolenia” w szablonie)');
        });
    }

    public function down(): void
    {
        Schema::table('online_courses', function (Blueprint $table) {
            $table->dropColumn('training_scope');
        });
    }
};
