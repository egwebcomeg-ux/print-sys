<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Simple CRM leads/opportunities (الفرص) that feed into job creation.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('contact_name');
            $table->string('company_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->nullable(); // مثال: "فيسبوك"، "ترشيح عميل"، "زيارة"
            $table->string('status', 20)->default('new')->index(); // App\Enums\LeadStatus
            $table->unsignedInteger('expected_quantity')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_job_id')->nullable()->constrained('jobs')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
