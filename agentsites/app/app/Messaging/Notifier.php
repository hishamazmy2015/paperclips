<?php

declare(strict_types=1);

namespace App\Messaging;

/**
 * WhatsApp delivery behind one interface (spec §6): Dialog360Notifier in production,
 * NullNotifier in dev/tests. Implementations report failure through DeliveryResult and
 * never throw for provider errors — publishing must not depend on them (spec §22.3).
 */
interface Notifier
{
    /** @param  string  $to  E.164 number, e.g. +9715XXXXXXXX */
    public function whatsapp(string $to, string $text): DeliveryResult;
}
