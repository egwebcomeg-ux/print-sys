<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Internal print lines AND external subcontractor presses live in the
     * same table (is_internal flags which). Capability + capacity fields
     * back the routing decision-support screen — routing itself stays a
     * manual choice, this table just powers the filtered list staff see.
     */
    public function up(): void
    {
        Schema::create('presses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_internal')->default(false);
            $table->unsignedTinyInteger('max_colors');
            // JSON arrays so a press can support several sizes/paper types
            // without extra pivot tables. Kept simple on purpose — revisit
            // if capability data grows complex enough to need real joins.
            $table->json('supported_cut_fractions'); // e.g. ["1/2","1/4"]
            $table->json('supported_paper_categories')->nullable(); // null/empty = accepts all
            // Manually-updated capacity indicator (see PressRoutingSelector's
            // notes) — replace with a real scheduling feed later if one exists.
            $table->unsignedSmallInteger('current_backlog_days')->default(0);
            $table->string('contact_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presses');
    }
};
