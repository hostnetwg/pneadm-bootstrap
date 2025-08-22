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
        Schema::table('participants', function (Blueprint $table) {
            $table->timestamp('access_expires_at')->nullable()->after('order');
            $table->index('access_expires_at'); // Indeks dla szybkiego wyszukiwania
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropIndex(['access_expires_at']);
            $table->dropColumn('access_expires_at');
        });
    }
};
