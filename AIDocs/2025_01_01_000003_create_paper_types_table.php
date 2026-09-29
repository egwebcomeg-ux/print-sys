<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paper_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // مثال: "دوبلكس ظهر رمادي"
            // Matches the PaperCategory union type in the React calculators —
            // keep both in sync if you add a category here.
            $table->enum('category', [
                'duplex_grey_back',
                'duplex_white_back',
                'bristol_white_back',
                'kraft_liner',
                'couche',
                'triplex_board',
            ]);
            $table->unsignedSmallInteger('sheet_width_cm')->default(70);
            $table->unsignedSmallInteger('sheet_height_cm')->default(100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paper_types');
    }
};
