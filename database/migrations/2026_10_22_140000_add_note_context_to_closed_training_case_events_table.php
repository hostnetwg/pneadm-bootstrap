<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('closed_training_case_events', function (Blueprint $table) {
            $table->string('note_channel', 16)->nullable()->after('body');
            $table->string('note_channel_other', 100)->nullable()->after('note_channel');
            $table->string('note_party', 16)->nullable()->after('note_channel_other');
            $table->string('note_party_name', 255)->nullable()->after('note_party');
        });
    }

    public function down(): void
    {
        Schema::table('closed_training_case_events', function (Blueprint $table) {
            $table->dropColumn([
                'note_channel',
                'note_channel_other',
                'note_party',
                'note_party_name',
            ]);
        });
    }
};
