<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->timestamp('contract_concluded_at')->nullable()->after('order_date');
            $table->string('early_performance_kind', 64)->nullable()->after('early_performance_scope');
            $table->text('early_performance_statement_text')->nullable()->after('early_performance_statement_version');
            $table->timestamp('legal_confirmation_failed_at')->nullable()->after('legal_confirmation_sent_at');
            $table->string('legal_confirmation_error', 1000)->nullable()->after('legal_confirmation_failed_at');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('is_extension')->default(false)->after('satisfaction_guarantee_days');
            $table->timestamp('previous_access_expires_at')->nullable()->after('is_extension');
            $table->json('commercial_terms')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('form_orders', function (Blueprint $table) {
            $table->dropColumn([
                'contract_concluded_at',
                'early_performance_kind',
                'early_performance_statement_text',
                'legal_confirmation_failed_at',
                'legal_confirmation_error',
            ]);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'is_extension',
                'previous_access_expires_at',
                'commercial_terms',
            ]);
        });
    }
};
