<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Scheme;
use App\Services\SchemeLogicEngine;
use Database\Seeders\JobRoleSeeder;
use Database\Seeders\SchemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemeLogicEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(JobRoleSeeder::class);
        $this->seed(SchemeSeeder::class);
    }

    public function test_eligible_candidate_passes_all_rules(): void
    {
        $scheme = Scheme::with('eligibilityRules')->firstOrFail();
        $candidate = $this->makeCandidate(['dob' => now()->subYears(20)->toDateString(), 'education_level' => '12_pass']);
        $result = app(SchemeLogicEngine::class)->checkEligibility($candidate, $scheme);

        $this->assertTrue($result['eligible']);
        $this->assertEmpty($result['blocking_reasons']);
    }

    public function test_underage_candidate_is_blocked(): void
    {
        $scheme = Scheme::with('eligibilityRules')->firstOrFail();
        $candidate = $this->makeCandidate(['dob' => now()->subYears(10)->toDateString(), 'education_level' => '12_pass']);
        $result = app(SchemeLogicEngine::class)->checkEligibility($candidate, $scheme);

        $this->assertFalse($result['eligible']);
        $this->assertNotEmpty($result['blocking_reasons']);
    }

    public function test_low_education_candidate_is_blocked(): void
    {
        $scheme = Scheme::with('eligibilityRules')->firstOrFail();
        $candidate = $this->makeCandidate(['dob' => now()->subYears(20)->toDateString(), 'education_level' => 'below_8']);
        $result = app(SchemeLogicEngine::class)->checkEligibility($candidate, $scheme);

        $this->assertFalse($result['eligible']);
    }

    private function makeCandidate(array $overrides = []): Candidate
    {
        static $counter = 0;
        $counter++;

        return Candidate::create(array_merge([
            'aadhaar' => str_pad((string) (100000000000 + $counter), 12, '0', STR_PAD_LEFT),
            'full_name' => 'Cand '.$counter,
            'gender' => 'male',
            'dob' => '2000-01-01',
            'category' => 'obc',
            'phone' => '91111'.str_pad((string) $counter, 5, '0', STR_PAD_LEFT),
            'address_line1' => 'x', 'city' => 'x', 'state' => 'x', 'pincode' => '111111',
            'education_level' => '12_pass',
        ], $overrides));
    }
}
