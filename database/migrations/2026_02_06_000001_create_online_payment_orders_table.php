<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabela zamówień płatności online (PayU, Paynow) – widoczne w panelu adm.pnedu.pl
     */
    public function up(): void
    {
        // Sprawdź czy tabela już istnieje - jeśli tak, pomiń migrację
        if (Schema::hasTable('online_payment_orders')) {
            return;
        }

        Schema::create('online_payment_orders', function (Blueprint $table) {
            $table->id();
            $table->string('ident', 64)->unique();
            $table->unsignedBigInteger('course_id');
            $table->string('payment_gateway', 32)->default('payu');
            $table->string('payu_order_id')->nullable();
            $table->string('status', 32)->default('pending'); // pending, created, paid, cancelled, failed
            $table->decimal('total_amount', 10, 2);
            $table->string('currency', 3)->default('PLN');

            // Dane zamawiającego (z formularza)
            $table->string('buyer_type', 32); // person, company, organisation
            $table->string('email');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->text('order_comment')->nullable();

            // Dane adresowe – JSON (różne dla person/company/organisation)
            $table->json('address_data')->nullable();

            // Raw payload z formularza (do faktury, rejestracji uczestnika)
            $table->json('form_data')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['ident', 'status']);
            $table->index(['payu_order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('online_payment_orders');
    }
};
