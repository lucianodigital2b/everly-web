<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Contracts\Validation\Validator;

/**
 * Any subset of the store body, plus `public`. Everything is optional here, so
 * the store request's required rules are relaxed.
 */
class EventUpdateRequest extends EventStoreRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'plan_id' => ['sometimes', 'integer', 'exists:plans,id'],
            'public' => ['sometimes', 'boolean'],

            // The parent makes this conditionally required by inspecting the
            // payload alone, which is wrong for a PATCH. Enforced below instead.
            'reveal_at' => ['sometimes', 'nullable', 'date'],
        ]);
    }

    /**
     * A scheduled reveal is useless without a time, but a PATCH may set only
     * one of the two fields — so validate the event's *resulting* state rather
     * than just what's in the body.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Event|null $event */
            $event = $this->route('event');

            $revealTime = $this->input('reveal_time', $event?->reveal_time);
            $revealAt = $this->has('reveal_at') ? $this->input('reveal_at') : $event?->reveal_at;

            if ($revealTime === Event::REVEAL_SCHEDULED && $revealAt === null) {
                $validator->errors()->add('reveal_at', 'A scheduled reveal needs a reveal_at time.');
            }
        });
    }
}
