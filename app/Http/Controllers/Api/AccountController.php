<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function index(Request $request): JsonResponse
    {
        $accounts = Account::query()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('code', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        $accounts->getCollection()->transform(function (Account $account) {
            $account->balance = $this->ledger->balanceForAccount($account->id);

            return $account;
        });

        return response()->json($accounts);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:accounts,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:cash,bank'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'account_holder' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
        $data['created_by'] = $request->user()->id;

        $account = Account::create($data);

        return response()->json($account, 201);
    }

    public function show(Account $account): JsonResponse
    {
        $account->balance = $this->ledger->balanceForAccount($account->id);

        return response()->json($account);
    }

    public function update(Request $request, Account $account): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', 'unique:accounts,code,'.$account->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:cash,bank'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'account_holder' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $account->update($data);

        return response()->json($account);
    }

    public function destroy(Account $account): JsonResponse
    {
        if (bccomp($this->ledger->balanceForAccount($account->id), '0', 2) !== 0) {
            throw new DomainException('Akun dengan saldo tidak nol tidak dapat dihapus.');
        }

        $account->delete();

        return response()->json(['message' => 'Akun kas/bank dinonaktifkan (soft delete).']);
    }
}
