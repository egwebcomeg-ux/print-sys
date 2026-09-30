<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who did what and when — job status changes, re-pricing, press routing,
     * invoicing and price/settings edits. Read-only history, never edited.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type', 40)->index(); // job | paper_price | settings | ...
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('action', 40);
            $table->string('description');
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['subject_type', 'subject_id']);
        });

        // Supplier price history (every save on a grammage price).
        Schema::create('paper_price_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_grammage_price_id')->nullable()->constrained('paper_grammage_prices')->nullOnDelete();
            $table->foreignId('paper_grammage_id')->constrained('paper_grammages')->cascadeOnDelete();
            $table->foreignId('paper_supplier_id')->constrained('paper_suppliers')->cascadeOnDelete();
            $table->decimal('old_price_egp', 10, 2)->nullable();
            $table->decimal('new_price_egp', 10, 2);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paper_price_changes');
        Schema::dropIfExists('activity_logs');
    }
};
