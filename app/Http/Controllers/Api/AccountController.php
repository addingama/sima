<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreAccountRequest;
use App\Http\Requests\Master\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Account::class);

        $accounts = Account::query()
            ->leftJoin('account_balances', 'account_balances.account_id', '=', 'accounts.id')
            ->select('accounts.*', DB::raw('COALESCE(account_balances.balance, 0) as balance'))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('accounts.name', 'like', '%'.$request->string('q').'%')
                ->orWhere('accounts.code', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('type'), fn ($q) => $q->where('accounts.type', $request->string('type')))
            ->orderBy('accounts.name')
            ->paginate($request->integer('per_page', 15));

        return AccountResource::collection($accounts)->response();
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        $account = Account::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return (new AccountResource($account))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Account $account): JsonResponse
    {
        $this->authorize('view', $account);

        $account->setAttribute('balance', $this->ledger->balanceForAccount($account->id));

        return (new AccountResource($account))->response();
    }

    public function update(UpdateAccountRequest $request, Account $account): JsonResponse
    {
        $account->update($request->validated());

        return (new AccountResource($account))->response();
    }

    public function destroy(Account $account): JsonResponse
    {
        $this->authorize('delete', $account);

        if (bccomp($this->ledger->balanceForAccount($account->id), '0', 2) !== 0) {
            throw new DomainException('Akun dengan saldo tidak nol tidak dapat dihapus.');
        }

        $account->delete();

        return response()->json(['message' => 'Akun kas/bank dinonaktifkan (soft delete).']);
    }
}
