<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_display_options', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_display_options', 'developer_online_payment_test_enabled')) {
                $table->boolean('developer_online_payment_test_enabled')
                    ->default(false)
                    ->after('order_form_auto_fill_test_data_developers_only');
            }

            if (! Schema::hasColumn('payment_display_options', 'developer_online_payment_sandbox_gateway')) {
                $table->boolean('developer_online_payment_sandbox_gateway')
                    ->default(true)
                    ->after('developer_online_payment_test_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_display_options', function (Blueprint $table) {
            if (Schema::hasColumn('payment_display_options', 'developer_online_payment_sandbox_gateway')) {
                $table->dropColumn('developer_online_payment_sandbox_gateway');
            }

            if (Schema::hasColumn('payment_display_options', 'developer_online_payment_test_enabled')) {
                $table->dropColumn('developer_online_payment_test_enabled');
            }
        });
    }
};
