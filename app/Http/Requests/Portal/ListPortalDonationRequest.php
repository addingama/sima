<?php

namespace App\Http\Requests\Portal;

use App\Http\Requests\Concerns\HasListQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListPortalDonationRequest extends FormRequest
{
    use HasListQuery;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->listQueryRules(['receipt_date', 'receipt_number', 'amount', 'created_at']);
    }
}
