<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('pan', 10)->unique();
            $table->string('gst', 15)->nullable()->unique();
            $table->string('cin', 21)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 15);
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('pincode', 10);
            $table->string('country', 2)->default('IN');
            $table->string('entity_type', 32); // private_ltd, public_ltd, partnership, llp, proprietorship, society, trust, section_8
            $table->string('status', 32)->default('draft'); // draft, pending_verification, verified, rejected, active, suspended
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index(['state', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
