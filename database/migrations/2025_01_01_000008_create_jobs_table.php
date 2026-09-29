<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The central record: a lead becomes a job, a job becomes a quote, an
     * approved quote becomes a production job + project, and a completed
     * job (after waste) becomes the one Odoo API call for invoicing.
     * `status` is the job's own lifecycle; `job_stages` (next migration)
     * tracks the finer-grained production stages within it.
     *
     * Changed from the original AIDocs version: a job is either a die-cut
     * box (QuickBoxPricingCalculator) or a manual paper job
     * (ManualJobCostingCalculator), so the box-only columns are nullable and
     * manual paper line items live in `job_paper_items`.
     */
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('job_type', 10)->default('box'); // box | manual (App\Enums\JobType)
            $table->string('title')->nullable(); // اسم الشغلانة — also the Odoo invoice line name

            // What's being made (box jobs only)
            $table->string('box_type')->nullable(); // medicine | candy | cosmetics | food | general
            $table->string('box_shape')->nullable(); // reverse_tuck_end | straight_tuck_end | auto_lock_bottom | pillow_bag
            $table->decimal('length_cm', 6, 2)->nullable();
            $table->decimal('width_cm', 6, 2)->nullable();
            $table->decimal('depth_cm', 6, 2)->nullable();
            $table->unsignedInteger('quantity')->nullable(); // originally quoted quantity (optional for manual jobs)

            // Materials (box jobs; manual jobs use job_paper_items)
            $table->foreignId('paper_grammage_id')->nullable()->constrained('paper_grammages')->restrictOnDelete();
            $table->foreignId('paper_grammage_price_id')->nullable()->constrained('paper_grammage_prices')->nullOnDelete();
            $table->unsignedTinyInteger('print_colors')->default(0);
            $table->enum('lamination', ['none', 'matte', 'gloss'])->default('none');

            // Die
            $table->boolean('is_using_existing_die')->default(true);
            $table->foreignId('die_id')->nullable()->constrained('dies')->nullOnDelete();

            // Production prep captured at quote time (box jobs) — إعداد مبدئي
            $table->unsignedInteger('raw_sheets_needed')->nullable();
            $table->unsignedSmallInteger('ups_per_raw_sheet')->nullable();
            $table->boolean('interlocked')->default(false);

            // Pricing — margin is always typed manually, per the confirmed workflow
            $table->decimal('base_cost_egp', 12, 2)->default(0);
            $table->decimal('margin_percent', 5, 2)->default(0);
            $table->decimal('final_price_egp', 12, 2)->default(0);
            // Full calculator payload at confirmation time, so the breakdown
            // survives later changes to paper prices or pricing constants.
            $table->json('quote_snapshot')->nullable();

            // Filled in once production is done, accounting for waste —
            // this (not `quantity`) is what the final Odoo invoice uses.
            $table->unsignedInteger('produced_quantity')->nullable();

            $table->enum('status', [
                'draft',            // being priced
                'quoted',           // quote sent to customer
                'approved',         // customer approved, preliminary order raised
                'in_production',    // tracked via job_stages
                'completed',        // final quantity confirmed, ready to invoice
                'invoiced',         // Odoo invoice created
            ])->default('draft');

            $table->timestamps();

            $table->index(['status', 'job_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
