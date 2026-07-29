<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_offers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 500)->nullable();
            $table->longText('description_html')->nullable();
            $table->text('scope')->nullable();
            $table->string('audience')->nullable();
            $table->enum('price_mode', ['individual', 'fixed'])->default('individual');
            $table->decimal('price_amount', 10, 2)->nullable();
            $table->foreignId('instructor_id')->nullable()->constrained('instructors')->nullOnDelete();
            $table->string('image')->nullable();
            $table->enum('default_course_category', ['open', 'closed'])->default('closed');
            $table->boolean('is_active')->default(true);
            $table->boolean('show_on_pnedu')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'show_on_pnedu', 'sort_order'], 'idx_training_offers_public');
            $table->index('instructor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_offers');
    }
};
