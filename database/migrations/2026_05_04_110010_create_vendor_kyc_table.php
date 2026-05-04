<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_kyc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_holder_name');
            $table->text('account_number_encrypted'); // Laravel Crypt
            $table->string('account_number_last4', 4);
            $table->string('ifsc', 11);
            $table->foreignId('pan_image_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('cancelled_cheque_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('agreement_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('status', 32)->default('pending'); // pending, verified, rejected
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_kyc');
    }
};
