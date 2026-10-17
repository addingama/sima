<?php

namespace App\Http\Requests\PettyCash;

use App\Models\PettyCashTopupRequest;
use Illuminate\Foundation\Http\FormRequest;

class RejectPettyCashTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PettyCashTopupRequest $topup */
        $topup = $this->route('pettyCashTopupRequest');

        return $this->user()->can('fund', $topup->float);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
