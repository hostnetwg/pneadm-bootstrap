<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->string('contact_email_alt1')->nullable()->after('contact_email');
            $table->string('contact_email_alt2')->nullable()->after('contact_email_alt1');
        });
    }

    public function down(): void
    {
        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->dropColumn(['contact_email_alt1', 'contact_email_alt2']);
        });
    }
};
