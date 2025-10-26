<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabela form_orders - odpowiednik tabeli zamowienia_FORM z bazy certgen
     * Przechowuje zamówienia złożone przez formularz na stronie
     */
    public function up(): void
    {
        Schema::create('form_orders', function (Blueprint $table) {
            $table->id();
            
            // ===== IDENTYFIKATORY =====
            $table->string('ident', 22)->nullable()->comment('Identyfikator zamówienia (ident)');
            $table->integer('ptw')->nullable()->comment('PTW (PTW)');
            
            // ===== DANE ZAMÓWIENIA =====
            $table->timestamp('order_date')->nullable()->comment('Data złożenia zamówienia (data_zamowienia)');
            
            // ===== PRODUKT / SZKOLENIE =====
            $table->integer('product_id')->nullable()->comment('ID produktu w starym systemie (produkt_id)');
            $table->string('product_name', 500)->nullable()->comment('Nazwa produktu/szkolenia (produkt_nazwa)');
            $table->decimal('product_price', 10, 2)->nullable()->comment('Cena produktu (produkt_cena)');
            $table->text('product_description')->nullable()->comment('Opis produktu (produkt_opis)');
            
            // ===== INTEGRACJA Z PUBLIGO =====
            $table->integer('publigo_product_id')->nullable()->comment('ID produktu w systemie Publigo (idProdPubligo)');
            $table->integer('publigo_price_id')->nullable()->comment('ID ceny w systemie Publigo (price_idProdPubligo)');
            $table->tinyInteger('publigo_sent')->default(0)->comment('Czy wysłano do Publigo: 0=nie, 1=tak (publigo_sent)');
            $table->timestamp('publigo_sent_at')->nullable()->comment('Data i godzina wysłania do Publigo (publigo_sent_at)');
            
            // ===== DANE UCZESTNIKA SZKOLENIA =====
            $table->string('participant_name', 255)->nullable()->comment('Imię i nazwisko uczestnika (konto_imie_nazwisko)');
            $table->string('participant_email', 255)->nullable()->comment('Email uczestnika (konto_email)');
            
            // ===== DANE ZAMAWIAJĄCEGO (kontaktowe) =====
            $table->string('orderer_name', 255)->nullable()->comment('Nazwa zamawiającego (zam_nazwa)');
            $table->string('orderer_address', 255)->nullable()->comment('Adres zamawiającego (zam_adres)');
            $table->string('orderer_postal_code', 10)->nullable()->comment('Kod pocztowy zamawiającego (zam_kod)');
            $table->string('orderer_city', 255)->nullable()->comment('Miejscowość zamawiającego (zam_poczta)');
            $table->string('orderer_phone', 50)->nullable()->comment('Telefon zamawiającego (zam_tel)');
            $table->string('orderer_email', 255)->nullable()->comment('Email zamawiającego (zam_email)');
            
            // ===== DANE NABYWCY (do faktury) =====
            $table->string('buyer_name', 500)->nullable()->comment('Nazwa nabywcy/firmy (nab_nazwa)');
            $table->string('buyer_address', 500)->nullable()->comment('Adres nabywcy (nab_adres)');
            $table->string('buyer_postal_code', 10)->nullable()->comment('Kod pocztowy nabywcy (nab_kod)');
            $table->string('buyer_city', 255)->nullable()->comment('Miejscowość nabywcy (nab_poczta)');
            $table->string('buyer_nip', 20)->nullable()->comment('NIP nabywcy (nab_nip)');
            
            // ===== DANE ODBIORCY =====
            $table->string('recipient_name', 500)->nullable()->comment('Nazwa odbiorcy (odb_nazwa)');
            $table->string('recipient_address', 500)->nullable()->comment('Adres odbiorcy (odb_adres)');
            $table->string('recipient_postal_code', 10)->nullable()->comment('Kod pocztowy odbiorcy (odb_kod)');
            $table->string('recipient_city', 255)->nullable()->comment('Miejscowość odbiorcy (odb_poczta)');
            $table->string('recipient_nip', 20)->nullable()->comment('NIP odbiorcy (odb_nip)');
            
            // ===== DANE DO FAKTURY =====
            $table->string('invoice_number', 100)->nullable()->comment('Numer faktury (nr_fakury)');
            $table->text('invoice_notes')->nullable()->comment('Uwagi do faktury (faktura_uwagi)');
            $table->integer('invoice_payment_delay')->nullable()->comment('Odroczenie płatności w dniach (faktura_odroczenie)');
            
            // ===== STATUS I NOTATKI =====
            $table->tinyInteger('status_completed')->default(0)->comment('Status zakończenia: 0=w trakcie, 1=zakończone (status_zakonczone)');
            $table->text('notes')->nullable()->comment('Notatki wewnętrzne (notatki)');
            $table->timestamp('updated_manually_at')->nullable()->comment('Data ostatniej ręcznej aktualizacji (data_update)');
            
            // ===== DANE TECHNICZNE =====
            $table->string('ip_address', 45)->nullable()->comment('Adres IP użytkownika (ip)');
            $table->string('fb_source', 255)->nullable()->comment('Źródło Facebook/marketing (fb)');
            
            // ===== TIMESTAMPS =====
            $table->timestamps();
            
            // ===== INDEKSY =====
            $table->index('ident', 'idx_ident');
            $table->index('order_date', 'idx_order_date');
            $table->index('participant_email', 'idx_participant_email');
            $table->index('orderer_email', 'idx_orderer_email');
            $table->index('invoice_number', 'idx_invoice_number');
            $table->index('status_completed', 'idx_status_completed');
            $table->index('publigo_sent', 'idx_publigo_sent');
            $table->index('product_id', 'idx_product_id');
            $table->index(['status_completed', 'invoice_number'], 'idx_status_invoice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_orders');
    }
};
