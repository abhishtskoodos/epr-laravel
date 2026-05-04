<?php

namespace Database\Seeders;

use App\Models\JobRole;
use Illuminate\Database\Seeder;

class JobRoleSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['qp_code' => 'TEL/Q2100', 'name' => 'Customer Care Executive (Voice)', 'nsqf_level' => 4, 'sector' => 'Telecom'],
            ['qp_code' => 'RAS/Q0103', 'name' => 'Retail Sales Associate', 'nsqf_level' => 4, 'sector' => 'Retail'],
            ['qp_code' => 'AGR/Q0101', 'name' => 'Field Crop Production Worker', 'nsqf_level' => 3, 'sector' => 'Agriculture'],
            ['qp_code' => 'BSC/Q0204', 'name' => 'Beauty Therapist', 'nsqf_level' => 4, 'sector' => 'Beauty & Wellness'],
            ['qp_code' => 'ASC/Q1402', 'name' => 'Automotive Service Technician (Two-Wheeler)', 'nsqf_level' => 4, 'sector' => 'Automotive'],
            ['qp_code' => 'CON/Q0103', 'name' => 'Assistant Electrician', 'nsqf_level' => 4, 'sector' => 'Construction'],
            ['qp_code' => 'HCS/Q5101', 'name' => 'General Duty Assistant', 'nsqf_level' => 4, 'sector' => 'Healthcare'],
            ['qp_code' => 'TSC/Q2101', 'name' => 'Sewing Machine Operator', 'nsqf_level' => 4, 'sector' => 'Textiles & Apparel'],
        ];

        foreach ($rows as $row) {
            JobRole::updateOrCreate(['qp_code' => $row['qp_code']], $row);
        }
    }
}
