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
            $table->string('closed_outcome', 30)->nullable()->after('status');
            $table->index('closed_outcome', 'ct_cases_closed_outcome_idx');
        });

        DB::table('closed_training_cases')
            ->whereIn('status', ['waiting', 'scheduled'])
            ->update(['status' => 'in_progress']);

        DB::table('closed_training_cases')
            ->whereIn('status', ['delivered', 'closed'])
            ->update([
                'status' => 'closed',
                'closed_outcome' => 'delivered',
                'waiting_on' => null,
            ]);

        DB::table('closed_training_cases')
            ->where('status', 'cancelled')
            ->update([
                'status' => 'closed',
                'closed_outcome' => 'cancelled',
                'waiting_on' => null,
            ]);
    }

    public function down(): void
    {
        DB::table('closed_training_cases')
            ->where('status', 'closed')
            ->where('closed_outcome', 'cancelled')
            ->update(['status' => 'cancelled']);

        DB::table('closed_training_cases')
            ->where('status', 'closed')
            ->where('closed_outcome', 'delivered')
            ->update(['status' => 'delivered']);

        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->dropIndex('ct_cases_closed_outcome_idx');
            $table->dropColumn('closed_outcome');
        });
    }
};
