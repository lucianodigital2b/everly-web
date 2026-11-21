<?php

namespace App\Support;

/**
 * Request fields every DeepSeek text call shares (see `services.deepseek.thinking`).
 */
class DeepSeekParams
{
    /** @return array<string,mixed> */
    public static function extra(): array
    {
        return config('services.deepseek.thinking', 'disabled') === 'disabled'
            ? ['thinking' => ['type' => 'disabled']]
            : [];
    }
}
