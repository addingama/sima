<?php

namespace App\Http\Requests\Audit;

use App\Http\Requests\Concerns\HasListQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListAuditRequest extends FormRequest
{
    use HasListQuery;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge($this->listQueryRules(['id', 'created_at'], withCursor: true), [
            'auditable_type' => ['nullable', 'string'],
            'auditable_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'event' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
    }
}
