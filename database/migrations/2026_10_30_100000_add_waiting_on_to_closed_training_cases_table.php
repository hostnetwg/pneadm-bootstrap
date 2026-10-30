<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->string('waiting_on', 30)->nullable()->default('pne')->after('next_action_at');
            $table->index('waiting_on', 'ct_cases_waiting_on_idx');
        });

        DB::table('closed_training_cases')
            ->whereNotIn('status', ['cancelled', 'closed'])
            ->whereNull('waiting_on')
            ->update(['waiting_on' => 'pne']);
    }

    public function down(): void
    {
        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->dropIndex('ct_cases_waiting_on_idx');
            $table->dropColumn('waiting_on');
        });
    }
};
