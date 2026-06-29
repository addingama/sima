<?php

namespace App\Http\Controllers\Api;

use App\Domains\Audit\Repositories\AuditLogRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OwenIt\Auditing\Models\Audit;

/** Audit Trail (read-only). */
class AuditController extends Controller
{
    public function __construct(private readonly AuditLogRepository $audits) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Audit::class);

        $audits = $this->audits->paginate([
            'auditable_type' => $request->filled('auditable_type') ? $request->string('auditable_type')->value() : null,
            'auditable_id' => $request->filled('auditable_id') ? $request->integer('auditable_id') : null,
            'user_id' => $request->filled('user_id') ? $request->integer('user_id') : null,
            'event' => $request->filled('event') ? $request->string('event')->value() : null,
            'from' => $request->filled('from') ? $request->date('from') : null,
            'to' => $request->filled('to') ? $request->date('to') : null,
        ], $request->integer('per_page', 25));

        return response()->json($audits);
    }

    public function show(Audit $audit): JsonResponse
    {
        $this->authorize('view', $audit);

        return response()->json($audit->load('user:id,name,email'));
    }
}
