<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVendorRequest;
use App\Http\Requests\TransitionRequest;
use App\Models\Vendor;
use App\Models\VendorCenter;
use App\Models\VendorKyc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class VendorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Vendor::query()->with(['centers', 'kyc'])->latest();

        if ($request->user()?->hasRole('vendor')) {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json($query->paginate((int) $request->input('per_page', 25)));
    }

    public function store(StoreVendorRequest $request): JsonResponse
    {
        $vendor = Vendor::create([
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);
        $vendor->transitionStatus('pending_verification');

        return response()->json($vendor->fresh(), 201);
    }

    public function show(Vendor $vendor): JsonResponse
    {
        $this->authorizeVendorRead($vendor);

        return response()->json($vendor->load(['centers', 'kyc', 'verifications.verifier', 'invoices']));
    }

    public function update(Request $request, Vendor $vendor): JsonResponse
    {
        $this->authorizeVendorWrite($vendor);
        if (! in_array($vendor->status->value, ['draft', 'rejected'], true)) {
            return response()->json(['message' => 'Vendor cannot be edited in current status.'], 422);
        }

        $vendor->update($request->only([
            'legal_name', 'trade_name', 'gst', 'cin', 'email', 'phone',
            'address_line1', 'address_line2', 'city', 'state', 'pincode', 'entity_type',
        ]));

        return response()->json($vendor->fresh());
    }

    public function destroy(Vendor $vendor): JsonResponse
    {
        $this->authorize('vendors.update', $vendor);
        $vendor->delete();

        return response()->json(null, 204);
    }

    public function transition(TransitionRequest $request, Vendor $vendor): JsonResponse
    {
        return $this->doTransition($request, $vendor);
    }

    public function addCenter(Request $request, Vendor $vendor): JsonResponse
    {
        $this->authorizeVendorWrite($vendor);

        $data = $request->validate([
            'name' => ['required', 'string'],
            'code' => ['required', 'string', 'max:32'],
            'address_line1' => ['required', 'string'],
            'city' => ['required', 'string'],
            'state' => ['required', 'string'],
            'pincode' => ['required', 'regex:/^\d{6}$/'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        $center = $vendor->centers()->create($data);

        return response()->json($center, 201);
    }

    public function inspectCenter(Request $request, Vendor $vendor, VendorCenter $center): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['inspector', 'admin']), 403);
        $data = $request->validate([
            'score' => ['required', 'numeric', 'between:0,100'],
            'remarks' => ['nullable', 'string'],
        ]);

        $center->update([
            'infrastructure_score' => $data['score'],
            'inspected_at' => now(),
            'inspected_by' => $request->user()->id,
        ]);
        $center->transitionStatus('inspected', $request->user()->id, $data['remarks'] ?? null, ['score' => $data['score']]);

        return response()->json($center->fresh());
    }

    public function transitionCenter(TransitionRequest $request, Vendor $vendor, VendorCenter $center): JsonResponse
    {
        return $this->doTransition($request, $center);
    }

    public function upsertKyc(Request $request, Vendor $vendor): JsonResponse
    {
        $this->authorizeVendorWrite($vendor);
        $data = $request->validate([
            'bank_name' => ['required', 'string'],
            'account_holder_name' => ['required', 'string'],
            'account_number' => ['required', 'string', 'min:6', 'max:20'],
            'ifsc' => ['required', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
        ]);

        $kyc = VendorKyc::firstOrNew(['vendor_id' => $vendor->id]);
        $kyc->fill([
            'bank_name' => $data['bank_name'],
            'account_holder_name' => $data['account_holder_name'],
            'ifsc' => $data['ifsc'],
        ]);
        $kyc->account_number = $data['account_number'];
        $kyc->status = 'pending';
        $kyc->save();

        return response()->json($kyc->makeHidden('account_number_encrypted'));
    }

    public function transitionKyc(TransitionRequest $request, Vendor $vendor): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['finance', 'admin']), 403);
        $kyc = $vendor->kyc;
        abort_if($kyc === null, 404, 'KYC not submitted.');

        $kyc->update([
            'status' => $request->validated('to'),
            'verified_at' => $request->validated('to') === 'verified' ? now() : null,
            'verified_by' => $request->user()->id,
            'remarks' => $request->validated('remarks'),
        ]);

        return response()->json($kyc->fresh());
    }

    private function doTransition(TransitionRequest $request, mixed $model): JsonResponse
    {
        try {
            $model->transitionStatus(
                $request->validated('to'),
                $request->user()->id,
                $request->validated('remarks'),
                $request->validated('meta') ?? [],
            );
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($model->fresh());
    }

    private function authorizeVendorRead(Vendor $vendor): void
    {
        $u = request()->user();
        if (! $u) {
            abort(401);
        }
        if ($u->hasAnyRole(['admin', 'finance', 'inspector'])) {
            return;
        }
        abort_unless($u->hasRole('vendor') && $vendor->user_id === $u->id, 403);
    }

    private function authorizeVendorWrite(Vendor $vendor): void
    {
        $u = request()->user();
        if (! $u) {
            abort(401);
        }
        if ($u->hasRole('admin')) {
            return;
        }
        abort_unless($u->hasRole('vendor') && $vendor->user_id === $u->id, 403);
    }
}
