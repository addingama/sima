<?php

namespace App\Http\Requests\GrantApplication;

use App\Models\GrantApplication;
use Illuminate\Foundation\Http\FormRequest;

class StoreGrantApplicationCommentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->exists('body')) {
            $this->merge(['body' => trim((string) $this->input('body'))]);
        }
    }

    public function authorize(): bool
    {
        $grant = $this->route('grant_application');

        return $grant instanceof GrantApplication
            && ($this->user()?->can('comment', $grant) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
