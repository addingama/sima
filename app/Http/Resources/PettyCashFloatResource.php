<?php

namespace App\Http\Resources;

use App\Models\PettyCashFloat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PettyCashFloat */
class PettyCashFloatResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $funded = $this->relationLoaded('fundings')
            ? $this->fundedTotal()
            : null;
        $spent = $this->relationLoaded('lines')
            ? $this->spentTotal()
            : null;

        return [
            'id' => $this->id,
            'float_number' => $this->float_number,
            'title' => $this->title,
            'purpose' => $this->purpose?->value,
            'status' => $this->status?->value,
            'custodian_user_id' => $this->custodian_user_id,
            'custodian' => $this->whenLoaded('custodian', fn () => [
                'id' => $this->custodian->id,
                'name' => $this->custodian->name,
                'email' => $this->custodian->email ?? null,
            ]),
            'program_id' => $this->program_id,
            'program' => $this->whenLoaded('program'),
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'return_receipt_id' => $this->return_receipt_id,
            'returned_amount' => (string) $this->returned_amount,
            'funded_total' => $funded,
            'spent_total' => $spent,
            'remaining_total' => ($funded !== null && $spent !== null)
                ? bcsub(bcsub($funded, $spent, 2), (string) $this->returned_amount, 2)
                : null,
            'notes' => $this->notes,
            'funded_at' => $this->funded_at,
            'closed_at' => $this->closed_at,
            'created_by' => $this->created_by,
            'created_by_user' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'lines' => PettyCashLineResource::collection($this->whenLoaded('lines')),
            'fundings' => $this->whenLoaded('fundings', function () {
                return $this->fundings->map(fn ($f) => [
                    'id' => $f->id,
                    'kind' => $f->kind?->value,
                    'amount' => (string) $f->amount,
                    'disbursement_id' => $f->disbursement_id,
                    'disbursement' => $f->relationLoaded('disbursement') && $f->disbursement
                        ? [
                            'id' => $f->disbursement->id,
                            'disbursement_number' => $f->disbursement->disbursement_number,
                            'status' => $f->disbursement->status?->value,
                            'amount' => (string) $f->disbursement->amount,
                            'disbursement_date' => $f->disbursement->disbursement_date,
                        ]
                        : null,
                    'notes' => $f->notes,
                ]);
            }),
            'topup_requests' => $this->whenLoaded('topupRequests'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
