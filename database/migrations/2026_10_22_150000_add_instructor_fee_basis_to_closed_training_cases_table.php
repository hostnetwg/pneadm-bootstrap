<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->string('instructor_fee_basis', 8)->default('gross')->after('instructor_fee');
        });
    }

    public function down(): void
    {
        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->dropColumn('instructor_fee_basis');
        });
    }
};
