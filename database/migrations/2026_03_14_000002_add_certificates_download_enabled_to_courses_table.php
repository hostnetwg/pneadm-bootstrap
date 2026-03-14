<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Flaga włączana przez admina – po ustawieniu uczestnicy mogą pobierać
     * zaświadczenia dla tego kursu przez link z tokenem (pnedu.pl/certificates/{token}).
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('certificates_download_enabled')->default(false)->after('certificate_template_id')
                ->comment('Czy uczestnicy mogą pobierać zaświadczenia przez link z tokenem (pnedu.pl)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('certificates_download_enabled');
        });
    }
};
