<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_artifact_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('growth_artifact_id')->constrained('growth_artifacts')->cascadeOnDelete();
            $table->foreignId('source_image_id')->nullable()->constrained('growth_artifact_images')->nullOnDelete();
            $table->string('format', 16);
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->string('disk', 32);
            $table->string('path');
            $table->string('mime', 32);
            $table->unsignedInteger('size_bytes');
            $table->text('prompt');
            $table->boolean('include_headline')->default(false);
            $table->string('overlay_headline', 180)->nullable();
            $table->string('overlay_date', 80)->nullable();
            $table->string('source', 16);
            $table->string('provider', 32)->nullable();
            $table->string('model', 64)->nullable();
            $table->string('quality', 16)->nullable();
            $table->string('prompt_version', 64);
            $table->boolean('is_selected')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['growth_artifact_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_artifact_images');
    }
};
