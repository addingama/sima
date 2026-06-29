<?php

namespace App\Http\Requests\Liability;

use App\Models\OperationalLiability;
use Illuminate\Foundation\Http\FormRequest;

class VoidOperationalLiabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var OperationalLiability $operationalLiability */
        $operationalLiability = $this->route('operationalLiability');

        return $this->user()->can('void', $operationalLiability);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
