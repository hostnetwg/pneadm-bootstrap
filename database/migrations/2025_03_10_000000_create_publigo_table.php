<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('publigo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_old')->nullable()->index(); // dodajemy pole id_old              
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->enum('type', ['online', 'offline']);
            $table->enum('category', ['open', 'closed']);
            $table->unsignedBigInteger('instructor_id')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('certificate_format')->nullable();
            $table->string('platform')->nullable();
            $table->string('meeting_link')->nullable();
            $table->string('meeting_password')->nullable();
            $table->string('location_name')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('post_office')->nullable();
            $table->string('address')->nullable();
            $table->string('country')->default('Polska');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('publigo');
    }
};
