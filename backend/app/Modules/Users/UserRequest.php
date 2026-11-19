<?php

namespace App\Modules\Users;

use App\Models\User;
use App\Modules\Auth\Role;
use App\Modules\Leads\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target
            ? $this->user()->can('update', $target)
            : $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($target)],
            'phone' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail) {
                if (filled($value) && ! PhoneNormalizer::normalize($value)) {
                    $fail('Enter a valid mobile number.');
                }
            }],
            'role' => ['required', Rule::enum(Role::class)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'active' => ['boolean'],
            'password' => [$target ? 'nullable' : 'required', 'string', Password::min(8)],
        ];
    }

    /** Validated data with the phone stored in +91 E.164 form. */
    public function userData(): array
    {
        $data = $this->validated();
        if (array_key_exists('phone', $data)) {
            $data['phone'] = PhoneNormalizer::normalize($data['phone']);
        }

        return $data;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $target = $this->route('user');
            if ($target && $target->is($this->user())
                && ($this->input('role') !== Role::SuperAdmin->value || ! $this->boolean('active', true))) {
                $validator->errors()->add('role', 'You cannot remove your own super admin access or deactivate yourself.');
            }
        }];
    }
}
