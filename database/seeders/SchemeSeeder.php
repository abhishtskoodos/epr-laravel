<?php

namespace Database\Seeders;

use App\Models\JobRole;
use App\Models\Scheme;
use Illuminate\Database\Seeder;

class SchemeSeeder extends Seeder
{
    public function run(): void
    {
        $scheme = Scheme::updateOrCreate(
            ['code' => 'PMKVY-4.0'],
            [
                'name' => 'PMKVY 4.0 (Short Term Training)',
                'funding_agency' => 'NSDC',
                'scheme_type' => 'short_term',
                'description' => 'Pradhan Mantri Kaushal Vikas Yojana — short-term skilling with placement linkage.',
                'effective_from' => now()->subYear()->toDateString(),
                'effective_to' => now()->addYears(2)->toDateString(),
                'is_active' => true,
                'min_attendance_percent' => 70,
                'requires_assessment' => true,
                'requires_placement' => true,
            ],
        );

        // Eligibility rules
        $rules = [
            ['rule_key' => 'age_min', 'operator' => 'gte', 'value_json' => ['value' => 15], 'is_blocking' => true, 'display_order' => 1],
            ['rule_key' => 'age_max', 'operator' => 'lte', 'value_json' => ['value' => 45], 'is_blocking' => true, 'display_order' => 2],
            ['rule_key' => 'education_min', 'operator' => 'gte', 'value_json' => ['value' => 2], 'is_blocking' => true, 'display_order' => 3], // 10_pass
        ];
        foreach ($rules as $r) {
            $scheme->eligibilityRules()->updateOrCreate(
                ['rule_key' => $r['rule_key']],
                $r,
            );
        }

        // Payment milestones
        $milestones = [
            ['key' => 'enrollment', 'label' => 'On Enrollment', 'percent' => 30, 'requires_status' => 'training', 'display_order' => 1],
            ['key' => 'certification', 'label' => 'On Certification', 'percent' => 30, 'requires_status' => 'certified', 'display_order' => 2],
            ['key' => 'placement_3m', 'label' => 'On Placement (3 months retention)', 'percent' => 40, 'requires_status' => 'placed', 'display_order' => 3],
        ];
        foreach ($milestones as $m) {
            $scheme->paymentMilestones()->updateOrCreate(
                ['key' => $m['key']],
                $m,
            );
        }

        // Attach all job roles with a default payable amount
        foreach (JobRole::all() as $role) {
            $scheme->jobRoles()->syncWithoutDetaching([
                $role->id => ['payable_per_candidate' => 22000],
            ]);
        }
    }
}
