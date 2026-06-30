<?php

namespace App\Domains\Opening\Services;

use App\Domains\Ledger\Services\LedgerService;
use App\Domains\Opening\Validators\OpeningBalanceValidator;
use App\Enums\LedgerMovement;
use App\Enums\TransactionType;
use App\Models\OpeningBalanceBatch;
use App\Models\OpeningBalanceLine;
use App\Models\User;
use App\Services\DocumentNumberService;
use Illuminate\Support\Facades\DB;

class OpeningBalanceService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly DocumentNumberService $numbers,
        private readonly OpeningBalanceValidator $validator,
    ) {}

    public function findForShow(OpeningBalanceBatch $batch): OpeningBalanceBatch
    {
        return $batch->load([
            'lines.account:id,code,name',
            'lines.fund:id,code,name',
            'postedBy:id,name,email',
            'createdBy:id,name,email',
        ]);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): OpeningBalanceBatch
    {
        return DB::transaction(function () use ($data, $actor): OpeningBalanceBatch {
            $lines = $data['lines'];
            $this->validator->assertLines($lines);

            $total = '0.00';
            foreach ($lines as $line) {
                $total = bcadd($total, bcadd((string) $line['amount'], '0', 2), 2);
            }

            $batchNumber = $this->numbers->next('OPN');
            $reference = $data['reference'] ?? "Saldo awal {$batchNumber}";

            $batch = OpeningBalanceBatch::create([
                'batch_number' => $batchNumber,
                'opening_date' => $data['opening_date'],
                'reference' => $data['reference'] ?? null,
                'total_amount' => $total,
                'posted_at' => now(),
                'posted_by' => $actor->getKey(),
                'created_by' => $actor->getKey(),
            ]);

            foreach (array_values($lines) as $index => $line) {
                $amount = bcadd((string) $line['amount'], '0', 2);
                $accountId = (int) $line['account_id'];
                $fundId = (int) $line['fund_id'];

                OpeningBalanceLine::create([
                    'opening_balance_batch_id' => $batch->id,
                    'line_number' => $index + 1,
                    'account_id' => $accountId,
                    'fund_id' => $fundId,
                    'amount' => $amount,
                ]);

                $this->ledger->postAmanahMovement(
                    TransactionType::OPENING,
                    $batch->id,
                    $accountId,
                    [['fund_id' => $fundId, 'amount' => $amount]],
                    LedgerMovement::IN,
                    $reference,
                );
            }

            return $batch->refresh();
        });
    }
}
