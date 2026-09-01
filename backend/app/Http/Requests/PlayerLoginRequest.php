<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlayerLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'pin' => ['required', 'digits_between:4,12'],
            'new_player' => ['sometimes', 'boolean'],
        ];
    }
}
