<?php

namespace App\Http\Requests\Receipt;

use App\Http\Requests\Concerns\ReceiptAllocationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreReceiptRequest extends FormRequest
{
    use ReceiptAllocationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('receipt.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'receipt_date' => ['required', 'date'],
            'account_id' => ['required', 'exists:accounts,id'],
            'donor_id' => ['nullable', 'exists:donors,id'],
            'channel' => ['required', 'in:cash,transfer,qris,other'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
            ...$this->allocationRules(required: true),
        ];
    }

    /** @return array<string, mixed> */
    public function receiptData(): array
    {
        return $this->safe()->except('allocations');
    }

    /** @return array<int, array<string, mixed>> */
    public function allocations(): array
    {
        return $this->validated('allocations');
    }
}
