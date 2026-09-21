<?php

namespace App\Http\Requests\GrantApplication;

use App\Enums\GrantApplicationStatus;
use App\Http\Requests\Concerns\HasListQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListGrantApplicationRequest extends FormRequest
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
            'created_at',
            'application_number',
            'recommended_amount',
            'status',
        ]), [
            'status' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                $allowed = array_map(static fn (GrantApplicationStatus $status) => $status->value, GrantApplicationStatus::cases());
                $items = is_array($value) ? $value : explode(',', (string) $value);

                foreach ($items as $item) {
                    $status = trim((string) $item);
                    if ($status === '') {
                        continue;
                    }
                    if (! in_array($status, $allowed, true)) {
                        $fail('Status tidak valid.');

                        return;
                    }
                }
            }],
            'assigned_verifier_id' => ['nullable', 'integer', 'exists:users,id'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
            'payment_method' => ['nullable', 'in:cash,transfer'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'mine' => ['nullable', 'boolean'],
        ]);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('mine')) {
            return;
        }

        $this->merge([
            'mine' => filter_var($this->input('mine'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
        ]);
    }
}
