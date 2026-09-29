<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dies', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // مثال: "D-301810"
            $table->string('name'); // مثال: "اسطامبة صيدلي 060"
            $table->decimal('length_cm', 6, 2);
            $table->decimal('width_cm', 6, 2);
            $table->decimal('depth_cm', 6, 2);
            $table->enum('closure_type', ['reverse_tuck', 'straight_tuck', 'auto_bottom', 'snap_lock']);
            $table->string('rack_location')->nullable(); // مثال: "ستاند أ - رف 3"
            $table->unsignedSmallInteger('ups_on_cut_sheet'); // عدد العلب في شابلونة الاسطامبة
            $table->enum('cut_fraction', ['1/1', '1/2', '1/4', '1/6', '1/8']);
            $table->enum('condition', ['ready', 'needs_rubber', 'maintenance'])->default('ready');
            // Die wear/lifespan tracking (flagged in the roadmap): count of
            // jobs run on this die, so the team gets a warning before it
            // wears out mid-job rather than finding out the hard way.
            $table->unsignedInteger('jobs_run_count')->default(0);
            $table->unsignedInteger('estimated_lifespan_jobs')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dies');
    }
};
