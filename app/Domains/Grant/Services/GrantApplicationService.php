<?php

namespace App\Domains\Grant\Services;

use App\Domains\Grant\DTOs\CreateGrantApplicationDto;
use App\Domains\Grant\DTOs\UpdateGrantApplicationDto;
use App\Domains\Grant\Repositories\GrantApplicationRepository;
use App\Domains\Grant\Validators\GrantApplicationValidator;
use App\Enums\GrantApplicationStatus;
use App\Enums\GrantPaymentMethod;
use App\Exceptions\DomainException;
use App\Models\Disbursement;
use App\Models\GrantApplication;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class GrantApplicationService
{
    private const DRAFT_FIELDS = [
        'recipient_name',
        'recipient_phone',
        'recipient_address',
        'recipient_identity_number',
        'recommended_amount',
        'reason',
        'recommender_name',
        'recommender_contact',
        'payment_method',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'notes',
        'program_id',
        'assigned_verifier_id',
    ];

    private const VERIFICATION_FIELDS = [
        'recipient_name',
        'recipient_phone',
        'recipient_address',
        'recipient_identity_number',
        'verified_amount',
        'payment_method',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'verifier_notes',
        'program_id',
    ];

    public function __construct(
        private readonly GrantApplicationRepository $repository,
        private readonly GrantApplicationValidator $validator,
        private readonly DocumentNumberService $numbers,
    ) {}

    public function paginate(ListQueryDto $query, User $viewer): LengthAwarePaginator
    {
        return $this->repository->paginate($query, $viewer);
    }

    public function findForShow(GrantApplication $grant): GrantApplication
    {
        return $grant->load([
            'assignedVerifier:id,name',
            'program:id,code,name',
            'createdBy:id,name',
            'handedOverBy:id,name',
            'disbursement:id,disbursement_number,status,amount',
            'attachments',
        ]);
    }

    public function create(array $data, User $actor): GrantApplication
    {
        return $this->createFromDto(new CreateGrantApplicationDto($data, $actor));
    }

    public function createFromDto(CreateGrantApplicationDto $dto): GrantApplication
    {
        $payload = $this->normalizeAmounts($dto->data, ['recommended_amount']);
        $payload['payment_method'] = $payload['payment_method'] ?? GrantPaymentMethod::CASH->value;

        if (! empty($payload['assigned_verifier_id'])) {
            $this->validator->assertAssignableVerifier(
                User::query()->findOrFail((int) $payload['assigned_verifier_id'])
            );
        }

        return DB::transaction(function () use ($dto, $payload): GrantApplication {
            return $this->repository->create([
                ...$payload,
                'application_number' => $dto->data['application_number'] ?? $this->numbers->next('BNT'),
                'status' => GrantApplicationStatus::DRAFT->value,
                'created_by' => $dto->actor->getKey(),
            ]);
        });
    }

    public function update(GrantApplication $grant, array $data, User $actor): GrantApplication
    {
        return $this->updateFromDto(new UpdateGrantApplicationDto($data, $actor), $grant);
    }

    public function updateFromDto(UpdateGrantApplicationDto $dto, GrantApplication $grant): GrantApplication
    {
        $allowed = $grant->status === GrantApplicationStatus::DRAFT
            ? self::DRAFT_FIELDS
            : self::VERIFICATION_FIELDS;

        $this->validator->assertStatus($grant, [
            GrantApplicationStatus::DRAFT,
            GrantApplicationStatus::VERIFICATION,
        ]);

        $payload = array_intersect_key($dto->data, array_flip($allowed));
        $payload = $this->normalizeAmounts($payload, ['recommended_amount', 'verified_amount']);

        if (array_key_exists('assigned_verifier_id', $payload) && $payload['assigned_verifier_id']) {
            $this->validator->assertAssignableVerifier(
                User::query()->findOrFail((int) $payload['assigned_verifier_id'])
            );
        }

        return DB::transaction(function () use ($grant, $payload): GrantApplication {
            return $this->repository->update($grant, $payload);
        });
    }

    public function assign(GrantApplication $grant, int $verifierId, User $actor): GrantApplication
    {
        $this->validator->assertStatus($grant, [
            GrantApplicationStatus::DRAFT,
            GrantApplicationStatus::VERIFICATION,
        ]);

        $verifier = User::query()->findOrFail($verifierId);
        $this->validator->assertAssignableVerifier($verifier);

        return DB::transaction(function () use ($grant, $verifierId): GrantApplication {
            return $this->repository->update($grant, [
                'assigned_verifier_id' => $verifierId,
            ]);
        });
    }

    public function sendToVerification(GrantApplication $grant, User $actor): GrantApplication
    {
        $this->validator->assertStatus($grant, [GrantApplicationStatus::DRAFT]);
        $this->validator->assertReadyForVerification($grant);

        return DB::transaction(function () use ($grant, $actor): GrantApplication {
            return $this->repository->update($grant, [
                'status' => GrantApplicationStatus::VERIFICATION->value,
                'verified_amount' => $grant->verified_amount ?? $grant->recommended_amount,
                'sent_to_verification_at' => now(),
                'sent_to_verification_by' => $actor->getKey(),
                'return_reason' => null,
            ]);
        });
    }

    public function submitForApproval(GrantApplication $grant, User $actor): GrantApplication
    {
        $this->validator->assertStatus($grant, [GrantApplicationStatus::VERIFICATION]);

        if ($grant->verified_amount === null) {
            $grant->verified_amount = $grant->recommended_amount;
        }

        $this->validator->assertReadyForApproval($grant);

        return DB::transaction(function () use ($grant, $actor): GrantApplication {
            return $this->repository->update($grant, [
                'status' => GrantApplicationStatus::PENDING_APPROVAL->value,
                'verified_amount' => $grant->verified_amount,
                'submitted_for_approval_at' => now(),
                'submitted_for_approval_by' => $actor->getKey(),
            ]);
        });
    }

    public function approve(GrantApplication $grant, User $actor, ?string $amount, ?string $notes): GrantApplication
    {
        $this->validator->assertStatus($grant, [GrantApplicationStatus::PENDING_APPROVAL]);

        $approved = bcadd((string) ($amount ?? $grant->verified_amount ?? $grant->recommended_amount), '0', 2);
        $this->validator->assertApprovedAmount($grant, $approved);

        return DB::transaction(function () use ($grant, $actor, $approved, $notes): GrantApplication {
            return $this->repository->update($grant, [
                'status' => GrantApplicationStatus::APPROVED->value,
                'approved_amount' => $approved,
                'decision_notes' => $notes,
                'approved_at' => now(),
                'approved_by' => $actor->getKey(),
                'rejected_at' => null,
                'rejected_by' => null,
                'rejection_reason' => null,
            ]);
        });
    }

    public function reject(GrantApplication $grant, User $actor, string $reason): GrantApplication
    {
        $this->validator->assertStatus($grant, [GrantApplicationStatus::PENDING_APPROVAL]);

        return DB::transaction(function () use ($grant, $actor, $reason): GrantApplication {
            return $this->repository->update($grant, [
                'status' => GrantApplicationStatus::REJECTED->value,
                'rejection_reason' => $reason,
                'rejected_at' => now(),
                'rejected_by' => $actor->getKey(),
            ]);
        });
    }

    public function returnToVerification(GrantApplication $grant, User $actor, string $reason): GrantApplication
    {
        $this->validator->assertStatus($grant, [GrantApplicationStatus::PENDING_APPROVAL]);

        return DB::transaction(function () use ($grant, $actor, $reason): GrantApplication {
            return $this->repository->update($grant, [
                'status' => GrantApplicationStatus::VERIFICATION->value,
                'return_reason' => $reason,
                'returned_at' => now(),
                'returned_by' => $actor->getKey(),
                'submitted_for_approval_at' => null,
                'submitted_for_approval_by' => null,
            ]);
        });
    }

    public function linkDisbursement(GrantApplication $grant, Disbursement $disbursement): GrantApplication
    {
        $this->validator->assertStatus($grant, [GrantApplicationStatus::APPROVED]);

        if ($grant->disbursement_id !== null) {
            throw new DomainException('Pengajuan ini sudah tertaut ke pengeluaran.');
        }

        $alreadyLinked = GrantApplication::query()
            ->where('disbursement_id', $disbursement->id)
            ->exists();

        if ($alreadyLinked) {
            throw new DomainException('Pengeluaran ini sudah tertaut ke pengajuan lain.');
        }

        $approved = bcadd((string) $grant->approved_amount, '0', 2);
        $expenseAmount = bcadd((string) $disbursement->amount, '0', 2);
        if (bccomp($approved, $expenseAmount, 2) !== 0) {
            throw new DomainException(
                "Nominal pengeluaran ({$expenseAmount}) harus sama dengan nominal disetujui ({$approved})."
            );
        }

        return DB::transaction(function () use ($grant, $disbursement): GrantApplication {
            return $this->repository->update($grant, [
                'disbursement_id' => $disbursement->id,
            ]);
        });
    }

    public function complete(GrantApplication $grant, User $actor, string $handedOverOn): GrantApplication
    {
        $grant->loadMissing('disbursement', 'attachments');
        $this->validator->assertReadyToComplete($grant);

        return DB::transaction(function () use ($grant, $actor, $handedOverOn): GrantApplication {
            return $this->repository->update($grant, [
                'status' => GrantApplicationStatus::COMPLETED->value,
                'handed_over_on' => $handedOverOn,
                'handed_over_at' => now(),
                'handed_over_by' => $actor->getKey(),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function normalizeAmounts(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $data[$key] = bcadd((string) $data[$key], '0', 2);
            }
        }

        return $data;
    }
}
