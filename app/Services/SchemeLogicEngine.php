<?php

namespace App\Services;

use App\Enums\CandidateStatus;
use App\Models\Candidate;
use App\Models\CandidateEnrollment;
use App\Models\Scheme;
use App\Models\SchemeEligibilityRule;
use App\Models\SchemePaymentMilestone;
use Carbon\Carbon;

/**
 * The "system brain" — evaluates eligibility, status progression, and milestone payouts.
 *
 * Pure data-driven: all rules live in `scheme_eligibility_rules` and `scheme_payment_milestones`.
 */
class SchemeLogicEngine
{
    /**
     * Check whether a candidate is eligible for a scheme.
     *
     * @return array{eligible: bool, blocking_reasons: list<string>, soft_warnings: list<string>}
     */
    public function checkEligibility(Candidate $candidate, Scheme $scheme): array
    {
        $blocking = [];
        $soft = [];

        if (! $scheme->is_active) {
            $blocking[] = 'Scheme is not active.';
        }

        $today = Carbon::today();
        if ($scheme->effective_to && $today->gt($scheme->effective_to)) {
            $blocking[] = 'Scheme has expired.';
        }
        if ($today->lt($scheme->effective_from)) {
            $blocking[] = 'Scheme is not yet effective.';
        }

        foreach ($scheme->eligibilityRules as $rule) {
            if (! $this->ruleMatches($rule, $candidate)) {
                $msg = $this->describeRuleFailure($rule);
                $rule->is_blocking ? $blocking[] = $msg : $soft[] = $msg;
            }
        }

        return [
            'eligible' => empty($blocking),
            'blocking_reasons' => $blocking,
            'soft_warnings' => $soft,
        ];
    }

    /**
     * Compute the next allowed candidate status given the current state of the enrollment.
     */
    public function nextStatus(CandidateEnrollment $enrollment): ?CandidateStatus
    {
        $candidate = $enrollment->candidate;
        $scheme = $enrollment->batch?->scheme;

        if (! $candidate || ! $scheme) {
            return null;
        }

        $current = $candidate->status;
        $minAttendance = (float) $scheme->min_attendance_percent;
        $attendance = (float) ($enrollment->attendance_percent ?? 0);

        return match ($current) {
            CandidateStatus::Registered => $enrollment->enrolled_at
                ? CandidateStatus::Training
                : null,

            CandidateStatus::Training => $enrollment->assessment
                && $attendance >= $minAttendance
                && (! $scheme->requires_assessment || $enrollment->assessment->status === 'verified')
                    ? CandidateStatus::Assessed
                    : null,

            CandidateStatus::Assessed => $enrollment->assessment?->result === 'pass'
                && $enrollment->certification?->status === 'issued'
                    ? CandidateStatus::Certified
                    : null,

            CandidateStatus::Certified => $scheme->requires_placement
                && $enrollment->placement?->status === 'verified'
                    ? CandidateStatus::Placed
                    : null,

            default => null,
        };
    }

    /**
     * Determine which milestones are payable for an enrollment, with reasons.
     *
     * @return array<int, array{milestone: SchemePaymentMilestone, eligible: bool, amount: float, reason: string}>
     */
    public function payableMilestones(CandidateEnrollment $enrollment): array
    {
        $scheme = $enrollment->batch?->scheme;
        if (! $scheme) {
            return [];
        }

        $perCandidate = $this->payablePerCandidate($enrollment);
        $candidate = $enrollment->candidate;
        $out = [];

        foreach ($scheme->paymentMilestones()->orderBy('display_order')->get() as $milestone) {
            $required = $milestone->requires_status;
            $reached = $required === null || $this->statusReached($candidate->status, $required);

            $amount = $milestone->amount !== null
                ? (float) $milestone->amount
                : round($perCandidate * ((float) $milestone->percent) / 100, 2);

            $out[] = [
                'milestone' => $milestone,
                'eligible' => $reached,
                'amount' => $amount,
                'reason' => $reached
                    ? 'Required status reached.'
                    : "Requires candidate status '{$required}', currently '{$candidate->status->value}'.",
            ];
        }

        return $out;
    }

    private function payablePerCandidate(CandidateEnrollment $enrollment): float
    {
        $jobRoleId = $enrollment->batch?->job_role_id;
        $scheme = $enrollment->batch?->scheme;
        if (! $jobRoleId || ! $scheme) {
            return 0.0;
        }

        $row = $scheme->jobRoles()->where('job_roles.id', $jobRoleId)->first();

        return $row?->pivot?->payable_per_candidate
            ? (float) $row->pivot->payable_per_candidate
            : 0.0;
    }

    private function ruleMatches(SchemeEligibilityRule $rule, Candidate $candidate): bool
    {
        $value = $this->extractCandidateValue($rule->rule_key, $candidate);
        $op = $rule->operator;
        $rhs = $rule->value_json;

        return match ($op) {
            'eq' => $value == ($rhs['value'] ?? null),
            'neq' => $value != ($rhs['value'] ?? null),
            'gte' => $value !== null && $value >= ($rhs['value'] ?? PHP_INT_MIN),
            'lte' => $value !== null && $value <= ($rhs['value'] ?? PHP_INT_MAX),
            'in' => in_array($value, (array) ($rhs['values'] ?? []), true),
            'not_in' => ! in_array($value, (array) ($rhs['values'] ?? []), true),
            'between' => $value !== null
                && $value >= ($rhs['min'] ?? PHP_INT_MIN)
                && $value <= ($rhs['max'] ?? PHP_INT_MAX),
            default => true,
        };
    }

    private function extractCandidateValue(string $key, Candidate $candidate): mixed
    {
        return match ($key) {
            'age_min', 'age_max' => $candidate->dob?->age,
            'gender' => $candidate->gender,
            'category' => $candidate->category,
            'education_min' => $this->educationOrdinal($candidate->education_level),
            'state' => $candidate->state,
            default => null,
        };
    }

    private function educationOrdinal(?string $level): int
    {
        $order = ['below_8' => 0, '8_pass' => 1, '10_pass' => 2, '12_pass' => 3, 'iti' => 4, 'diploma' => 5, 'graduate' => 6, 'pg' => 7];

        return $order[$level] ?? -1;
    }

    private function describeRuleFailure(SchemeEligibilityRule $rule): string
    {
        return "Eligibility rule failed: {$rule->rule_key} {$rule->operator} ".json_encode($rule->value_json);
    }

    private function statusReached(CandidateStatus $current, string $required): bool
    {
        $order = [
            CandidateStatus::Registered->value => 0,
            CandidateStatus::Training->value => 1,
            CandidateStatus::Assessed->value => 2,
            CandidateStatus::Certified->value => 3,
            CandidateStatus::Placed->value => 4,
        ];

        return ($order[$current->value] ?? -1) >= ($order[$required] ?? PHP_INT_MAX);
    }
}
