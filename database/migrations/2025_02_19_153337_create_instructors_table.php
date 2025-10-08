<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInstructorsTable extends Migration
{
    public function up()
    {
        Schema::create('instructors', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();          
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->text('bio')->nullable();
            $table->string('photo')->nullable();
            $table->string('signature')->nullable(); // Ścieżka do podpisu instruktora            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('instructors');
    }
}

