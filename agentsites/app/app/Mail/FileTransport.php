<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

/**
 * MAIL_MAILER=file: every message becomes a JSON file under storage/app/private/mail-sink
 * (dev, staging without Mailpit, and the Playwright suite, which reads the sign-in code from it).
 */
final class FileTransport extends AbstractTransport
{
    public const DIRECTORY = 'mail-sink';

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();
        if (! $original instanceof Message) {
            return;
        }
        $email = MessageConverter::toEmail($original);

        Storage::disk('local')->put(
            self::DIRECTORY.'/'.now()->format('Ymd-His-u').'-'.Str::lower(Str::random(6)).'.json',
            (string) json_encode([
                'to' => array_map(static fn (Address $address): string => $address->getAddress(), $email->getTo()),
                'subject' => $email->getSubject(),
                'text' => $email->getTextBody(),
                'html' => $email->getHtmlBody(),
                'sent_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    public function __toString(): string
    {
        return 'file://'.self::DIRECTORY;
    }
}
