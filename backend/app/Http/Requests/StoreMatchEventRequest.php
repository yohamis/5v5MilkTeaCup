<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMatchEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'event_date' => ['required', 'date', 'unique:match_events,event_date'],
            'title' => ['sometimes', 'string', 'max:100'],
            'signup_starts_at' => ['nullable', 'date'],
            'signup_ends_at' => ['nullable', 'date', 'after:signup_starts_at'],
            'capacity' => ['sometimes', 'integer', 'min:2', 'max:100'],
            'waitlist_capacity' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'status' => ['sometimes', 'in:draft,open,closed,completed'],
        ];
    }
}
