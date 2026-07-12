<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventStoreRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],

            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'event_date' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date'],

            'shot_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'participant_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],

            'reveal_time' => ['sometimes', 'nullable', Rule::in([
                Event::REVEAL_INSTANT,
                Event::REVEAL_END_OF_EVENT,
                Event::REVEAL_SCHEDULED,
            ])],

            // Only meaningful for a scheduled reveal, and required there —
            // otherwise the gallery would never flip. Note the absence of
            // "sometimes": it would skip every rule below when the field is
            // missing, which is exactly the case requiredIf has to catch.
            'reveal_at' => [
                'nullable',
                'date',
                Rule::requiredIf(fn (): bool => $this->input('reveal_time') === Event::REVEAL_SCHEDULED),
            ],

            'filter' => ['sometimes', 'nullable', Rule::in(['none', 'warm', 'film', 'mono'])],
            'tier' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_revealed' => ['sometimes', 'boolean'],
            'cover_image_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }
}
