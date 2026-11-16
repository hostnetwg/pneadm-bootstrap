<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->string('school_certificate_token', 64)->nullable()->unique('ct_cases_school_cert_token_uniq');
        });
    }

    public function down(): void
    {
        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->dropUnique('ct_cases_school_cert_token_uniq');
            $table->dropColumn('school_certificate_token');
        });
    }
};
