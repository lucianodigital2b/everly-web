<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    /**
     * Answer a wedding planning question.
     *
     * Free for every signed-in user. The `chat` throttle (per user, per minute
     * and per day) is what bounds the spend.
     */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:40'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:4000'],
            'language' => ['nullable', 'string', 'max:10'],
            'context' => ['nullable', 'array'],
        ]);

        try {
            return response()->json([
                'reply' => $this->chat->reply(
                    $data['messages'],
                    (string) ($data['language'] ?? 'en'),
                    $data['context'] ?? [],
                ),
            ]);
        } catch (Throwable $e) {
            Log::error('[Chat] '.$e->getMessage());

            return response()->json(['error' => 'Chat failed'], 502);
        }
    }
}
