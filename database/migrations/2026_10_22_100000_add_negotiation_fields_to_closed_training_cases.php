<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->foreignId('instructor_id')
                ->nullable()
                ->after('assignee_user_id')
                ->constrained('instructors')
                ->nullOnDelete();
            $table->decimal('client_price', 10, 2)->nullable()->after('message');
            $table->timestamp('client_price_confirmed_at')->nullable()->after('client_price');
            $table->decimal('instructor_fee', 10, 2)->nullable()->after('client_price_confirmed_at');
            $table->timestamp('instructor_fee_confirmed_at')->nullable()->after('instructor_fee');
            $table->dateTime('start_date')->nullable()->after('instructor_fee_confirmed_at');
            $table->dateTime('end_date')->nullable()->after('start_date');
            $table->boolean('schedule_time_known')->default(false)->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('closed_training_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instructor_id');
            $table->dropColumn([
                'client_price',
                'client_price_confirmed_at',
                'instructor_fee',
                'instructor_fee_confirmed_at',
                'start_date',
                'end_date',
                'schedule_time_known',
            ]);
        });
    }
};
