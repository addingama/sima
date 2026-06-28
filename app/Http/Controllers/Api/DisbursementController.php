<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Disbursement;
use App\Services\DisbursementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisbursementController extends Controller
{
    public function __construct(private readonly DisbursementService $service) {}

    public function index(Request $request): JsonResponse
    {
        $items = Disbursement::query()
            ->with(['account:id,code,name', 'fund:id,code,name', 'program:id,code,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('fund_id'), fn ($q) => $q->where('fund_id', $request->integer('fund_id')))
            ->when($request->filled('program_id'), fn ($q) => $q->where('program_id', $request->integer('program_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('disbursement_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('disbursement_date', '<=', $request->date('to')))
            ->orderByDesc('disbursement_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'disbursement_date' => ['required', 'date'],
            'account_id' => ['required', 'exists:accounts,id'],
            'fund_id' => ['required', 'exists:funds,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payee' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $disbursement = $this->service->create($data, $request->user());

        return response()->json($disbursement, 201);
    }

    public function show(Disbursement $disbursement): JsonResponse
    {
        return response()->json($disbursement->load([
            'account:id,code,name',
            'fund:id,code,name',
            'program:id,code,name',
            'approvals.actor:id,name',
        ]));
    }

    public function update(Request $request, Disbursement $disbursement): JsonResponse
    {
        abort_unless($disbursement->status->value === 'draft', 422, 'Hanya draft yang dapat diubah.');

        $data = $request->validate([
            'disbursement_date' => ['sometimes', 'date'],
            'account_id' => ['sometimes', 'exists:accounts,id'],
            'fund_id' => ['sometimes', 'exists:funds,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'payee' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $disbursement->update($data);

        return response()->json($disbursement);
    }

    public function submit(Disbursement $disbursement, Request $request): JsonResponse
    {
        return response()->json($this->service->submit($disbursement, $request->user()));
    }

    public function verify(Disbursement $disbursement, Request $request): JsonResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        return response()->json($this->service->verify($disbursement, $request->user(), $data['notes'] ?? null));
    }

    public function approve(Disbursement $disbursement, Request $request): JsonResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        return response()->json($this->service->approve($disbursement, $request->user(), $data['notes'] ?? null));
    }

    public function reject(Disbursement $disbursement, Request $request): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return response()->json($this->service->reject($disbursement, $request->user(), $data['reason']));
    }

    public function reverse(Disbursement $disbursement, Request $request): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return response()->json($this->service->reverse($disbursement, $request->user(), $data['reason']));
    }
}
