<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paper line items for manual jobs (ManualJobCostingCalculator): each
     * row is one paper + sheet size + sheet count. Weight and cost are
     * recomputed server-side from the DB price when the job is saved.
     */
    public function up(): void
    {
        Schema::create('job_paper_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->string('label'); // مثال: "غلاف"، "متن الكتيب"
            $table->foreignId('paper_grammage_id')->constrained('paper_grammages')->restrictOnDelete();
            $table->foreignId('paper_grammage_price_id')->nullable()->constrained('paper_grammage_prices')->nullOnDelete();
            $table->decimal('sheet_width_cm', 6, 2);
            $table->decimal('sheet_height_cm', 6, 2);
            $table->unsignedInteger('sheets_count');
            $table->decimal('weight_kg', 10, 3);
            $table->decimal('cost_egp', 12, 2);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_paper_items');
    }
};
