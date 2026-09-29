<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paper_grammages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_type_id')->constrained('paper_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('gsm'); // جرام / متر مربع، مثال 250, 300, 350
            $table->timestamps();

            $table->unique(['paper_type_id', 'gsm']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paper_grammages');
    }
};
