<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per paper (type + gsm) and sheet size the factory keeps on hand.
        // Sizes are stored short side first (70×100, never 100×70).
        Schema::create('paper_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_grammage_id')->constrained()->restrictOnDelete();
            $table->decimal('sheet_width_cm', 6, 2);
            $table->decimal('sheet_height_cm', 6, 2);
            // Signed: approving a job can push it below zero (= shortage to buy).
            $table->integer('quantity_sheets')->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->string('location', 100)->nullable();
            $table->timestamps();

            $table->unique(['paper_grammage_id', 'sheet_width_cm', 'sheet_height_cm'], 'paper_stocks_item_unique');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_stock_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // receipt | consumption | adjustment
            $table->integer('quantity'); // signed delta in sheets
            $table->integer('balance_after');
            $table->foreignId('job_id')->nullable()->constrained('jobs')->nullOnDelete();
            $table->foreignId('paper_supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['job_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('paper_stocks');
    }
};
