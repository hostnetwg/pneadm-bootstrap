<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nazwa szablonu np. "Szablon Standardowy"
            $table->string('slug')->unique(); // Slug używany jako nazwa pliku np. "default"
            $table->text('description')->nullable(); // Opis szablonu
            $table->json('config')->nullable(); // Konfiguracja bloków i ustawień
            $table->string('preview_image')->nullable(); // Ścieżka do obrazu podglądu
            $table->boolean('is_active')->default(true); // Czy szablon jest aktywny
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_templates');
    }
};
