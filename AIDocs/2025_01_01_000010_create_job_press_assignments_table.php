<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records WHO picked WHICH press for a job, and when — this is the
     * output of PressRoutingSelector's manual "اختار دي" click. Kept as its
     * own table (rather than a column on `jobs`) so a job's press can be
     * re-assigned later with a clear audit trail.
     */
    public function up(): void
    {
        Schema::create('job_press_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignId('press_id')->constrained('presses')->restrictOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_press_assignments');
    }
};
