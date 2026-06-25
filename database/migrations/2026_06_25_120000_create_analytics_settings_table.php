<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Runtime override trybów analityki pne_analytics (panel: Analityka -> Ustawienia).
     *
     * Tabela aplikacyjna/backoffice w bazie pneadm (NIE w connection analytics / pne_analytics).
     * Odczyt także z aplikacji pnedu (przez connection 'pneadm'), wzorzec jak payment_display_options.
     *
     * Znaczenie:
     * - enabled_override = null  -> użyj .env/config (ANALYTICS_ENABLED),
     * - enabled_override = 0/1   -> runtime override, ale .env ANALYTICS_ENABLED=false pozostaje hard kill switch,
     * - default_mode_override = null -> użyj .env/config (ANALYTICS_DEFAULT_MODE),
     * - default_mode_override z: off|aggregate_only|light|standard|full.
     */
    public function up(): void
    {
        Schema::create('analytics_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled_override')->nullable()->comment('null = użyj .env/config; 0/1 = runtime override (z respektem hard kill switch .env)');
            $table->string('default_mode_override', 32)->nullable()->comment('null = użyj .env/config; off|aggregate_only|light|standard|full');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('users.id administratora, który ostatnio zmienił ustawienia');
            $table->timestamps();
        });

        DB::table('analytics_settings')->insert([
            'id' => 1,
            'enabled_override' => null,
            'default_mode_override' => null,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_settings');
    }
};
