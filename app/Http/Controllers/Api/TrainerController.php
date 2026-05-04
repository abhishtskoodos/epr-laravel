<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransitionRequest;
use App\Models\Trainer;
use App\Models\TrainerAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Trainer::latest()->paginate((int) $request->input('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'full_name' => ['required', 'string'],
            'pan' => ['nullable', 'regex:/^[A-Z]{5}\d{4}[A-Z]$/', 'unique:trainers,pan'],
            'qualification' => ['nullable', 'string'],
            'tot_certificate_no' => ['nullable', 'string'],
            'experience_years' => ['nullable', 'numeric', 'min:0'],
            'phone' => ['nullable', 'regex:/^\d{10}$/'],
            'email' => ['nullable', 'email'],
        ]);
        $trainer = Trainer::create($data);

        return response()->json($trainer, 201);
    }

    public function show(Trainer $trainer): JsonResponse
    {
        return response()->json($trainer->load('assignments.batch.scheme'));
    }

    public function update(Request $request, Trainer $trainer): JsonResponse
    {
        $trainer->update($request->only(['full_name', 'qualification', 'tot_certificate_no', 'experience_years', 'phone', 'email']));

        return response()->json($trainer->fresh());
    }

    public function destroy(Trainer $trainer): JsonResponse
    {
        abort_unless(request()->user()?->hasRole('admin'), 403);
        $trainer->delete();

        return response()->json(null, 204);
    }

    public function transition(TransitionRequest $request, Trainer $trainer): JsonResponse
    {
        $trainer->transitionStatus(
            $request->validated('to'),
            $request->user()->id,
            $request->validated('remarks'),
            $request->validated('meta') ?? [],
        );

        return response()->json($trainer->fresh());
    }

    public function assign(Request $request, Trainer $trainer): JsonResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
        $data = $request->validate([
            'batch_id' => ['required', 'exists:batches,id'],
            'assigned_at' => ['nullable', 'date'],
            'meta' => ['nullable', 'array'],
        ]);

        $a = TrainerAssignment::create([
            'trainer_id' => $trainer->id,
            'batch_id' => $data['batch_id'],
            'assigned_by' => $request->user()->id,
            'assigned_at' => $data['assigned_at'] ?? now(),
            'meta' => $data['meta'] ?? null,
        ]);

        return response()->json($a, 201);
    }
}
