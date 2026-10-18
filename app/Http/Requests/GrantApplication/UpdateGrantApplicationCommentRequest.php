<?php

namespace App\Http\Requests\GrantApplication;

use App\Models\GrantApplication;
use App\Models\GrantApplicationComment;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGrantApplicationCommentRequest extends FormRequest
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
        $comment = $this->route('comment');

        return $grant instanceof GrantApplication
            && $comment instanceof GrantApplicationComment
            && ($this->user()?->can('updateComment', [$grant, $comment]) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
