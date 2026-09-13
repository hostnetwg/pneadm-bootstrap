<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_offers', function (Blueprint $table) {
            $table->unsignedSmallInteger('satisfaction_guarantee_days')
                ->default(30)
                ->after('allow_paynow')
                ->comment('0 = brak gwarancji handlowej; 30 = klasyka kursów online');
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->timestamp('access_starts_at')
                ->nullable()
                ->after('access_policy')
                ->comment('UTC; puste = dostęp od nadania');
            $table->text('access_note')
                ->nullable()
                ->after('access_starts_at');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('access_starts_at')->nullable()->after('access_policy');
            $table->text('access_note')->nullable()->after('access_starts_at');
            $table->unsignedSmallInteger('satisfaction_guarantee_days')->nullable()->after('access_note');
        });

        Schema::table('online_course_enrollments', function (Blueprint $table) {
            $table->timestamp('access_starts_at')->nullable()->after('access_expires_at');
            $table->text('access_note')->nullable()->after('access_starts_at');
        });

        DB::table('product_offers')
            ->whereNull('satisfaction_guarantee_days')
            ->update(['satisfaction_guarantee_days' => 30]);
    }

    public function down(): void
    {
        Schema::table('online_course_enrollments', function (Blueprint $table) {
            $table->dropColumn(['access_starts_at', 'access_note']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['access_starts_at', 'access_note', 'satisfaction_guarantee_days']);
        });
        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropColumn(['access_starts_at', 'access_note']);
        });
        Schema::table('product_offers', function (Blueprint $table) {
            $table->dropColumn('satisfaction_guarantee_days');
        });
    }
};
