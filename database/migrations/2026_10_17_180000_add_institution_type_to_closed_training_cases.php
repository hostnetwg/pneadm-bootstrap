<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->string('institution_type', 32)->nullable()->after('institution');
            $table->string('institution_type_other')->nullable()->after('institution_type');
        });
    }

    public function down(): void
    {
        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->dropColumn(['institution_type', 'institution_type_other']);
        });
    }
};
