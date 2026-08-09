<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('survey_templates')) {
            Schema::create('survey_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('survey_template_questions')) {
            Schema::create('survey_template_questions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('survey_template_id')->constrained('survey_templates')->cascadeOnDelete();
                $table->string('question_key', 100);
                $table->string('question_text');
                $table->string('question_type', 50);
                $table->unsignedInteger('question_order')->default(0);
                $table->json('options')->nullable();
                $table->boolean('is_required')->default(true);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['survey_template_id', 'question_key'], 'stq_template_key_unique');
                $table->index(['survey_template_id', 'question_order'], 'stq_template_order_idx');
            });
        } else {
            // Naprawa po częściowym failu migracji (zbyt długi domyślny indeks MySQL)
            $indexes = collect(DB::select('SHOW INDEX FROM survey_template_questions'))->pluck('Key_name')->unique();
            if (! $indexes->contains('stq_template_order_idx')) {
                Schema::table('survey_template_questions', function (Blueprint $table) {
                    $table->index(['survey_template_id', 'question_order'], 'stq_template_order_idx');
                });
            }
            if (! $indexes->contains('stq_template_key_unique')) {
                Schema::table('survey_template_questions', function (Blueprint $table) {
                    $table->unique(['survey_template_id', 'question_key'], 'stq_template_key_unique');
                });
            }
        }

        if (! Schema::hasTable('survey_settings')) {
            Schema::create('survey_settings', function (Blueprint $table) {
                $table->id();
                // manual | auto — jak domyślnie ustawiać okno otwarcia nowych ankiet
                $table->string('open_mode', 20)->default('manual');
                $table->unsignedInteger('auto_open_offset_hours')->default(0);
                $table->unsignedInteger('auto_close_after_days')->nullable();
                // native | external
                $table->string('default_channel', 20)->default('native');
                $table->boolean('default_is_anonymous')->default(true);
                $table->foreignId('default_template_id')->nullable()->constrained('survey_templates')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('survey_testimonials')) {
            Schema::create('survey_testimonials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('survey_id')->nullable()->constrained('surveys')->nullOnDelete();
                $table->foreignId('survey_response_id')->nullable()->constrained('survey_responses')->nullOnDelete();
                $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
                $table->string('author_name');
                $table->string('author_role')->nullable();
                $table->string('author_city')->nullable();
                $table->text('quote');
                $table->unsignedTinyInteger('rating')->nullable();
                $table->boolean('publish_consent')->default(false);
                $table->boolean('is_published')->default(false);
                $table->timestamp('published_at')->nullable();
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();

                $table->index(['is_published', 'display_order']);
            });
        }

        Schema::table('course_survey_links', function (Blueprint $table) {
            if (! Schema::hasColumn('course_survey_links', 'channel')) {
                $table->string('channel', 20)->default('external')->after('provider');
            }
            if (! Schema::hasColumn('course_survey_links', 'is_anonymous')) {
                $table->boolean('is_anonymous')->default(true)->after('channel');
            }
            if (! Schema::hasColumn('course_survey_links', 'survey_id')) {
                $table->foreignId('survey_id')->nullable()->after('course_id')->constrained('surveys')->nullOnDelete();
            }
            if (! Schema::hasColumn('course_survey_links', 'survey_template_id')) {
                $table->foreignId('survey_template_id')->nullable()->after('survey_id')->constrained('survey_templates')->nullOnDelete();
            }
        });

        // URL opcjonalny dla kanału native
        if (Schema::hasColumn('course_survey_links', 'url')) {
            DB::statement('ALTER TABLE course_survey_links MODIFY url VARCHAR(2048) NULL');
        }

        Schema::table('surveys', function (Blueprint $table) {
            if (! Schema::hasColumn('surveys', 'is_anonymous')) {
                $table->boolean('is_anonymous')->default(true)->after('source');
            }
            if (! Schema::hasColumn('surveys', 'survey_template_id')) {
                $table->foreignId('survey_template_id')->nullable()->after('instructor_id')->constrained('survey_templates')->nullOnDelete();
            }
            if (! Schema::hasColumn('surveys', 'channel')) {
                $table->string('channel', 20)->nullable()->after('source');
            }
        });

        Schema::table('survey_responses', function (Blueprint $table) {
            if (! Schema::hasColumn('survey_responses', 'participant_id')) {
                $table->unsignedBigInteger('participant_id')->nullable()->after('respondent_id');
                $table->foreign('participant_id')->references('id')->on('participants')->nullOnDelete();
            }
            if (! Schema::hasColumn('survey_responses', 'respondent_email')) {
                $table->string('respondent_email')->nullable()->after('participant_id');
            }
        });

        // Rozszerzenie typów pytań (MySQL enum)
        try {
            DB::statement("ALTER TABLE survey_questions MODIFY question_type ENUM('rating','text','multiple_choice','single_choice','date','time','testimonial','availability') NOT NULL");
        } catch (\Throwable) {
            // SQLite / środowiska bez enum — pomiń
        }

        $this->seedDefaultTemplateAndSettings();
    }

    public function down(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            if (Schema::hasColumn('survey_responses', 'participant_id')) {
                $table->dropForeign(['participant_id']);
                $table->dropColumn('participant_id');
            }
            if (Schema::hasColumn('survey_responses', 'respondent_email')) {
                $table->dropColumn('respondent_email');
            }
        });

        Schema::table('surveys', function (Blueprint $table) {
            if (Schema::hasColumn('surveys', 'survey_template_id')) {
                $table->dropForeign(['survey_template_id']);
                $table->dropColumn('survey_template_id');
            }
            if (Schema::hasColumn('surveys', 'is_anonymous')) {
                $table->dropColumn('is_anonymous');
            }
            if (Schema::hasColumn('surveys', 'channel')) {
                $table->dropColumn('channel');
            }
        });

        Schema::table('course_survey_links', function (Blueprint $table) {
            if (Schema::hasColumn('course_survey_links', 'survey_template_id')) {
                $table->dropForeign(['survey_template_id']);
                $table->dropColumn('survey_template_id');
            }
            if (Schema::hasColumn('course_survey_links', 'survey_id')) {
                $table->dropForeign(['survey_id']);
                $table->dropColumn('survey_id');
            }
            if (Schema::hasColumn('course_survey_links', 'is_anonymous')) {
                $table->dropColumn('is_anonymous');
            }
            if (Schema::hasColumn('course_survey_links', 'channel')) {
                $table->dropColumn('channel');
            }
        });

        Schema::dropIfExists('survey_testimonials');
        Schema::dropIfExists('survey_settings');
        Schema::dropIfExists('survey_template_questions');
        Schema::dropIfExists('survey_templates');
    }

    private function seedDefaultTemplateAndSettings(): void
    {
        if (DB::table('survey_templates')->where('slug', 'ewaluacja-szkolenia')->exists()) {
            return;
        }

        $templateId = DB::table('survey_templates')->insertGetId([
            'name' => 'Ewaluacja szkolenia (standard)',
            'slug' => 'ewaluacja-szkolenia',
            'description' => 'Domyślny kwestionariusz po szkoleniu (odpowiednik dotychczasowego Formularza Google) + blok rekomendacji na pnedu.pl.',
            'is_active' => true,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $days = ['poniedziałek', 'wtorek', 'środa', 'czwartek', 'piątek', 'sobota', 'niedziela'];
        $slots = [
            'Rano (8:00–11:00)',
            'Południe (11:00–14:00)',
            'Popołudnie (14:00–17:00)',
            'Wieczór (17:00–20:00)',
        ];

        $questions = [
            [
                'question_key' => 'rating_cele',
                'question_text' => '1. Ocena szkolenia [Czy prowadzący przedstawił cele szkolenia precyzyjnie i zrozumiale?]',
                'question_type' => 'rating',
                'question_order' => 10,
                'options' => null,
                'is_required' => true,
            ],
            [
                'question_key' => 'rating_wiedza',
                'question_text' => '1. Ocena szkolenia [Czy prowadzący szkolenie posiadał odpowiednią wiedzę i przygotowanie merytoryczne?]',
                'question_type' => 'rating',
                'question_order' => 20,
                'options' => null,
                'is_required' => true,
            ],
            [
                'question_key' => 'rating_przekaz',
                'question_text' => '1. Ocena szkolenia [Czy treść szkolenia była przekazana w zrozumiały i przystępny sposób?]',
                'question_type' => 'rating',
                'question_order' => 30,
                'options' => null,
                'is_required' => true,
            ],
            [
                'question_key' => 'rating_oczekiwania',
                'question_text' => '1. Ocena szkolenia [Czy szkolenie spełniło Pani/Pana oczekiwania?]',
                'question_type' => 'rating',
                'question_order' => 40,
                'options' => null,
                'is_required' => true,
            ],
            [
                'question_key' => 'rating_wiedza_wzrost',
                'question_text' => '1. Ocena szkolenia [Czy dzięki szkoleniu zwiększyła się Pani/Pana wiedza?]',
                'question_type' => 'rating',
                'question_order' => 50,
                'options' => null,
                'is_required' => true,
            ],
            [
                'question_key' => 'rating_polecenie',
                'question_text' => '1. Ocena szkolenia [Czy poleciła(a)by Pani/Pan to szkolenie innym osobom?]',
                'question_type' => 'rating',
                'question_order' => 60,
                'options' => null,
                'is_required' => true,
            ],
            [
                'question_key' => 'plusy',
                'question_text' => 'Jakie zauważa Pan/Pani plusy w szkoleniu?',
                'question_type' => 'text',
                'question_order' => 70,
                'options' => null,
                'is_required' => false,
            ],
            [
                'question_key' => 'zmiany',
                'question_text' => 'Co zmieniłaby/zmieniłby, dodała/dodał Pani/Pan w szkoleniu?',
                'question_type' => 'text',
                'question_order' => 80,
                'options' => null,
                'is_required' => false,
            ],
            [
                'question_key' => 'zainteresowania',
                'question_text' => 'Szkoleniami z jakiego zakresu jest Pani/Pan zainteresowana/y?',
                'question_type' => 'text',
                'question_order' => 90,
                'options' => null,
                'is_required' => false,
            ],
            [
                'question_key' => 'zrodlo',
                'question_text' => 'O szkoleniu dowiedziałam/em się z',
                'question_type' => 'single_choice',
                'question_order' => 100,
                'options' => ['wiadomości e-mail z ofertą', 'portalu Facebook', 'Inne'],
                'is_required' => false,
            ],
            [
                'question_key' => 'terminy',
                'question_text' => 'Które dni tygodnia i godziny rozpoczęcia są dla Ciebie najbardziej dogodne na udział w szkoleniach online?',
                'question_type' => 'availability',
                'question_order' => 110,
                'options' => ['days' => $days, 'slots' => $slots],
                'is_required' => false,
            ],
            [
                'question_key' => 'uwagi',
                'question_text' => 'Inne uwagi i sugestie',
                'question_type' => 'text',
                'question_order' => 120,
                'options' => null,
                'is_required' => false,
            ],
            [
                'question_key' => 'rekomendacja',
                'question_text' => 'Rekomendacja / opinia do ewentualnej publikacji na pnedu.pl',
                'question_type' => 'testimonial',
                'question_order' => 130,
                'options' => null,
                'is_required' => false,
                'meta' => ['collect_role' => true, 'collect_city' => true, 'collect_rating' => true],
            ],
        ];

        foreach ($questions as $q) {
            DB::table('survey_template_questions')->insert([
                'survey_template_id' => $templateId,
                'question_key' => $q['question_key'],
                'question_text' => $q['question_text'],
                'question_type' => $q['question_type'],
                'question_order' => $q['question_order'],
                'options' => isset($q['options']) ? json_encode($q['options'], JSON_UNESCAPED_UNICODE) : null,
                'is_required' => $q['is_required'],
                'meta' => isset($q['meta']) ? json_encode($q['meta'], JSON_UNESCAPED_UNICODE) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (DB::table('survey_settings')->where('id', 1)->doesntExist()) {
            DB::table('survey_settings')->insert([
                'id' => 1,
                'open_mode' => 'manual',
                'auto_open_offset_hours' => 0,
                'auto_close_after_days' => 14,
                'default_channel' => 'native',
                'default_is_anonymous' => true,
                'default_template_id' => $templateId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Istniejące placeholdery ze strony głównej — jako opublikowane rekomendacje startowe
        if (DB::table('survey_testimonials')->count() === 0) {
            DB::table('survey_testimonials')->insert([
                [
                    'survey_id' => null,
                    'survey_response_id' => null,
                    'course_id' => null,
                    'author_name' => 'Anna Nowak',
                    'author_role' => 'Nauczycielka',
                    'author_city' => 'Kraków',
                    'quote' => 'Szkolenie było bardzo profesjonalne i konkretne. Materiały świetnie przygotowane, a prowadzący wspaniale tłumaczył.',
                    'rating' => 5,
                    'publish_consent' => true,
                    'is_published' => true,
                    'published_at' => now(),
                    'display_order' => 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'survey_id' => null,
                    'survey_response_id' => null,
                    'course_id' => null,
                    'author_name' => 'Piotr Zieliński',
                    'author_role' => 'Wicedyrektor',
                    'author_city' => 'Wrocław',
                    'quote' => 'Dzięki szkoleniu z AI potrafię szybciej przygotować materiały i lepiej reagować na potrzeby uczniów.',
                    'rating' => 5,
                    'publish_consent' => true,
                    'is_published' => true,
                    'published_at' => now(),
                    'display_order' => 20,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }
};
