<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('data_completion_requests', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->index();
            $table->unsignedBigInteger('course_id')->nullable()->index();
            $table->timestamp('sent_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Klucz obcy do courses (opcjonalny - może być NULL dla wysyłki globalnej)
            $table->foreign('course_id')
                  ->references('id')
                  ->on('courses')
                  ->onDelete('set null');

            // Indeksy dla wydajności
            $table->index(['email', 'completed_at']);
            $table->index('sent_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('data_completion_requests');
    }
};

