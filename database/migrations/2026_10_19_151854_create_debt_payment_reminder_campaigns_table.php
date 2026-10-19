<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_payment_reminder_campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('audience', 16); // standard|vip
            $table->string('status', 32)->default('pending');
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('send_email')->default(true);
            $table->boolean('send_sms')->default(false);
            $table->string('email_template', 32)->default('reminder');
            $table->string('sms_template', 32)->default('reminder');
            $table->boolean('check_ifirma')->default(true);
            $table->boolean('attach_ifirma_pdf')->default(true);
            $table->boolean('strip_sms_diacritics')->default(true);
            $table->boolean('sms_companion_email')->default(true);
            $table->string('batch_id', 36)->nullable()->index();
            $table->unsignedInteger('selected_count')->default(0);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'audience']);
        });

        Schema::create('debt_payment_reminder_campaign_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')
                ->constrained('debt_payment_reminder_campaigns')
                ->cascadeOnDelete();
            $table->foreignId('debt_case_id')->constrained('debt_cases')->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('skip_reason', 64)->nullable();
            $table->string('email_outcome', 32)->nullable();
            $table->string('sms_outcome', 32)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'debt_case_id'], 'dpr_items_campaign_case_unique');
            $table->index(['status', 'debt_case_id'], 'dpr_items_status_case_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_payment_reminder_campaign_items');
        Schema::dropIfExists('debt_payment_reminder_campaigns');
    }
};
