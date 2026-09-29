<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The matching `res.partner` id in Odoo, resolved (or created) the first
     * time the customer is invoiced and cached here so later invoices reuse it.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('odoo_partner_id')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('odoo_partner_id');
        });
    }
};
