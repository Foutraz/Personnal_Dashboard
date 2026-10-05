<?php

namespace Technical\WebAuthentication\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Technical\WebAuthentication\Rules\AllowedRegistrationEmail;

class RegisterWebRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(AllowedRegistrationEmail $allowedRegistrationEmail): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => ['bail', 'required', 'ascii', 'email', $allowedRegistrationEmail, 'unique:users,email'],
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
