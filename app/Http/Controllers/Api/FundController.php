<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\Fund;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FundController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function index(Request $request): JsonResponse
    {
        // Saldo di-join dari cache materialized (O(1) per baris, tanpa N+1).
        $funds = Fund::query()
            ->leftJoin('fund_balances', 'fund_balances.fund_id', '=', 'funds.id')
            ->select('funds.*', DB::raw('COALESCE(fund_balances.balance, 0) as balance'))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('funds.name', 'like', '%'.$request->string('q').'%')
                ->orWhere('funds.code', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('type'), fn ($q) => $q->where('funds.type', $request->string('type')))
            ->orderBy('funds.name')
            ->paginate($request->integer('per_page', 15));

        return response()->json($funds);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:funds,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:restricted,unrestricted'],
            'is_active' => ['boolean'],
        ]);
        $data['created_by'] = $request->user()->id;
        $data['is_system'] = false;

        $fund = Fund::create($data);

        return response()->json($fund, 201);
    }

    public function show(Fund $fund): JsonResponse
    {
        $fund->balance = $this->ledger->balanceForFund($fund->id);

        return response()->json($fund);
    }

    public function update(Request $request, Fund $fund): JsonResponse
    {
        if ($fund->is_system) {
            throw new DomainException('Dana sistem tidak dapat diubah.');
        }

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', 'unique:funds,code,'.$fund->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', 'in:restricted,unrestricted'],
            'is_active' => ['boolean'],
        ]);

        $fund->update($data);

        return response()->json($fund);
    }

    public function destroy(Fund $fund): JsonResponse
    {
        if ($fund->is_system) {
            throw new DomainException('Dana sistem tidak dapat dihapus.');
        }

        if (bccomp($this->ledger->balanceForFund($fund->id), '0', 2) !== 0) {
            throw new DomainException('Dana dengan saldo tidak nol tidak dapat dihapus.');
        }

        $fund->delete();

        return response()->json(['message' => 'Dana Amanah dinonaktifkan (soft delete).']);
    }
}
