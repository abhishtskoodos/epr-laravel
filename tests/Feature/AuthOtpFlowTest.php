<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthOtpFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_request_otp_creates_pending_row(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '9876543210'])
            ->assertOk()
            ->assertJson(['message' => 'OTP issued. Check your SMS.']);

        $this->assertDatabaseHas('otp_codes', [
            'phone' => '9876543210',
            'purpose' => 'login',
        ]);
    }

    public function test_verify_otp_returns_token_and_creates_user_with_candidate_role(): void
    {
        $code = '123456';
        OtpCode::create([
            'phone' => '9876543210',
            'code_hash' => Hash::make($code),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(5),
        ]);

        $resp = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '9876543210',
            'code' => $code,
        ])->assertOk()->json();

        $this->assertNotEmpty($resp['token']);
        $this->assertContains('candidate', $resp['user']['roles']);

        $user = User::where('phone', '9876543210')->firstOrFail();
        $this->assertNotNull($user->phone_verified_at);
        $this->assertTrue($user->hasRole('candidate'));
    }

    public function test_verify_rejects_bad_code(): void
    {
        OtpCode::create([
            'phone' => '9876543210',
            'code_hash' => Hash::make('123456'),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '9876543210',
            'code' => '999999',
        ])->assertStatus(422);
    }
}
