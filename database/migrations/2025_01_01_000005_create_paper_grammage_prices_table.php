<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (grammage, supplier) — this is what powers the supplier
     * price comparison in the pricing tools. A grammage can have several
     * rows (one per supplier quoting it); the calculators pick the cheapest
     * by default and let staff pick a different one.
     */
    public function up(): void
    {
        Schema::create('paper_grammage_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_grammage_id')->constrained('paper_grammages')->cascadeOnDelete();
            $table->foreignId('paper_supplier_id')->constrained('paper_suppliers')->cascadeOnDelete();
            $table->decimal('price_per_ton_egp', 10, 2);
            // Prices move often — keep an explicit "as of" timestamp separate
            // from updated_at, since updated_at can be touched by unrelated
            // edits (e.g. a supplier name correction).
            $table->timestamp('price_as_of')->useCurrent();
            $table->timestamps();

            $table->unique(['paper_grammage_id', 'paper_supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paper_grammage_prices');
    }
};
