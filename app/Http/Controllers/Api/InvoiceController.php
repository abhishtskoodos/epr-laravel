<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransitionRequest;
use App\Models\CandidateEnrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Scheme;
use App\Models\SchemePaymentMilestone;
use App\Services\SchemeLogicEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
    public function __construct(private readonly SchemeLogicEngine $engine) {}

    public function index(Request $request): JsonResponse
    {
        $q = Invoice::query()->with(['vendor', 'scheme'])->latest();
        if ($request->user()?->hasRole('vendor')) {
            $q->where('vendor_id', $request->user()->vendor?->id);
        }

        return response()->json($q->paginate((int) $request->input('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('invoices.create'), 403);
        $data = $request->validate([
            'scheme_id' => ['required', 'exists:schemes,id'],
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'enrollment_ids' => ['required', 'array', 'min:1'],
            'enrollment_ids.*' => ['integer', 'exists:candidate_enrollments,id'],
        ]);

        return DB::transaction(function () use ($request, $data) {
            $vendor = $request->user()->vendor;
            abort_if($vendor === null, 422, 'Authenticated user has no vendor profile.');

            $scheme = Scheme::with(['paymentMilestones', 'jobRoles'])->findOrFail($data['scheme_id']);
            $enrollments = CandidateEnrollment::with(['batch.scheme', 'candidate'])
                ->whereIn('id', $data['enrollment_ids'])
                ->get();

            $invoice = Invoice::create([
                'invoice_no' => 'INV-'.Str::upper(Str::random(8)),
                'vendor_id' => $vendor->id,
                'scheme_id' => $scheme->id,
                'period_from' => $data['period_from'],
                'period_to' => $data['period_to'],
            ]);

            foreach ($enrollments as $enrollment) {
                foreach ($this->engine->payableMilestones($enrollment) as $row) {
                    /** @var SchemePaymentMilestone $milestone */
                    $milestone = $row['milestone'];
                    if ($milestone->scheme_id !== $scheme->id) {
                        continue;
                    }

                    $invoice->items()->updateOrCreate(
                        [
                            'candidate_enrollment_id' => $enrollment->id,
                            'scheme_payment_milestone_id' => $milestone->id,
                        ],
                        [
                            'invoice_id' => $invoice->id,
                            'amount' => $row['amount'],
                            'is_eligible' => $row['eligible'],
                            'eligibility_reason' => $row['reason'],
                            'status' => 'pending',
                        ],
                    );
                }
            }

            $invoice->recalculateTotals();

            return response()->json($invoice->load('items'), 201);
        });
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json($invoice->load(['items.enrollment.candidate', 'items.milestone', 'payments', 'verifications']));
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($invoice->status->value === 'draft', 422, 'Only draft invoices can be edited.');
        $invoice->update($request->only(['period_from', 'period_to', 'remarks']));

        return response()->json($invoice->fresh());
    }

    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($invoice->status->value === 'draft', 422, 'Only draft invoices can be deleted.');
        $invoice->delete();

        return response()->json(null, 204);
    }

    public function submit(Request $request, Invoice $invoice): JsonResponse
    {
        $invoice->transitionStatus('submitted', $request->user()->id);
        $invoice->update(['submitted_at' => now()]);

        return response()->json($invoice->fresh());
    }

    public function transition(TransitionRequest $request, Invoice $invoice): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['finance', 'admin']), 403);
        $invoice->transitionStatus(
            $request->validated('to'),
            $request->user()->id,
            $request->validated('remarks'),
            $request->validated('meta') ?? [],
        );
        if ($invoice->status->value === 'approved') {
            $invoice->update(['approved_at' => now(), 'approved_by' => $request->user()->id]);
        }

        return response()->json($invoice->fresh());
    }

    public function recordPayment(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($request->user()?->can('payments.record'), 403);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'mode' => ['required', 'in:neft,rtgs,imps,upi,cheque'],
            'utr' => ['nullable', 'string', 'max:64'],
            'paid_on' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($invoice, $request, $data) {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $data['amount'],
                'mode' => $data['mode'],
                'utr' => $data['utr'] ?? null,
                'paid_on' => $data['paid_on'],
                'paid_by' => $request->user()->id,
                'status' => 'success',
                'remarks' => $data['remarks'] ?? null,
            ]);

            $paid = (float) $invoice->payments()->where('status', 'success')->sum('amount');
            $next = $paid >= (float) $invoice->total ? 'paid' : 'partially_paid';
            if ($invoice->status->value !== $next) {
                $invoice->transitionStatus($next, $request->user()->id);
                if ($next === 'paid') {
                    $invoice->update(['paid_at' => now()]);
                }
            }

            return response()->json($payment->load('invoice'), 201);
        });
    }
}
