<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Messaging\DeliveryResult;
use App\Messaging\Notifier;

/** Captures WhatsApp messages in tests. */
final class SpyNotifier implements Notifier
{
    /** @var list<array{to: string, text: string}> */
    public array $sent = [];

    public bool $accept = true;

    public function whatsapp(string $to, string $text): DeliveryResult
    {
        $this->sent[] = ['to' => $to, 'text' => $text];

        return $this->accept ? DeliveryResult::accepted('spy-'.count($this->sent)) : DeliveryResult::failed('spy: refused');
    }

    public function lastCode(): string
    {
        preg_match('/\b(\d{6})\b/', $this->sent[array_key_last($this->sent)]['text'] ?? '', $m);

        return $m[1] ?? '';
    }
}
