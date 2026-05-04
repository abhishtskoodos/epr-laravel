<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCandidateRequest;
use App\Models\Candidate;
use App\Models\CandidateEnrollment;
use App\Models\Scheme;
use App\Services\SchemeLogicEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateController extends Controller
{
    public function __construct(private readonly SchemeLogicEngine $engine) {}

    public function index(Request $request): JsonResponse
    {
        $q = Candidate::query()->latest();
        if ($request->user()?->hasRole('vendor')) {
            $q->where('registered_by_vendor_id', $request->user()->vendor?->id);
        }

        return response()->json($q->paginate((int) $request->input('per_page', 25)));
    }

    public function store(StoreCandidateRequest $request): JsonResponse
    {
        $vendorId = $request->user()->vendor?->id;
        $candidate = Candidate::create([
            ...$request->validated(),
            'registered_by_vendor_id' => $vendorId,
            'registered_via' => $vendorId ? 'vendor' : 'self',
        ]);

        return response()->json($candidate, 201);
    }

    public function show(Candidate $candidate): JsonResponse
    {
        return response()->json($candidate->load([
            'enrollments.batch.scheme',
            'enrollments.assessment',
            'enrollments.certification',
            'enrollments.placement',
            'documents',
        ]));
    }

    public function update(Request $request, Candidate $candidate): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['sometimes', 'string'],
            'phone' => ['sometimes', 'regex:/^\d{10}$/'],
            'email' => ['sometimes', 'nullable', 'email'],
            'address_line1' => ['sometimes', 'string'],
            'city' => ['sometimes', 'string'],
            'state' => ['sometimes', 'string'],
            'pincode' => ['sometimes', 'regex:/^\d{6}$/'],
            'education_level' => ['sometimes', 'in:below_8,8_pass,10_pass,12_pass,iti,diploma,graduate,pg'],
        ]);
        $candidate->update($data);

        return response()->json($candidate->fresh());
    }

    public function destroy(Candidate $candidate): JsonResponse
    {
        abort_unless($this->canManageCandidate($candidate), 403);
        $candidate->delete();

        return response()->json(null, 204);
    }

    public function eligibilityCheck(Request $request, Candidate $candidate): JsonResponse
    {
        $data = $request->validate(['scheme_id' => ['required', 'exists:schemes,id']]);
        $scheme = Scheme::with('eligibilityRules')->findOrFail($data['scheme_id']);

        return response()->json($this->engine->checkEligibility($candidate, $scheme));
    }

    public function enroll(Request $request, Candidate $candidate): JsonResponse
    {
        $data = $request->validate([
            'batch_id' => ['required', 'exists:batches,id'],
            'enrolled_at' => ['nullable', 'date'],
        ]);

        $enrollment = $candidate->enrollments()->create([
            'batch_id' => $data['batch_id'],
            'enrolled_at' => $data['enrolled_at'] ?? now(),
        ]);
        // Auto-progress to "training" if rules allow
        $next = $this->engine->nextStatus($enrollment);
        if ($next !== null) {
            $candidate->transitionStatus($next->value);
        }

        return response()->json($enrollment->fresh(), 201);
    }

    public function updateEnrollment(Request $request, CandidateEnrollment $enrollment): JsonResponse
    {
        $data = $request->validate([
            'attendance_percent' => ['nullable', 'numeric', 'between:0,100'],
            'ojt_completed' => ['nullable', 'boolean'],
            'ojt_hours' => ['nullable', 'integer', 'min:0'],
            'dropped_at' => ['nullable', 'date'],
            'drop_reason' => ['nullable', 'string'],
        ]);
        $enrollment->update($data);

        return response()->json($enrollment->fresh());
    }

    public function putAssessment(Request $request, CandidateEnrollment $enrollment): JsonResponse
    {
        $data = $request->validate([
            'assessment_agency' => ['required', 'string'],
            'assessor_name' => ['required', 'string'],
            'assessed_on' => ['required', 'date'],
            'theory_score' => ['required', 'numeric', 'between:0,100'],
            'practical_score' => ['required', 'numeric', 'between:0,100'],
            'viva_score' => ['nullable', 'numeric', 'between:0,100'],
            'total_score' => ['required', 'numeric', 'between:0,100'],
            'result' => ['required', 'in:pass,fail,pending'],
        ]);

        $assessment = $enrollment->assessment()->updateOrCreate(
            ['candidate_enrollment_id' => $enrollment->id],
            $data + ['status' => 'pending'],
        );

        return response()->json($assessment->fresh());
    }

    public function putCertification(Request $request, CandidateEnrollment $enrollment): JsonResponse
    {
        $data = $request->validate([
            'certificate_no' => ['required', 'string', 'unique:candidate_certifications,certificate_no'],
            'issued_on' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after:issued_on'],
        ]);

        $cert = $enrollment->certification()->updateOrCreate(
            ['candidate_enrollment_id' => $enrollment->id],
            $data + ['status' => 'issued'],
        );

        return response()->json($cert->fresh());
    }

    public function putPlacement(Request $request, CandidateEnrollment $enrollment): JsonResponse
    {
        $data = $request->validate([
            'employer_name' => ['required', 'string'],
            'employer_pan' => ['nullable', 'regex:/^[A-Z]{5}\d{4}[A-Z]$/'],
            'designation' => ['required', 'string'],
            'monthly_ctc' => ['required', 'numeric', 'min:0'],
            'placement_type' => ['required', 'in:wage,self_employed,apprentice'],
            'placed_on' => ['required', 'date'],
        ]);

        $placement = $enrollment->placement()->updateOrCreate(
            ['candidate_enrollment_id' => $enrollment->id],
            $data + ['status' => 'pending'],
        );

        return response()->json($placement->fresh());
    }

    public function progressStatus(CandidateEnrollment $enrollment): JsonResponse
    {
        $next = $this->engine->nextStatus($enrollment);
        if ($next === null) {
            return response()->json([
                'message' => 'No transition available given current state.',
                'current' => $enrollment->candidate->status->value,
            ], 422);
        }

        $enrollment->candidate->transitionStatus($next->value);

        return response()->json([
            'candidate_id' => $enrollment->candidate_id,
            'status' => $next->value,
        ]);
    }

    private function canManageCandidate(Candidate $c): bool
    {
        $u = request()->user();

        return $u?->hasRole('admin')
            || ($u?->hasRole('vendor') && $c->registered_by_vendor_id === $u->vendor?->id);
    }
}
