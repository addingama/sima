<?php

namespace App\Services\Portal;

use App\Enums\ReceiptStatus;
use App\Http\Resources\Portal\PortalDonorResource;
use App\Models\Donor;
use App\Models\Receipt;
use App\Models\User;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PortalService
{
    public function resolveDonor(User $user): Donor
    {
        $donor = $user->donor;

        if ($donor === null) {
            throw new NotFoundHttpException('Akun ini belum tertaut dengan data donatur.');
        }

        return $donor;
    }

    public function profile(User $user): Donor
    {
        return $this->resolveDonor($user);
    }

    public function donations(User $user, ListQueryDto $query): LengthAwarePaginator
    {
        $donor = $this->resolveDonor($user);

        return Receipt::query()
            ->where('donor_id', $donor->id)
            ->where('status', ReceiptStatus::APPROVED->value)
            ->with([
                'account:id,code,name',
                'allocations.fund:id,code,name',
                'allocations.program:id,code,name',
            ])
            ->orderByDesc('receipt_date')
            ->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    /** @return array<string, mixed> */
    public function summary(User $user): array
    {
        $donor = $this->resolveDonor($user);

        $total = Receipt::where('donor_id', $donor->id)
            ->where('status', ReceiptStatus::APPROVED->value)
            ->sum('amount');

        return [
            'donor' => (new PortalDonorResource($donor))->resolve(),
            'total_donasi' => bcadd((string) $total, '0', 2),
            'jumlah_transaksi' => Receipt::where('donor_id', $donor->id)
                ->where('status', ReceiptStatus::APPROVED->value)
                ->count(),
        ];
    }
}
