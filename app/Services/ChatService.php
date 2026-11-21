<?php

namespace App\Services;

use App\Support\DeepSeekParams;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The in-app wedding planning assistant (the chat behind the app's FAB).
 *
 * Text only, on DeepSeek: it is free for every user, so it runs on the
 * cheapest model that answers well. Ported from DailyHair's ChatService.
 */
class ChatService
{
    /** Bounded so a long thread cannot grow the bill without limit. */
    private const MAX_HISTORY = 12;

    // DeepSeek's V4 models bill their reasoning pass against max_tokens, so a
    // budget sized for the answer alone can come back empty.
    private const MAX_TOKENS = 6000;

    /**
     * @param  array<int,array{role:string,content:string}>  $messages
     * @param  array<string,mixed>  $context
     */
    public function reply(array $messages, string $language = 'en', array $context = []): string
    {
        $config = config('services.deepseek');

        if (empty($config['api_key'])) {
            throw new RuntimeException('DeepSeek is not configured.');
        }

        $payload = [
            'model' => $config['model'],
            'temperature' => 0.6,
            'max_tokens' => self::MAX_TOKENS,
            'messages' => $this->buildMessages($messages, $language, $context),
        ] + DeepSeekParams::extra();

        $response = Http::withToken($config['api_key'])
            ->timeout((int) ($config['timeout'] ?? 45))
            ->acceptJson()
            ->post(rtrim((string) $config['base_url'], '/').'/chat/completions', $payload);

        if ($response->failed()) {
            Log::error('[Chat] provider returned '.$response->status().': '.$response->body());
            throw new RuntimeException('Chat provider request failed.');
        }

        $reply = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($reply) || trim($reply) === '') {
            $finish = data_get($response->json(), 'choices.0.finish_reason');
            throw new RuntimeException("Chat provider returned an empty completion (finish_reason={$finish}).");
        }

        return trim($reply);
    }

    /**
     * @param  array<int,array{role:string,content:string}>  $messages
     * @param  array<string,mixed>  $context
     * @return array<int,array{role:string,content:string}>
     */
    private function buildMessages(array $messages, string $language, array $context): array
    {
        $out = [[
            'role' => 'system',
            'content' => $this->systemPrompt($language).$this->contextBlock($context),
        ]];

        // Keep only the tail of the conversation, and only well-formed turns.
        $history = array_slice(array_values(array_filter(
            $messages,
            fn ($m) => is_array($m)
                && in_array($m['role'] ?? '', ['user', 'assistant'], true)
                && is_string($m['content'] ?? null)
                && trim($m['content']) !== ''
        )), -self::MAX_HISTORY);

        foreach ($history as $message) {
            $out[] = [
                'role' => $message['role'],
                'content' => mb_substr($message['content'], 0, 2000),
            ];
        }

        return $out;
    }

    /**
     * Renders what the app knows about this wedding into the system prompt, so
     * answers are about their wedding rather than weddings in general.
     *
     * The planner lives on the device (checklist, guests and budget are local
     * stores), so the client sends a summary with every message. It sends
     * counts only: never guest names, phones, emails or addresses.
     *
     * @param  array<string,mixed>  $context
     */
    private function contextBlock(array $context): string
    {
        $lines = [];

        $couple = $this->arr($context, 'couple');
        $names = array_values(array_filter([
            $this->str($couple, 'partnerA'),
            $this->str($couple, 'partnerB'),
        ]));
        if ($names) {
            $lines[] = 'Couple: '.implode(' & ', $names).'.';
        }

        $wedding = $this->arr($context, 'wedding');
        $date = $this->str($wedding, 'date');
        if ($date !== null) {
            $line = 'Wedding on '.$date;
            if (($wedding['dateEstimated'] ?? false) === true) {
                $line .= ' (date estimated)';
            }
            if (is_numeric($wedding['daysLeft'] ?? null)) {
                $line .= ', '.(int) $wedding['daysLeft'].' days left';
            }
            if ($phase = $this->str($wedding, 'phase')) {
                $line .= ', current phase: '.$phase;
            }
            $lines[] = $line.'.';
        }

        $profile = [];
        if ($role = $this->str($wedding, 'role')) {
            $profile[] = 'Planning as: '.$role.'.';
        }
        if ($type = $this->str($wedding, 'type')) {
            $profile[] = 'Type: '.$type.'.';
        }
        if ($worry = $this->str($wedding, 'worry')) {
            $profile[] = 'Biggest worry: '.$worry.'.';
        }
        if (is_numeric($wedding['guestEstimate'] ?? null)) {
            $profile[] = 'Guest estimate: '.(int) $wedding['guestEstimate'].'.';
        }
        if ($profile) {
            $lines[] = implode(' ', $profile);
        }

        $checklist = $this->arr($context, 'checklist');
        if (is_numeric($checklist['done'] ?? null) && is_numeric($checklist['total'] ?? null)) {
            $line = 'Checklist: '.(int) $checklist['done'].' of '.(int) $checklist['total'].' done';
            if (is_numeric($checklist['overdue'] ?? null)) {
                $line .= ', '.(int) $checklist['overdue'].' overdue';
            }
            if (is_numeric($checklist['essentialsLeft'] ?? null)) {
                $line .= ', '.(int) $checklist['essentialsLeft'].' essentials left';
            }
            $line .= '.';
            if ($next = $this->str($checklist, 'next')) {
                $line .= ' Next: '.$next.'.';
            }
            $open = $this->strings($checklist['openThisPhase'] ?? null, 10);
            if ($open) {
                $line .= ' Open in this phase: '.implode('; ', $open).'.';
            }
            $lines[] = $line;
        }

        $guests = $this->arr($context, 'guests');
        if (is_numeric($guests['total'] ?? null) && (int) $guests['total'] > 0) {
            $rsvp = [];
            foreach (['attending', 'waiting', 'declined'] as $status) {
                if (is_numeric($guests[$status] ?? null)) {
                    $rsvp[] = (int) $guests[$status].' '.$status;
                }
            }
            $line = 'Guests: list of '.(int) $guests['total'].($rsvp ? ' ('.implode(', ', $rsvp).')' : '');
            foreach (['plusOnes' => 'plus-ones', 'children' => 'children', 'babies' => 'babies'] as $key => $label) {
                if (is_numeric($guests[$key] ?? null) && (int) $guests[$key] > 0) {
                    $line .= ', '.(int) $guests[$key].' '.$label;
                }
            }
            $lines[] = $line.'.';
        }

        $budget = $this->arr($context, 'budget');
        if (is_numeric($budget['total'] ?? null) || is_numeric($budget['estimated'] ?? null)) {
            $currency = $this->str($budget, 'currency');
            $parts = [];
            if (is_numeric($budget['total'] ?? null)) {
                $parts[] = 'total '.$this->money($budget['total']).($currency ? ' '.$currency : '');
            }
            if (is_numeric($budget['estimated'] ?? null)) {
                $parts[] = 'estimated '.$this->money($budget['estimated']);
            }
            if (is_numeric($budget['paid'] ?? null)) {
                $parts[] = 'paid '.$this->money($budget['paid']);
            }
            $line = 'Budget: '.implode('; ', $parts).'.';

            $categories = [];
            foreach (array_slice(is_array($budget['categories'] ?? null) ? $budget['categories'] : [], 0, 12) as $category) {
                if (! is_array($category) || ! ($name = $this->str($category, 'name'))) {
                    continue;
                }
                $categories[] = $name.' '.$this->money($category['estimated'] ?? 0).' est / '
                    .$this->money($category['paid'] ?? 0).' paid';
            }
            if ($categories) {
                $line .= ' '.implode('; ', $categories).'.';
            }

            $next = $this->arr($budget, 'nextPayment');
            if (($due = $this->str($next, 'dueDate')) && ($label = $this->str($next, 'label'))) {
                $line .= ' Next payment due '.$due.': '.$label;
                if ($category = $this->str($next, 'category')) {
                    $line .= ' ('.$category.')';
                }
                if (is_numeric($next['amount'] ?? null)) {
                    $line .= ', '.$this->money($next['amount']);
                }
                $line .= '.';
            }
            $lines[] = $line;
        }

        if (is_bool($context['premium'] ?? null)) {
            $lines[] = 'Premium: '.($context['premium'] ? 'yes' : 'no').'.';
        }

        if ($lines === []) {
            return '';
        }

        return "\n\nWhat you know about this wedding:\n- ".implode("\n- ", $lines)
            ."\n\nUse this. Do not ask them to repeat what is already listed here, and"
            .' tailor every suggestion to it. If something here contradicts what they'
            .' now say, believe them and say you have noted the change.';
    }

    /** @return array<string,mixed> */
    private function arr(array $from, string $key): array
    {
        return is_array($from[$key] ?? null) ? $from[$key] : [];
    }

    private function str(array $from, string $key): ?string
    {
        $value = $from[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, 120) : null;
    }

    /** @return array<int,string> */
    private function strings(mixed $list, int $max): array
    {
        if (! is_array($list)) {
            return [];
        }

        $out = [];
        foreach ($list as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = mb_substr(trim($item), 0, 120);
            }
        }

        return array_slice($out, 0, $max);
    }

    private function money(mixed $value): string
    {
        return number_format(is_numeric($value) ? (float) $value : 0, 0, '.', ',');
    }

    private function languageLine(string $language): string
    {
        $line = str_starts_with(strtolower($language), 'pt')
            ? 'Always answer in Brazilian Portuguese, in a natural, friendly register (use "você").'
            : 'Always answer in English.';

        return $line.' If the person writes in another language, answer in that language.';
    }

    private function systemPrompt(string $language): string
    {
        $langLine = $this->languageLine($language);

        return <<<TXT
        You are the Everly assistant: a calm, organized friend who has helped plan many weddings, now helping this couple plan theirs.

        {$langLine}

        What Everly can do (point them to it instead of suggesting other tools):
        - Plan tab: countdown, a checklist split by phase (12+ months, 7 months, 4 months, 2 months, 1 month, 2 weeks, the day, after), the next task, notes on each task and custom tasks.
        - Guests: groups, RSVP (waiting, attending, declined), plus-ones, menu choice, import from contacts, PDF export. Premium.
        - Budget: a total, categories, expenses with estimated and paid amounts and due dates. Premium.
        - Timeline of the wedding day. Premium.
        - Album: guests scan a QR code and shoot into one shared album, revealed together.

        How to answer:
        - Be brief. Two or three short paragraphs at most, or a short list.
        - Be concrete and about their wedding: use their date, current phase, what is done and what is open. When you suggest a next step, name the task the way it appears in their checklist.
        - Money: use their budget and their currency. Never invent prices or vendor names. Give typical shares (venue and catering often take about half) and say local prices vary.
        - Markdown is supported: use **bold**, lists and links where they help.
        - If you are unsure, say so rather than inventing specifics.
        - You cannot change anything in the app. Never say you added, marked or saved something; tell them where to do it.
        - If what they need is a Premium feature and they are not Premium, mention it once, plainly, then help as far as you can without it.

        Boundaries:
        - Wedding planning, the wedding day and the Everly album only. If asked about something else, say that is outside what you can help with and steer back.
        - Legal requirements (license, documents, deadlines) depend on the country and the venue: give the general checklist and tell them to confirm with the registry office or officiant.
        - Family and partner conflicts: be kind and practical, never take sides.
        TXT;
    }
}
