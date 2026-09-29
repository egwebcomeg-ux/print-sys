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
     */
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();

            // What's being made
            $table->string('box_type'); // medicine | candy | cosmetics | food | general
            $table->string('box_shape'); // reverse_tuck_end | straight_tuck_end | auto_lock_bottom | pillow_bag
            $table->decimal('length_cm', 6, 2);
            $table->decimal('width_cm', 6, 2);
            $table->decimal('depth_cm', 6, 2);
            $table->unsignedInteger('quantity'); // originally quoted quantity

            // Materials
            $table->foreignId('paper_grammage_id')->constrained('paper_grammages')->restrictOnDelete();
            $table->foreignId('paper_grammage_price_id')->nullable()->constrained('paper_grammage_prices')->nullOnDelete();
            $table->unsignedTinyInteger('print_colors')->default(0);
            $table->enum('lamination', ['none', 'matte', 'gloss'])->default('none');

            // Die
            $table->boolean('is_using_existing_die')->default(true);
            $table->foreignId('die_id')->nullable()->constrained('dies')->nullOnDelete();

            // Pricing — margin is always typed manually, per the confirmed workflow
            $table->decimal('base_cost_egp', 12, 2)->default(0);
            $table->decimal('margin_percent', 5, 2)->default(0);
            $table->decimal('final_price_egp', 12, 2)->default(0);

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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
