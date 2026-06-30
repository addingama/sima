<?php

namespace App\Http\Requests\Report;

use App\Http\Requests\Concerns\HasListQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListOpeningBalanceReportRequest extends FormRequest
{
    use HasListQuery;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge($this->listQueryRules([
            'opening_balance_batches.opening_date',
            'opening_balance_batches.batch_number',
            'opening_balance_lines.line_number',
            'opening_balance_lines.amount',
        ]), [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
        ]);
    }
}
