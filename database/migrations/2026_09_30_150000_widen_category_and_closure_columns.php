<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Native ENUMs → strings (validated by the PHP enums), so new paper categories
 * and die closures (micro flute, pizza front lock) never need an ALTER … MODIFY.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paper_types', function (Blueprint $table) {
            $table->string('category', 30)->change();
        });
        Schema::table('dies', function (Blueprint $table) {
            $table->string('closure_type', 30)->change();
        });
    }

    public function down(): void
    {
        // Irreversible without data loss once the new values are used; strings stay.
    }
};
