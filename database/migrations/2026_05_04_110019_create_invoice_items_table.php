<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheme_payment_milestone_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->boolean('is_eligible')->default(false);
            $table->text('eligibility_reason')->nullable();
            $table->string('status', 32)->default('pending'); // pending, approved, rejected
            $table->timestamps();

            // No double-billing for the same enrollment + milestone across all invoices
            $table->unique(['candidate_enrollment_id', 'scheme_payment_milestone_id'], 'invoice_items_no_double_billing');
            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
