<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_order_id')->constrained('form_orders')->cascadeOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('open');
            $table->string('priority', 16)->default('normal');
            $table->string('customer_segment', 32)->default('standard');
            $table->unsignedSmallInteger('risk_score')->default(0);
            $table->unsignedSmallInteger('relationship_score')->default(0);
            $table->boolean('manual_vip')->default(false);
            $table->boolean('do_not_auto_dun')->default(false);
            $table->string('vip_reason', 255)->nullable();
            $table->string('invoice_number', 128)->nullable();
            $table->string('ksef_number', 128)->nullable();
            $table->decimal('amount_gross', 10, 2)->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('ifirma_payment_status', 64)->nullable();
            $table->timestamp('ifirma_synced_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->timestamp('last_action_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('summary')->nullable();
            $table->text('closure_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('form_order_id');
            $table->index(['status', 'next_action_at']);
            $table->index(['customer_segment', 'status']);
            $table->index('due_date');
            $table->index('invoice_number');
            $table->index('ksef_number');
        });

        Schema::create('debt_case_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_case_id')->constrained('debt_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type', 32);
            $table->string('channel', 32)->nullable();
            $table->string('outcome', 64)->nullable();
            $table->timestamp('happened_at')->nullable();
            $table->date('promised_payment_at')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['debt_case_id', 'happened_at']);
            $table->index('action_type');
            $table->index('next_action_at');
        });

        Schema::create('debt_case_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_case_id')->constrained('debt_cases')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contact_type', 32);
            $table->string('value', 255);
            $table->string('label', 120)->nullable();
            $table->string('source', 120)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['debt_case_id', 'contact_type']);
            $table->index('value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_case_contacts');
        Schema::dropIfExists('debt_case_actions');
        Schema::dropIfExists('debt_cases');
    }
};
