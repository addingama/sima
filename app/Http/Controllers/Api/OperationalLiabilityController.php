<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Liability\SettleOperationalLiabilityRequest;
use App\Http\Requests\Liability\StoreOperationalLiabilityRequest;
use App\Http\Requests\Liability\UpdateOperationalLiabilityRequest;
use App\Http\Requests\Liability\VoidOperationalLiabilityRequest;
use App\Models\OperationalLiability;
use App\Services\OperationalLiabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalLiabilityController extends Controller
{
    public function __construct(private readonly OperationalLiabilityService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OperationalLiability::class);

        $items = OperationalLiability::query()
            ->with(['fund:id,code,name', 'program:id,code,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('fund_id'), fn ($q) => $q->where('fund_id', $request->integer('fund_id')))
            ->orderByDesc('liability_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    public function store(StoreOperationalLiabilityRequest $request): JsonResponse
    {
        $liability = $this->service->create($request->validated(), $request->user());

        return response()->json($liability, 201);
    }

    public function show(OperationalLiability $operationalLiability): JsonResponse
    {
        $this->authorize('view', $operationalLiability);

        return response()->json($operationalLiability->load([
            'fund:id,code,name', 'program:id,code,name', 'settledDisbursement', 'attachments',
        ]));
    }

    public function update(UpdateOperationalLiabilityRequest $request, OperationalLiability $operationalLiability): JsonResponse
    {
        return response()->json(
            $this->service->update($operationalLiability, $request->validated(), $request->user())
        );
    }

    public function settle(SettleOperationalLiabilityRequest $request, OperationalLiability $operationalLiability): JsonResponse
    {
        return response()->json(
            $this->service->settle(
                $operationalLiability,
                $request->integer('disbursement_id'),
                $request->user()
            )
        );
    }

    public function void(VoidOperationalLiabilityRequest $request, OperationalLiability $operationalLiability): JsonResponse
    {
        return response()->json(
            $this->service->void($operationalLiability, $request->validated('reason'), $request->user())
        );
    }
}
