<?php

namespace App\Http\Requests\Role;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $this->user()?->hasRole(UserRole::ADMIN->value)
            && $role instanceof Role
            && $role->name !== UserRole::ADMIN->value;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::in(config('sima.permissions', []))],
        ];
    }
}
