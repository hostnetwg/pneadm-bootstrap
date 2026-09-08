<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_orders', function (Blueprint $table): void {
            $table->string('customer_profile', 32)->nullable()->after('order_form_variant');
            $table->string('terms_version', 32)->nullable()->after('customer_profile');
            $table->char('terms_hash', 64)->nullable()->after('terms_version');
            $table->string('early_performance_scope', 32)->nullable()->after('terms_hash');
            $table->string('early_performance_statement_version', 32)->nullable()->after('early_performance_scope');
            $table->timestamp('early_performance_accepted_at')->nullable()->after('early_performance_statement_version');
            $table->timestamp('legal_confirmation_sent_at')->nullable()->after('early_performance_accepted_at');
        });

        Schema::table('online_payment_orders', function (Blueprint $table): void {
            $table->string('customer_profile', 32)->nullable()->after('buyer_type');
            $table->string('terms_version', 32)->nullable()->after('customer_profile');
            $table->char('terms_hash', 64)->nullable()->after('terms_version');
            $table->string('early_performance_scope', 32)->nullable()->after('terms_hash');
            $table->string('early_performance_statement_version', 32)->nullable()->after('early_performance_scope');
            $table->timestamp('early_performance_accepted_at')->nullable()->after('early_performance_statement_version');
            $table->timestamp('legal_confirmation_sent_at')->nullable()->after('early_performance_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('online_payment_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'customer_profile',
                'terms_version',
                'terms_hash',
                'early_performance_scope',
                'early_performance_statement_version',
                'early_performance_accepted_at',
                'legal_confirmation_sent_at',
            ]);
        });

        Schema::table('form_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'customer_profile',
                'terms_version',
                'terms_hash',
                'early_performance_scope',
                'early_performance_statement_version',
                'early_performance_accepted_at',
                'legal_confirmation_sent_at',
            ]);
        });
    }
};
