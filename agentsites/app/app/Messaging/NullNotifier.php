<?php

declare(strict_types=1);

namespace App\Messaging;

use Illuminate\Support\Facades\Log;

/**
 * Development/test notifier: logs the message and reports it as accepted (spec §6).
 */
final class NullNotifier implements Notifier
{
    public function whatsapp(string $to, string $text): DeliveryResult
    {
        Log::info('NullNotifier: whatsapp message not sent', ['to' => $to, 'length' => mb_strlen($text)]);

        return DeliveryResult::accepted('null-'.bin2hex(random_bytes(6)));
    }
}
