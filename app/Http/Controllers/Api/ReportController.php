<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Invoice;
use App\Models\Trainer;
use App\Models\Vendor;
use App\Models\Verification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;

class ReportController extends Controller
{
    public function mis(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('reports.view'), 403);

        return response()->json([
            'vendors' => Vendor::query()->select('status', DB::raw('COUNT(*) as c'))->groupBy('status')->pluck('c', 'status'),
            'candidates' => Candidate::query()->select('status', DB::raw('COUNT(*) as c'))->groupBy('status')->pluck('c', 'status'),
            'trainers' => Trainer::query()->select('status', DB::raw('COUNT(*) as c'))->groupBy('status')->pluck('c', 'status'),
            'invoices' => Invoice::query()->select('status', DB::raw('COUNT(*) as c, SUM(total) as amt'))->groupBy('status')->get(),
        ]);
    }

    public function audits(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('audit.view'), 403);
        $q = Audit::query()->latest('created_at');
        if ($request->filled('auditable_type')) {
            $q->where('auditable_type', $request->string('auditable_type'));
        }
        if ($request->filled('auditable_id')) {
            $q->where('auditable_id', $request->integer('auditable_id'));
        }
        if ($request->filled('user_id')) {
            $q->where('user_id', $request->integer('user_id'));
        }

        return response()->json($q->paginate((int) $request->input('per_page', 50)));
    }

    public function verifications(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('audit.view'), 403);
        $q = Verification::query()->with('verifier:id,name,email')->latest('created_at');
        if ($request->filled('verifiable_type')) {
            $q->where('verifiable_type', $request->string('verifiable_type'));
        }
        if ($request->filled('verifier_id')) {
            $q->where('verifier_id', $request->integer('verifier_id'));
        }
        if ($request->filled('to_status')) {
            $q->where('to_status', $request->string('to_status'));
        }

        return response()->json($q->paginate((int) $request->input('per_page', 50)));
    }
}
