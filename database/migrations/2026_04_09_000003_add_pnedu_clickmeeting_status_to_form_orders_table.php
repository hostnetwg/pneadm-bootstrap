<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->string('pnedu_clickmeeting_status', 32)
                ->nullable()
                ->after('pnedu_user_existed_before');
            $table->timestamp('pnedu_clickmeeting_synced_at')
                ->nullable()
                ->after('pnedu_clickmeeting_status');
            $table->text('pnedu_clickmeeting_message')
                ->nullable()
                ->after('pnedu_clickmeeting_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->dropColumn([
                'pnedu_clickmeeting_message',
                'pnedu_clickmeeting_synced_at',
                'pnedu_clickmeeting_status',
            ]);
        });
    }
};
