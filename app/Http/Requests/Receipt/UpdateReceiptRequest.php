<?php

namespace App\Http\Requests\Receipt;

use App\Enums\ReceiptStatus;
use App\Http\Requests\Concerns\ReceiptAllocationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReceiptRequest extends FormRequest
{
    use ReceiptAllocationRules;

    public function authorize(): bool
    {
        $receipt = $this->route('receipt');

        return $this->user()?->can('receipt.create')
            && $receipt?->status === ReceiptStatus::DRAFT;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'receipt_date' => ['sometimes', 'date'],
            'account_id' => ['sometimes', 'exists:accounts,id'],
            'donor_id' => ['nullable', 'exists:donors,id'],
            'channel' => ['sometimes', 'in:cash,transfer,qris,other'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
            ...$this->allocationRules(required: false),
        ];
    }

    /** @return array<string, mixed> */
    public function receiptData(): array
    {
        return $this->safe()->except('allocations');
    }

    /** @return array<int, array<string, mixed>>|null */
    public function allocations(): ?array
    {
        return $this->has('allocations') ? $this->validated('allocations') : null;
    }
}
