<?php

namespace App\Http\Requests;

use App\Services\DeciderVoteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeciderVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', Rule::in([
                DeciderVoteService::MATCHMAKING,
                DeciderVoteService::RANKED,
            ])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mode.required' => '请选择决胜局模式',
            'mode.in' => '决胜局模式无效',
        ];
    }
}
