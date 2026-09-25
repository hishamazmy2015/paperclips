<?php

declare(strict_types=1);

namespace App\Messaging;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * WhatsApp Business API through 360dialog (spec §6): one text message per call, an 8-second
 * budget, and a DeliveryResult instead of an exception whatever the provider does (§22.3).
 */
final class Dialog360Notifier implements Notifier
{
    public function __construct(private readonly string $apiKey, private readonly string $baseUrl = 'https://waba-v2.360dialog.io') {}

    public function whatsapp(string $to, string $text): DeliveryResult
    {
        if ($this->apiKey === '') {
            return DeliveryResult::failed('360dialog: no API key configured');
        }

        try {
            $response = Http::withHeaders(['D360-API-KEY' => $this->apiKey])
                ->acceptJson()
                ->timeout(8)
                ->post(rtrim($this->baseUrl, '/').'/messages', [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => ltrim($to, '+'),
                    'type' => 'text',
                    'text' => ['preview_url' => true, 'body' => $text],
                ]);

            if ($response->successful()) {
                return DeliveryResult::accepted((string) ($response->json('messages.0.id') ?? ''));
            }

            return DeliveryResult::failed('360dialog '.$response->status().': '.Str::limit($response->body(), 200));
        } catch (Throwable $e) {
            return DeliveryResult::failed('360dialog: '.$e->getMessage());
        }
    }
}
