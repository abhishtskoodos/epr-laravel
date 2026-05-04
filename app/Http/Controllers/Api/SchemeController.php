<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchemeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Scheme::query()
                ->with(['eligibilityRules', 'paymentMilestones', 'jobRoles'])
                ->latest()
                ->paginate((int) $request->input('per_page', 25))
        );
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('schemes.create'), 403);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:schemes,code'],
            'name' => ['required', 'string', 'max:255'],
            'funding_agency' => ['nullable', 'string'],
            'scheme_type' => ['required', 'in:short_term,long_term,apprenticeship,rpl,placement_linked'],
            'description' => ['nullable', 'string'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'is_active' => ['nullable', 'boolean'],
            'min_attendance_percent' => ['nullable', 'numeric', 'between:0,100'],
            'requires_assessment' => ['nullable', 'boolean'],
            'requires_placement' => ['nullable', 'boolean'],
        ]);

        return response()->json(Scheme::create($data), 201);
    }

    public function show(Scheme $scheme): JsonResponse
    {
        return response()->json($scheme->load(['eligibilityRules', 'paymentMilestones', 'jobRoles']));
    }

    public function update(Request $request, Scheme $scheme): JsonResponse
    {
        abort_unless($request->user()?->can('schemes.update'), 403);
        $scheme->update($request->only([
            'name', 'funding_agency', 'description', 'effective_from', 'effective_to',
            'is_active', 'min_attendance_percent', 'requires_assessment', 'requires_placement',
        ]));

        return response()->json($scheme->fresh());
    }

    public function destroy(Request $request, Scheme $scheme): JsonResponse
    {
        abort_unless($request->user()?->can('schemes.delete'), 403);
        $scheme->delete();

        return response()->json(null, 204);
    }

    public function syncEligibilityRules(Request $request, Scheme $scheme): JsonResponse
    {
        abort_unless($request->user()?->can('schemes.update'), 403);
        $data = $request->validate([
            'rules' => ['required', 'array'],
            'rules.*.rule_key' => ['required', 'string'],
            'rules.*.operator' => ['required', 'in:eq,neq,gte,lte,in,not_in,between'],
            'rules.*.value_json' => ['required', 'array'],
            'rules.*.is_blocking' => ['nullable', 'boolean'],
            'rules.*.display_order' => ['nullable', 'integer'],
        ]);

        $scheme->eligibilityRules()->delete();
        $scheme->eligibilityRules()->createMany($data['rules']);

        return response()->json($scheme->fresh()->load('eligibilityRules'));
    }

    public function syncPaymentMilestones(Request $request, Scheme $scheme): JsonResponse
    {
        abort_unless($request->user()?->can('schemes.update'), 403);
        $data = $request->validate([
            'milestones' => ['required', 'array'],
            'milestones.*.key' => ['required', 'string'],
            'milestones.*.label' => ['required', 'string'],
            'milestones.*.percent' => ['nullable', 'numeric', 'between:0,100'],
            'milestones.*.amount' => ['nullable', 'numeric', 'min:0'],
            'milestones.*.requires_status' => ['nullable', 'string'],
            'milestones.*.display_order' => ['nullable', 'integer'],
        ]);

        $scheme->paymentMilestones()->delete();
        $scheme->paymentMilestones()->createMany($data['milestones']);

        return response()->json($scheme->fresh()->load('paymentMilestones'));
    }

    public function syncJobRoles(Request $request, Scheme $scheme): JsonResponse
    {
        abort_unless($request->user()?->can('schemes.update'), 403);
        $data = $request->validate([
            'mappings' => ['required', 'array'],
            'mappings.*.job_role_id' => ['required', 'exists:job_roles,id'],
            'mappings.*.payable_per_candidate' => ['required', 'numeric', 'min:0'],
        ]);

        $sync = collect($data['mappings'])->mapWithKeys(fn ($m) => [
            $m['job_role_id'] => ['payable_per_candidate' => $m['payable_per_candidate']],
        ])->toArray();
        $scheme->jobRoles()->sync($sync);

        return response()->json($scheme->fresh()->load('jobRoles'));
    }
}
