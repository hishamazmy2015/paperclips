<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Abandonment reminder (spec §13): +1 h and +24 h, with a resume link and an opt-out link. */
final class OnboardingReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $resumeUrl,
        public readonly string $optOutUrl,
        public readonly string $kind,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('platform.mail.reminder_subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.onboarding-reminder', text: 'mail.onboarding-reminder-text');
    }
}
