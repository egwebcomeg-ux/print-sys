<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Project-management stage tracking for a job (printing, die-cutting,
     * gluing/folding, QC, ready for delivery...). Seed a default stage set
     * per box_shape/box_type when a job is approved; staff move stages
     * forward as production progresses.
     */
    public function up(): void
    {
        Schema::create('job_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->string('name'); // مثال: "طباعة"، "تكسير"، "لصق وتطبيق"، "مراجعة جودة"
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->enum('status', ['pending', 'in_progress', 'done'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_stages');
    }
};
