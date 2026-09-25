<?php

declare(strict_types=1);

namespace App\Messaging;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * WHATSAPP_PROVIDER=file: messages become JSON files under storage/app/private/whatsapp-sink
 * (dev/staging without a WhatsApp account; the Playwright suite reads the welcome message).
 */
final class FileNotifier implements Notifier
{
    public const DIRECTORY = 'whatsapp-sink';

    public function whatsapp(string $to, string $text): DeliveryResult
    {
        $id = 'file-'.Str::lower(Str::random(10));
        Storage::disk('local')->put(
            self::DIRECTORY.'/'.now()->format('Ymd-His-u').'-'.$id.'.json',
            (string) json_encode(['id' => $id, 'to' => $to, 'text' => $text, 'sent_at' => now()->toIso8601String()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        return DeliveryResult::accepted($id);
    }
}
