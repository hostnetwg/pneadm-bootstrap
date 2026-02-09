<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabela w bazie pneadm – logowanie webhooków z PayU i PayNow
     */
    public function up(): void
    {
        // Sprawdź czy tabela już istnieje - jeśli tak, pomiń migrację
        if (Schema::hasTable('payment_webhook_logs')) {
            return;
        }

        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('online_payment_order_id');
            $table->string('payment_gateway', 32); // payu, paynow
            $table->string('gateway_payment_id')->nullable(); // payu_order_id lub paymentId z PayNow
            $table->string('external_id')->nullable(); // ident zamówienia (extOrderId/externalId)
            $table->string('status', 64)->nullable(); // status z webhooka (COMPLETED, CONFIRMED, PENDING, etc.)
            $table->string('status_mapped', 32)->nullable(); // zmapowany status (paid, cancelled, pending)
            $table->json('payload')->nullable(); // pełny payload webhooka
            $table->string('signature', 512)->nullable(); // podpis webhooka (jeśli dostępny)
            $table->boolean('signature_valid')->nullable(); // czy podpis był poprawny
            $table->string('ip_address', 45)->nullable(); // IP z którego przyszedł webhook
            $table->text('error_message')->nullable(); // błąd jeśli wystąpił
            $table->timestamps();

            $table->index(['online_payment_order_id']);
            $table->index(['payment_gateway', 'gateway_payment_id']);
            $table->index(['external_id']);
            $table->index(['status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_logs');
    }
};
