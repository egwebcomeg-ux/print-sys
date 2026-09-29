<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ONLY table that talks to Odoo. One row per invoicing attempt for
     * a job — kept separate from `jobs` so a failed API call can be retried
     * without losing the history of what was sent/received (per the
     * roadmap's risk note: Odoo is a single point of failure for invoicing,
     * so this needs clear retry/error visibility, and a manual fallback:
     * if `status` stays 'failed', staff can create the invoice by hand in
     * Odoo and record the resulting odoo_invoice_id here manually).
     */
    public function up(): void
    {
        Schema::create('odoo_invoice_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->string('odoo_invoice_id')->nullable();
            $table->enum('status', ['pending', 'sent', 'success', 'failed'])->default('pending');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_invoice_syncs');
    }
};
