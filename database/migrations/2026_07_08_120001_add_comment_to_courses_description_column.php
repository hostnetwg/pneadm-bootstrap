<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (Schema::getConnection()->getDriverName() !== 'mysql') {
      return;
    }

    if (! Schema::hasColumn('courses', 'description')) {
      return;
    }

    $comment = 'Zakres szkolenia / Zagadnienia';
    DB::statement('ALTER TABLE courses MODIFY COLUMN description TEXT NULL COMMENT '.$this->quote($comment));
  }

  public function down(): void
  {
    if (Schema::getConnection()->getDriverName() !== 'mysql') {
      return;
    }

    if (! Schema::hasColumn('courses', 'description')) {
      return;
    }

    DB::statement('ALTER TABLE courses MODIFY COLUMN description TEXT NULL');
  }

  private function quote(string $value): string
  {
    return DB::getPdo()->quote($value);
  }
};
