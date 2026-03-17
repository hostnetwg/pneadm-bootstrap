<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->unsignedInteger('download_count')->default(0)->after('file_path');
            $table->timestamp('first_downloaded_at')->nullable()->after('download_count');
            $table->timestamp('last_downloaded_at')->nullable()->after('first_downloaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn(['download_count', 'first_downloaded_at', 'last_downloaded_at']);
        });
    }
};

