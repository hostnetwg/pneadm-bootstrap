<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trainer_invoices')) {
            return;
        }

        Schema::create('trainer_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('instructor_id');
            $table->string('invoice_number', 64);
            $table->string('ksef_number', 128)->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('payment_status', 16)->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('instructor_id')
                ->references('id')
                ->on('instructors')
                ->onDelete('cascade');

            $table->unique(['instructor_id', 'invoice_number']);
            $table->index(['instructor_id', 'payment_status']);
        });

        Schema::create('trainer_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trainer_invoice_id');
            $table->unsignedBigInteger('course_id');
            $table->decimal('amount_gross', 10, 2);
            $table->decimal('amount_net', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('trainer_invoice_id')
                ->references('id')
                ->on('trainer_invoices')
                ->onDelete('cascade');

            $table->foreign('course_id')
                ->references('id')
                ->on('courses')
                ->onDelete('cascade');

            $table->unique(['trainer_invoice_id', 'course_id']);
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_invoice_items');
        Schema::dropIfExists('trainer_invoices');
    }
};
