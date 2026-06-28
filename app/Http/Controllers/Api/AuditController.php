<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OwenIt\Auditing\Models\Audit;

/** Audit Trail (read-only). */
class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $audits = Audit::query()
            ->with('user:id,name,email')
            ->when($request->filled('auditable_type'), fn ($q) => $q->where('auditable_type', $request->string('auditable_type')))
            ->when($request->filled('auditable_id'), fn ($q) => $q->where('auditable_id', $request->integer('auditable_id')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->string('event')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return response()->json($audits);
    }

    public function show(Audit $audit): JsonResponse
    {
        return response()->json($audit->load('user:id,name,email'));
    }
}
