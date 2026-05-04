<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('aadhaar_token', 64)->unique();
            $table->string('aadhaar_last4', 4);
            $table->string('full_name');
            $table->string('gender', 16); // male, female, other
            $table->date('dob');
            $table->string('category', 16); // gen, obc, sc, st, pwd, minority, ews
            $table->string('phone', 15)->index();
            $table->string('email')->nullable();
            $table->string('address_line1');
            $table->string('city');
            $table->string('state');
            $table->string('pincode', 10);
            $table->string('education_level', 32); // below_8, 8_pass, 10_pass, 12_pass, iti, diploma, graduate, pg
            $table->string('status', 32)->default('registered'); // registered, training, assessed, certified, placed, dropped
            $table->string('registered_via', 32)->default('self'); // self, vendor, bulk_import
            $table->foreignId('registered_by_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index(['state', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
