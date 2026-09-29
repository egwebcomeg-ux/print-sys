<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Free-form extra cost lines a staff member types manually (printing,
     * finishing, shipping...) — same concept as ManualJobCostingCalculator's
     * cost lines, now persisted per job.
     */
    public function up(): void
    {
        Schema::create('job_cost_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->string('label'); // مثال: "طباعة"، "تشطيب"، "شحن"
            $table->decimal('amount_egp', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_cost_lines');
    }
};
