<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReceiptStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Portal\PortalDonorResource;
use App\Http\Resources\Portal\PortalReceiptResource;
use App\Models\Receipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Portal Donatur — donatur hanya dapat melihat data miliknya sendiri.
 */
class PortalController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        $donor = $request->user()->donor;

        abort_if($donor === null, 404, 'Akun ini belum tertaut dengan data donatur.');

        return (new PortalDonorResource($donor))->response();
    }

    public function donations(Request $request): JsonResponse
    {
        $donor = $request->user()->donor;

        abort_if($donor === null, 404, 'Akun ini belum tertaut dengan data donatur.');

        $receipts = Receipt::query()
            ->where('donor_id', $donor->id)
            ->where('status', ReceiptStatus::APPROVED->value)
            ->with([
                'account:id,code,name',
                'allocations.fund:id,code,name',
                'allocations.program:id,code,name',
            ])
            ->orderByDesc('receipt_date')
            ->paginate($request->integer('per_page', 15));

        return PortalReceiptResource::collection($receipts)->response();
    }

    public function summary(Request $request): JsonResponse
    {
        $donor = $request->user()->donor;

        abort_if($donor === null, 404, 'Akun ini belum tertaut dengan data donatur.');

        $total = Receipt::where('donor_id', $donor->id)
            ->where('status', ReceiptStatus::APPROVED->value)
            ->sum('amount');

        return response()->json([
            'donor' => (new PortalDonorResource($donor))->resolve(),
            'total_donasi' => bcadd((string) $total, '0', 2),
            'jumlah_transaksi' => Receipt::where('donor_id', $donor->id)
                ->where('status', ReceiptStatus::APPROVED->value)->count(),
        ]);
    }
}
