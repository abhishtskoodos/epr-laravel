<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_vendor_can_self_register_and_status_is_pending_verification(): void
    {
        $user = User::factory()->create(['phone' => '9876500001']);
        $user->assignRole('vendor');

        $payload = [
            'legal_name' => 'ACME Skill Pvt Ltd',
            'pan' => 'AAAAA1111A',
            'phone' => '9876500001',
            'address_line1' => '12 MG Road',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560001',
            'entity_type' => 'private_ltd',
        ];

        $resp = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/vendors', $payload)
            ->assertCreated()
            ->json();

        $this->assertSame('pending_verification', $resp['status']);
        $this->assertDatabaseHas('vendors', ['pan' => 'AAAAA1111A']);
    }

    public function test_admin_verification_writes_immutable_verifications_row(): void
    {
        $admin = User::factory()->create(['phone' => '9999999999']);
        $admin->assignRole('admin');

        $vendor = Vendor::factory()->for($admin, 'user')->create([
            'pan' => 'BBBBB2222B',
            'status' => 'pending_verification',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/vendors/{$vendor->id}/transition", ['to' => 'verified'])
            ->assertOk()
            ->assertJsonPath('status', 'verified');

        $this->assertDatabaseHas('verifications', [
            'verifiable_type' => Vendor::class,
            'verifiable_id' => $vendor->id,
            'to_status' => 'verified',
            'verifier_id' => $admin->id,
        ]);
    }

    public function test_rejection_requires_remarks(): void
    {
        $admin = User::factory()->create(['phone' => '9999999990']);
        $admin->assignRole('admin');

        $vendor = Vendor::factory()->for($admin, 'user')->create([
            'pan' => 'CCCCC3333C',
            'status' => 'pending_verification',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/vendors/{$vendor->id}/transition", ['to' => 'rejected'])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_illegal_transition_is_rejected(): void
    {
        $admin = User::factory()->create(['phone' => '9999999991']);
        $admin->assignRole('admin');

        $vendor = Vendor::factory()->for($admin, 'user')->create([
            'pan' => 'DDDDD4444D',
            'status' => 'draft',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/vendors/{$vendor->id}/transition", ['to' => 'verified'])
            ->assertStatus(422);
    }
}
