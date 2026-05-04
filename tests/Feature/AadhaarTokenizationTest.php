<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Support\Aadhaar;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AadhaarTokenizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidates_table_does_not_have_raw_aadhaar_column(): void
    {
        $this->assertFalse(Schema::hasColumn('candidates', 'aadhaar'));
        $this->assertTrue(Schema::hasColumn('candidates', 'aadhaar_token'));
        $this->assertTrue(Schema::hasColumn('candidates', 'aadhaar_last4'));
    }

    public function test_setting_aadhaar_only_persists_token_and_last4(): void
    {
        $candidate = Candidate::create([
            'aadhaar' => '123456789012',
            'full_name' => 'R Kumar',
            'gender' => 'male',
            'dob' => '2000-01-01',
            'category' => 'obc',
            'phone' => '9111111111',
            'address_line1' => 'x', 'city' => 'x', 'state' => 'x', 'pincode' => '111111',
            'education_level' => '12_pass',
        ]);

        $this->assertSame(Aadhaar::tokenize('123456789012'), $candidate->aadhaar_token);
        $this->assertSame('9012', $candidate->aadhaar_last4);
        $this->assertSame('XXXX-XXXX-9012', $candidate->maskedAadhaar());
    }

    public function test_invalid_aadhaar_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Candidate::create([
            'aadhaar' => '12345',
            'full_name' => 'X',
            'gender' => 'male',
            'dob' => '2000-01-01',
            'category' => 'gen',
            'phone' => '9111111112',
            'address_line1' => 'x', 'city' => 'x', 'state' => 'x', 'pincode' => '111111',
            'education_level' => '12_pass',
        ]);
    }

    public function test_aadhaar_uniqueness_via_token(): void
    {
        Candidate::create([
            'aadhaar' => '111122223333',
            'full_name' => 'A',
            'gender' => 'male', 'dob' => '2000-01-01', 'category' => 'gen',
            'phone' => '9111111113',
            'address_line1' => 'x', 'city' => 'x', 'state' => 'x', 'pincode' => '111111',
            'education_level' => '12_pass',
        ]);

        $this->expectException(QueryException::class);
        Candidate::create([
            'aadhaar' => '111122223333',
            'full_name' => 'B',
            'gender' => 'female', 'dob' => '2001-01-01', 'category' => 'gen',
            'phone' => '9111111114',
            'address_line1' => 'x', 'city' => 'x', 'state' => 'x', 'pincode' => '111111',
            'education_level' => '12_pass',
        ]);
    }
}
