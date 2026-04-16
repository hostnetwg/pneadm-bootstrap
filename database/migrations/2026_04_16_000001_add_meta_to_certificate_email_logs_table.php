<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_email_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('certificate_email_logs', 'meta')) {
                $table->json('meta')->nullable()->after('error_message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('certificate_email_logs', function (Blueprint $table) {
            if (Schema::hasColumn('certificate_email_logs', 'meta')) {
                $table->dropColumn('meta');
            }
        });
    }
};
