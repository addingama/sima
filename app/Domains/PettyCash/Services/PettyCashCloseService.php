<?php

namespace App\Domains\PettyCash\Services;

use App\Domains\Receipt\Services\ReceiptService;
use App\Enums\PettyCashFloatStatus;
use App\Enums\ReceiptStatus;
use App\Exceptions\DomainException;
use App\Models\PettyCashFloat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PettyCashCloseService
{
    public function __construct(
        private readonly PettyCashFundingService $fundings,
        private readonly ReceiptService $receipts,
    ) {}

    /**
     * @param  array{returned_amount?: string|null, account_id?: int|null, receipt_date?: string|null}  $data
     */
    public function close(PettyCashFloat $float, array $data, User $actor): PettyCashFloat
    {
        if ($float->status !== PettyCashFloatStatus::REPORTING) {
            throw new DomainException('Hanya float reporting yang dapat ditutup.');
        }

        $returned = bcadd((string) ($data['returned_amount'] ?? '0'), '0', 2);
        if (bccomp($returned, '0', 2) === -1) {
            throw ValidationException::withMessages([
                'returned_amount' => ['Nominal kembalian tidak boleh negatif.'],
            ]);
        }

        $funded = $float->fundedTotal();
        $spent = $float->spentTotal();
        $expected = bcadd($spent, $returned, 2);

        if (bccomp($expected, $funded, 2) !== 0) {
            throw ValidationException::withMessages([
                'returned_amount' => [
                    "Tidak seimbang: belanja ({$spent}) + kembalian ({$returned}) harus sama dengan cair ({$funded}).",
                ],
            ]);
        }

        return DB::transaction(function () use ($float, $data, $actor, $returned): PettyCashFloat {
            $receiptId = null;

            if (bccomp($returned, '0', 2) === 1) {
                $accountId = $data['account_id'] ?? null;
                if ($accountId === null) {
                    throw ValidationException::withMessages([
                        'account_id' => ['Akun kas/bank wajib diisi saat ada sisa dikembalikan.'],
                    ]);
                }

                $fundId = $this->fundings->lockedFundId($float);
                if ($fundId === null) {
                    throw new DomainException('Dana Amanah float tidak ditemukan dari funding.');
                }

                $receipt = $this->receipts->create([
                    'receipt_date' => $data['receipt_date'] ?? now()->toDateString(),
                    'account_id' => $accountId,
                    'amount' => $returned,
                    'channel' => 'transfer',
                    'reference_number' => $float->float_number,
                    'description' => 'Kembalian kas kecil '.$float->float_number,
                ], [
                    ['fund_id' => $fundId, 'amount' => $returned],
                ], $actor);

                $receiptId = $receipt->id;
            }

            $float->update([
                'status' => PettyCashFloatStatus::CLOSED,
                'returned_amount' => $returned,
                'return_receipt_id' => $receiptId,
                'closed_at' => now(),
            ]);

            return $float->fresh([
                'custodian:id,name,email',
                'program:id,code,name',
                'createdBy:id,name',
                'lines.category:id,code,name',
                'fundings.disbursement:id,disbursement_number,status,amount,disbursement_date',
                'topupRequests',
                'returnReceipt:id,receipt_number,status,amount',
            ]);
        });
    }

    public function reopen(PettyCashFloat $float): PettyCashFloat
    {
        if ($float->status !== PettyCashFloatStatus::CLOSED) {
            throw new DomainException('Hanya float closed yang dapat dibuka kembali.');
        }

        if ($float->return_receipt_id !== null) {
            $float->loadMissing('returnReceipt');
            if ($float->returnReceipt?->status === ReceiptStatus::APPROVED) {
                throw new DomainException('Tidak dapat reopen: penerimaan kembalian sudah di-approve.');
            }
        }

        return DB::transaction(function () use ($float): PettyCashFloat {
            $float->update([
                'status' => PettyCashFloatStatus::REPORTING,
                'closed_at' => null,
                'returned_amount' => '0.00',
                'return_receipt_id' => null,
            ]);

            return $float->refresh();
        });
    }
}
