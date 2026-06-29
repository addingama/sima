<?php

namespace App\Http\Requests\Liability;

use App\Models\OperationalLiability;
use Illuminate\Foundation\Http\FormRequest;

class SettleOperationalLiabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var OperationalLiability $operationalLiability */
        $operationalLiability = $this->route('operationalLiability');

        return $this->user()->can('settle', $operationalLiability);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'disbursement_id' => ['required', 'exists:disbursements,id'],
        ];
    }
}
