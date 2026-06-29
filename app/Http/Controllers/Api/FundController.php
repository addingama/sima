<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreFundRequest;
use App\Http\Requests\Master\UpdateFundRequest;
use App\Http\Resources\FundResource;
use App\Models\Fund;
use App\Domains\Ledger\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FundController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Fund::class);

        $funds = Fund::query()
            ->select('funds.*')
            ->selectSub(
                DB::table('ledger_entries')
                    ->selectRaw('COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0)')
                    ->whereColumn('ledger_entries.ledger_account_id', 'funds.id')
                    ->where('ledger_entries.ledger_account_type', 'fund'),
                'balance'
            )
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('funds.name', 'like', '%'.$request->string('q').'%')
                ->orWhere('funds.code', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('type'), fn ($q) => $q->where('funds.type', $request->string('type')))
            ->orderBy('funds.name')
            ->paginate($request->integer('per_page', 15));

        return FundResource::collection($funds)->response();
    }

    public function store(StoreFundRequest $request): JsonResponse
    {
        $fund = Fund::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'is_system' => false,
        ]);

        return (new FundResource($fund))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Fund $fund): JsonResponse
    {
        $this->authorize('view', $fund);

        $fund->setAttribute('balance', $this->ledger->balanceForFund($fund->id));

        return (new FundResource($fund))->response();
    }

    public function update(UpdateFundRequest $request, Fund $fund): JsonResponse
    {
        if ($fund->is_system) {
            throw new DomainException('Dana sistem tidak dapat diubah.');
        }

        $fund->update($request->validated());

        return (new FundResource($fund))->response();
    }

    public function destroy(Fund $fund): JsonResponse
    {
        $this->authorize('delete', $fund);

        if (bccomp($this->ledger->balanceForFund($fund->id), '0', 2) !== 0) {
            throw new DomainException('Dana dengan saldo tidak nol tidak dapat dihapus.');
        }

        $fund->delete();

        return response()->json(['message' => 'Dana Amanah dinonaktifkan (soft delete).']);
    }
}
