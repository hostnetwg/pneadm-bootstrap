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
            $table->timestamp('inquired_at')->nullable()->after('consent_at');
            $table->index('inquired_at', 'ct_cases_inquired_at_idx');
        });

        DB::table('closed_training_cases')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $value = $row->consent_at ?? $row->created_at;
                if ($value === null) {
                    continue;
                }
                DB::table('closed_training_cases')
                    ->where('id', $row->id)
                    ->update(['inquired_at' => $value]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->dropIndex('ct_cases_inquired_at_idx');
            $table->dropColumn('inquired_at');
        });
    }
};
