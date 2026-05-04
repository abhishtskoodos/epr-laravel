<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('permission:cache-reset');

        $permissions = [
            // Vendor module
            'vendors.view', 'vendors.create', 'vendors.update', 'vendors.verify', 'vendors.suspend',
            'vendor_centers.view', 'vendor_centers.create', 'vendor_centers.update', 'vendor_centers.inspect', 'vendor_centers.approve',
            'vendor_kyc.view', 'vendor_kyc.update', 'vendor_kyc.verify',
            // Trainer module
            'trainers.view', 'trainers.create', 'trainers.update', 'trainers.verify', 'trainers.assign',
            // Candidate module
            'candidates.view', 'candidates.create', 'candidates.update', 'candidates.verify', 'candidates.bulk_import',
            'enrollments.view', 'enrollments.create', 'enrollments.update',
            'assessments.view', 'assessments.update', 'assessments.verify',
            'certifications.view', 'certifications.issue', 'certifications.revoke',
            'placements.view', 'placements.create', 'placements.verify',
            // Scheme module
            'schemes.view', 'schemes.create', 'schemes.update', 'schemes.delete',
            'job_roles.view', 'job_roles.create', 'job_roles.update',
            'batches.view', 'batches.create', 'batches.update',
            // Invoice / Payment
            'invoices.view', 'invoices.create', 'invoices.submit', 'invoices.review', 'invoices.approve', 'invoices.reject',
            'payments.view', 'payments.record',
            // System
            'audit.view', 'reports.view', 'users.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $roles = [
            RoleName::Admin->value => $permissions, // full access
            RoleName::Inspector->value => [
                'vendors.view', 'vendor_centers.view', 'vendor_centers.inspect', 'vendor_centers.approve',
                'trainers.view', 'audit.view',
            ],
            RoleName::Finance->value => [
                'vendors.view', 'vendor_kyc.view', 'vendor_kyc.verify',
                'invoices.view', 'invoices.review', 'invoices.approve', 'invoices.reject',
                'payments.view', 'payments.record', 'reports.view', 'audit.view',
            ],
            RoleName::Vendor->value => [
                'vendors.view', 'vendors.update',
                'vendor_centers.view', 'vendor_centers.create', 'vendor_centers.update',
                'vendor_kyc.view', 'vendor_kyc.update',
                'trainers.view', 'trainers.create', 'trainers.update',
                'candidates.view', 'candidates.create', 'candidates.update', 'candidates.bulk_import',
                'enrollments.view', 'enrollments.create', 'enrollments.update',
                'assessments.view', 'assessments.update',
                'certifications.view',
                'placements.view', 'placements.create',
                'batches.view', 'batches.create', 'batches.update',
                'schemes.view', 'job_roles.view',
                'invoices.view', 'invoices.create', 'invoices.submit',
                'payments.view',
            ],
            RoleName::Trainer->value => [
                'trainers.view', 'trainers.update',
                'candidates.view', 'enrollments.view',
                'assessments.view', 'assessments.update',
                'batches.view', 'schemes.view',
            ],
            RoleName::Candidate->value => [
                'candidates.view', 'candidates.update',
                'enrollments.view',
                'assessments.view',
                'certifications.view',
                'placements.view',
            ],
        ];

        foreach ($roles as $name => $perms) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }
    }
}
