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
        Schema::create('marketing_source_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // np. "Facebook", "Email", "Website"
            $table->string('slug')->unique(); // np. "facebook", "email", "website"
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#6c757d'); // hex color for UI
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index('slug');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_source_types');
    }
};
