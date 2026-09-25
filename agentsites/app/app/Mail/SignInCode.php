<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** S1 (spec §13): the 6-digit code and a magic link in the same email. Sent in the UI locale. */
final class SignInCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $code, public readonly string $magicUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('platform.mail.code_subject', ['code' => $this->code]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.sign-in-code', text: 'mail.sign-in-code-text');
    }
}
