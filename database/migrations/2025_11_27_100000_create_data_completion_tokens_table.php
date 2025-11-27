<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('data_completion_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->index();
            $table->string('token', 64)->unique();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            // Indeksy dla wydajności
            $table->index(['email', 'used_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('data_completion_tokens');
    }
};

