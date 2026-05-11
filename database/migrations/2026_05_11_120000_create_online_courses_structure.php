<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_courses', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('offer_description_html')->nullable();
            $table->foreignId('instructor_id')->nullable()->constrained('instructors')->nullOnDelete();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('visible_in_dashboard')->default(true)->comment('Czy wyświetlać zapisanemu użytkownikowi na pnedu (np. przygotowanie treści)');
            $table->text('internal_notes')->nullable();
            $table->string('legacy_publigo_product_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('online_course_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_course_id')->constrained('online_courses')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['online_course_id', 'sort_order'], 'idx_oc_modules_course_ord');
        });

        Schema::create('online_course_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_course_module_id')->constrained('online_course_modules')->cascadeOnDelete();
            $table->string('title');
            $table->mediumText('body_html')->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['online_course_module_id', 'sort_order'], 'idx_oc_lessons_mod_ord');
        });

        Schema::create('online_course_lesson_embeds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('online_course_lesson_id');
            $table->foreign('online_course_lesson_id', 'fk_oc_embed_lesson')
                ->references('id')->on('online_course_lessons')->cascadeOnDelete();
            $table->string('video_url');
            $table->enum('platform', ['youtube', 'vimeo', 'other'])->default('youtube');
            $table->string('title')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['online_course_lesson_id', 'sort_order'], 'idx_oc_emb_less_ord');
        });

        Schema::create('online_course_lesson_resource_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('online_course_lesson_id');
            $table->foreign('online_course_lesson_id', 'fk_oc_rlnk_lesson')
                ->references('id')->on('online_course_lessons')->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('title')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['online_course_lesson_id', 'sort_order'], 'idx_oc_res_less_ord');
        });

        Schema::create('online_course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_course_id')->constrained('online_courses')->cascadeOnDelete();
            $table->string('email');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->timestamp('access_expires_at')->nullable()->comment('UTC; null = bezterminowy dostęp');
            $table->string('access_source')->default('manual')->comment('manual, publigo_migration, …');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['online_course_id', 'email'], 'uq_oc_enroll_course_mail');
            $table->index('email', 'idx_oc_enroll_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_course_enrollments');
        Schema::dropIfExists('online_course_lesson_resource_links');
        Schema::dropIfExists('online_course_lesson_embeds');
        Schema::dropIfExists('online_course_lessons');
        Schema::dropIfExists('online_course_modules');
        Schema::dropIfExists('online_courses');
    }
};
