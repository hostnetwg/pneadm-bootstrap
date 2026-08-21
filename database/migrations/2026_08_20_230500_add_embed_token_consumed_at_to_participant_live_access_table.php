<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participant_live_access', function (Blueprint $table) {
            $table->timestamp('embed_token_consumed_at')->nullable()->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('participant_live_access', function (Blueprint $table) {
            $table->dropColumn('embed_token_consumed_at');
        });
    }
};
