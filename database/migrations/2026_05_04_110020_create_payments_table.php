<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('mode', 16); // neft, rtgs, imps, upi, cheque
            $table->string('utr', 64)->nullable()->index();
            $table->dateTime('paid_on');
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('initiated'); // initiated, success, failed, reversed
            $table->json('bank_response_json')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
