<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participant_live_access', function (Blueprint $table) {
            $table->timestamp('embed_last_seen_at')->nullable()->after('embed_last_entered_at');
            $table->index(['course_id', 'embed_last_seen_at'], 'pla_course_embed_last_seen_idx');
        });
    }

    public function down(): void
    {
        Schema::table('participant_live_access', function (Blueprint $table) {
            $table->dropIndex('pla_course_embed_last_seen_idx');
            $table->dropColumn('embed_last_seen_at');
        });
    }
};
