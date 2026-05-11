<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $emailRules = [
            'required',
            'string',
            'lowercase',
            'email',
            'max:255',
            Rule::unique(User::class)->ignore($this->user()->id),
        ];

        if (! (bool) $this->user()?->is_admin) {
            $emailRules[] = Rule::in([(string) $this->user()?->email]);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:80'],
            'email' => $emailRules,
        ];
    }

    public function messages(): array
    {
        return [
            'email.in' => 'Usuário comum não pode alterar o e-mail cadastrado.',
        ];
    }
}
