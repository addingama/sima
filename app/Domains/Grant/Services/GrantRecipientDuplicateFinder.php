<?php

namespace App\Domains\Grant\Services;

use App\Enums\GrantApplicationStatus;
use App\Models\GrantApplication;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class GrantRecipientDuplicateFinder
{
    /**
     * @param  iterable<GrantApplication>  $grants
     */
    public function attach(iterable $grants, User $viewer): void
    {
        $targets = [];
        foreach ($grants as $grant) {
            $targets[] = $grant;
        }

        if ($targets === []) {
            return;
        }

        $grouped = [];
        foreach ($this->candidates() as $candidate) {
            $name = self::normalizeName($candidate->recipient_name);
            if ($name === null) {
                continue;
            }
            $grouped[$name][] = $candidate;
        }

        foreach ($targets as $grant) {
            $name = self::normalizeName($grant->recipient_name);
            $matches = [];
            $completed = 0;

            foreach ($name === null ? [] : ($grouped[$name] ?? []) as $other) {
                if ((int) $other->getKey() === (int) $grant->getKey()) {
                    continue;
                }

                $status = $other->status instanceof GrantApplicationStatus
                    ? $other->status
                    : GrantApplicationStatus::from((string) $other->status);

                if ($status === GrantApplicationStatus::COMPLETED) {
                    $completed++;
                }

                $matches[] = [
                    'id' => $other->getKey(),
                    'application_number' => $other->application_number,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'reason' => $other->reason,
                    'nik_relation' => self::nikRelation($grant->recipient_identity_number, $other->recipient_identity_number),
                    'viewable' => Gate::forUser($viewer)->allows('view', $other),
                ];
            }

            usort($matches, fn (array $left, array $right): int => strcmp(
                (string) $left['application_number'],
                (string) $right['application_number'],
            ));

            $grant->nameMatchSummary = [
                'warning' => $matches !== [],
                'matches' => $matches,
                'completed_assistance_count' => $completed,
            ];
        }
    }

    public static function normalizeName(?string $name): ?string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim((string) $name)) ?? '';
        $normalized = mb_strtolower($normalized);

        return $normalized === '' ? null : $normalized;
    }

    public static function nikRelation(?string $left, ?string $right): string
    {
        $leftNik = self::normalizeNik($left);
        $rightNik = self::normalizeNik($right);

        if ($leftNik === null || $rightNik === null) {
            return 'unknown';
        }

        return $leftNik === $rightNik ? 'same' : 'different';
    }

    /** @return list<GrantApplication> */
    private function candidates(): array
    {
        return GrantApplication::query()
            ->where('status', '!=', GrantApplicationStatus::REJECTED->value)
            ->orderBy('application_number')
            ->get([
                'id',
                'application_number',
                'status',
                'recipient_name',
                'recipient_identity_number',
                'reason',
                'created_by',
                'assigned_verifier_id',
                'assigned_handover_id',
            ])
            ->all();
    }

    private static function normalizeNik(?string $nik): ?string
    {
        $normalized = preg_replace('/[\s-]+/', '', trim((string) $nik)) ?? '';

        return $normalized === '' ? null : $normalized;
    }
}
