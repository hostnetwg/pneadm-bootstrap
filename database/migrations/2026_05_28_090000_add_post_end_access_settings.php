<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_display_options', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_display_options', 'default_post_end_access_duration_value')) {
                $table->unsignedInteger('default_post_end_access_duration_value')
                    ->default(2)
                    ->after('order_form_auto_fill_test_data')
                    ->comment('Domyślny okres dostępu po zakończeniu szkolenia');
            }

            if (! Schema::hasColumn('payment_display_options', 'default_post_end_access_duration_unit')) {
                $table->string('default_post_end_access_duration_unit', 16)
                    ->default('months')
                    ->after('default_post_end_access_duration_value')
                    ->comment('Jednostka domyślnego okresu dostępu po zakończeniu szkolenia');
            }
        });

        Schema::table('courses', function (Blueprint $table) {
            if (! Schema::hasColumn('courses', 'post_end_access_duration_value')) {
                $table->unsignedInteger('post_end_access_duration_value')
                    ->nullable()
                    ->after('access_notes')
                    ->comment('Nadpisanie okresu dostępu po zakończeniu szkolenia; null = ustawienie globalne');
            }

            if (! Schema::hasColumn('courses', 'post_end_access_duration_unit')) {
                $table->string('post_end_access_duration_unit', 16)
                    ->nullable()
                    ->after('post_end_access_duration_value')
                    ->comment('Jednostka nadpisania okresu dostępu po zakończeniu szkolenia');
            }
        });

        Schema::table('course_price_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('course_price_variants', 'availability_after_course_end')) {
                $table->string('availability_after_course_end', 32)
                    ->default('always')
                    ->after('access_duration_unit')
                    ->comment('Dostępność wariantu względem daty zakończenia szkolenia: always, hide_after_end, show_after_end');
            }

            if (! Schema::hasColumn('course_price_variants', 'post_end_access_rule')) {
                $table->string('post_end_access_rule', 16)
                    ->default('inherit')
                    ->after('availability_after_course_end')
                    ->comment('Reguła dostępu po zakończeniu: inherit, duration, unlimited');
            }

            if (! Schema::hasColumn('course_price_variants', 'post_end_access_duration_value')) {
                $table->unsignedInteger('post_end_access_duration_value')
                    ->nullable()
                    ->after('post_end_access_rule')
                    ->comment('Okres dostępu po zakończeniu dla wariantu');
            }

            if (! Schema::hasColumn('course_price_variants', 'post_end_access_duration_unit')) {
                $table->string('post_end_access_duration_unit', 16)
                    ->nullable()
                    ->after('post_end_access_duration_value')
                    ->comment('Jednostka okresu dostępu po zakończeniu dla wariantu');
            }
        });

        if (! DB::table('payment_display_options')->where('id', 1)->exists()) {
            DB::table('payment_display_options')->insert([
                'id' => 1,
                'show_pay_publigo' => true,
                'show_pay_online' => true,
                'show_deferred_order' => true,
                'show_order_form' => true,
                'show_order_form_alt' => true,
                'order_form_auto_fill_test_data' => false,
                'default_post_end_access_duration_value' => 2,
                'default_post_end_access_duration_unit' => 'months',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('course_price_variants', function (Blueprint $table) {
            foreach ([
                'availability_after_course_end',
                'post_end_access_rule',
                'post_end_access_duration_value',
                'post_end_access_duration_unit',
            ] as $column) {
                if (Schema::hasColumn('course_price_variants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('courses', function (Blueprint $table) {
            foreach (['post_end_access_duration_value', 'post_end_access_duration_unit'] as $column) {
                if (Schema::hasColumn('courses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('payment_display_options', function (Blueprint $table) {
            foreach (['default_post_end_access_duration_value', 'default_post_end_access_duration_unit'] as $column) {
                if (Schema::hasColumn('payment_display_options', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
