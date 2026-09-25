<?php

declare(strict_types=1);

use App\Messaging\Dialog360Notifier;
use App\Messaging\FileNotifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('sends a WhatsApp text through 360dialog and reports provider errors as results', function (): void {
    Http::fake([
        'waba-v2.360dialog.io/messages' => Http::sequence()
            ->push(['messages' => [['id' => 'wamid.1']]], 201)
            ->push(['error' => 'invalid number'], 400),
    ]);
    $notifier = new Dialog360Notifier('secret-key');

    $ok = $notifier->whatsapp('+971501234567', 'Hello');
    expect($ok->accepted)->toBeTrue()->and($ok->providerMessageId)->toBe('wamid.1');
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('D360-API-KEY', 'secret-key')
        && $request['to'] === '971501234567' && $request['text']['body'] === 'Hello' && $request['messaging_product'] === 'whatsapp');

    $failed = $notifier->whatsapp('+971501234567', 'Hello again');
    expect($failed->accepted)->toBeFalse()->and($failed->error)->toContain('400');

    expect((new Dialog360Notifier(''))->whatsapp('+971501234567', 'x')->accepted)->toBeFalse();
});

it('never throws when the provider is unreachable', function (): void {
    Http::fake(fn () => throw new RuntimeException('dns failure'));
    $result = (new Dialog360Notifier('k'))->whatsapp('+971501234567', 'x');
    expect($result->accepted)->toBeFalse()->and($result->error)->toContain('dns failure');
});

it('writes messages to the file sink', function (): void {
    Storage::fake('local');
    $result = (new FileNotifier)->whatsapp('+971501234567', 'Welcome');
    $files = Storage::disk('local')->files(FileNotifier::DIRECTORY);

    expect($result->accepted)->toBeTrue()->and($files)->toHaveCount(1);
    $payload = json_decode((string) Storage::disk('local')->get($files[0]), true);
    expect($payload['to'])->toBe('+971501234567')->and($payload['text'])->toBe('Welcome');
});
