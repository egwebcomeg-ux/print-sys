<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paper_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // مثال: "مصنع الأهرام للورق"
            $table->string('contact_note')->nullable(); // رقم تليفون / اسم المسؤول
            // Vendor performance tracking (flagged in the roadmap): keep it
            // simple to start — free-text notes are enough until there's
            // real delivery/quality history to compute a score from.
            $table->text('performance_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paper_suppliers');
    }
};
