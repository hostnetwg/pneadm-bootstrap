<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_order_participants', function (Blueprint $table) {
            if (! Schema::hasColumn('form_order_participants', 'participant_id')) {
                $table->unsignedBigInteger('participant_id')->nullable()->after('is_primary');
                $table->foreign('participant_id', 'fk_fop_participant_id')
                    ->references('id')
                    ->on('participants')
                    ->nullOnDelete();
                $table->index('participant_id', 'idx_fop_participant_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('form_order_participants', function (Blueprint $table) {
            if (Schema::hasColumn('form_order_participants', 'participant_id')) {
                $table->dropForeign('fk_fop_participant_id');
                $table->dropIndex('idx_fop_participant_id');
                $table->dropColumn('participant_id');
            }
        });
    }
};
