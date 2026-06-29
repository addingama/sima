<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\FundStatementRequest;
use App\Http\Requests\Report\ListLedgerRequest;
use App\Http\Resources\FundResource;
use App\Http\Resources\LedgerEntryResource;
use App\Services\Report\ReportService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $service) {}

    #[OA\Get(
        path: '/reports/fund-balances',
        summary: 'Saldo seluruh Dana Amanah',
        tags: ['Report'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function fundBalances(): JsonResponse
    {
        $result = $this->service->fundBalances();

        return $this->ok(['rows' => $result['rows'], 'total' => $result['total']]);
    }

    #[OA\Get(
        path: '/reports/account-balances',
        summary: 'Saldo seluruh kas/bank',
        tags: ['Report'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function accountBalances(): JsonResponse
    {
        $result = $this->service->accountBalances();

        return $this->ok(['rows' => $result['rows'], 'total' => $result['total']]);
    }

    #[OA\Get(
        path: '/reports/ledger',
        summary: 'Buku besar (ledger)',
        tags: ['Report'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function ledger(ListLedgerRequest $request): JsonResponse
    {
        return $this->collection(LedgerEntryResource::collection($this->service->ledger($request->listQuery(50))));
    }

    #[OA\Get(
        path: '/reports/reconciliation-summary',
        summary: 'Rekonsiliasi global Amanah',
        tags: ['Report'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function reconciliationSummary(): JsonResponse
    {
        return $this->ok($this->service->reconciliationSummary());
    }

    #[OA\Get(
        path: '/reports/fund-statement',
        summary: 'Mutasi per Dana Amanah',
        tags: ['Report'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function fundStatement(FundStatementRequest $request): JsonResponse
    {
        $result = $this->service->fundStatement(
            $request->integer('fund_id'),
            $request->filled('from') ? $request->date('from')->toDateString() : null,
            $request->filled('to') ? $request->date('to')->toDateString() : null,
        );

        return $this->ok([
            'fund' => $result['fund'] ? (new FundResource($result['fund']))->resolve() : null,
            'inflow' => $result['inflow'],
            'outflow' => $result['outflow'],
            'net' => $result['net'],
        ]);
    }
}
