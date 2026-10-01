<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_artifact_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('growth_artifact_id')->constrained('growth_artifacts')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('source', 32);
            $table->unsignedInteger('restored_from_version')->nullable();
            $table->json('payload');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['growth_artifact_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_artifact_versions');
    }
};
