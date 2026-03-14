<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabela mapuje znormalizowany adres e-mail uczestnika na unikalny token (64 znaki)
     * używany w linkach do pobierania zaświadczeń na pnedu.pl. Jeden token na e-mail –
     * ten sam link pokazuje listę wszystkich szkoleń danego uczestnika i pozwala pobrać
     * zaświadczenia po aktywacji przez admina.
     */
    public function up(): void
    {
        Schema::create('participant_download_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('email_normalized', 191)->unique()->comment('E-mail znormalizowany: trim + lowercase');
            $table->string('token', 64)->unique()->comment('Unikalny token do linku pobierania zaświadczeń (64 znaki)');
            $table->timestamps();
        });

        Schema::table('participant_download_tokens', function (Blueprint $table) {
            $table->comment('Mapowanie e-mail → token do linków pobierania zaświadczeń (jeden token na e-mail, używane na pnedu.pl)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('participant_download_tokens');
    }
};
