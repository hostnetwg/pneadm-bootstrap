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
        if (!Schema::hasTable('webhook_logs')) {
            Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('publigo'); // publigo, test, etc.
            $table->string('endpoint'); // /api/publigo/webhook, /api/publigo/webhook-test
            $table->string('method'); // POST, GET, etc.
            $table->text('request_data')->nullable(); // Surowy request
            $table->text('response_data')->nullable(); // Odpowiedź
            $table->integer('status_code')->nullable(); // HTTP status code
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->text('headers')->nullable(); // Headers jako JSON
            $table->text('error_message')->nullable(); // Błąd jeśli wystąpił
            $table->boolean('success')->default(true);
            $table->timestamps();
            
            $table->index(['source', 'created_at']);
            $table->index(['endpoint', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
