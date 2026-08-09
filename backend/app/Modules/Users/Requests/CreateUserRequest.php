<?php

namespace App\Modules\Users\Requests;

use App\Core\Auth\AvatarGroup;
use App\Core\Auth\Support\EmailNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => EmailNormalizer::normalize((string) $this->input('email')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer'],
            'send_invite' => ['sometimes', 'boolean'],
            'temporary_password' => ['required_if:send_invite,false', 'nullable', 'string', 'confirmed', Password::defaults()],
            'temporary_password_confirmation' => ['required_with:temporary_password', 'nullable', 'string'],
            'avatar_group' => ['sometimes', Rule::enum(AvatarGroup::class)],
        ];
    }

    /**
     * @return array{name: string, email: string, role_ids?: list<int>, send_invite?: bool, temporary_password?: string|null, avatar_group?: string}
     */
    public function validatedPayload(): array
    {
        /** @var array{name: string, email: string, role_ids?: list<int>, send_invite?: bool, temporary_password?: string|null, avatar_group?: string} */
        return $this->validated();
    }
}
