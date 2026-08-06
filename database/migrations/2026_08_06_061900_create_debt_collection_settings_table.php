<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_collection_settings', function (Blueprint $table) {
            $table->id();
            $table->string('contact_phone', 64)->nullable()->comment('Telefon kontaktowy do stopki e-maili windykacyjnych');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        DB::table('debt_collection_settings')->insert([
            'id' => 1,
            'contact_phone' => null,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_collection_settings');
    }
};
