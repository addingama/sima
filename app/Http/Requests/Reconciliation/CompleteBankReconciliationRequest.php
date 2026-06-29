<?php

namespace App\Http\Requests\Reconciliation;

use App\Models\BankReconciliation;
use Illuminate\Foundation\Http\FormRequest;

class CompleteBankReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var BankReconciliation $bankReconciliation */
        $bankReconciliation = $this->route('bankReconciliation');

        return $this->user()->can('complete', $bankReconciliation);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
