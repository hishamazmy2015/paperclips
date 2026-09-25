<?php

declare(strict_types=1);

use Anthropic\Client;
use App\Content\ClaudeGenerator;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** A PSR-18 client that answers every request with one canned message and records the request. */
final class CannedTransport implements ClientInterface
{
    public ?RequestInterface $last = null;

    public function __construct(private readonly array $body) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->last = $request;

        return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($this->body));
    }
}

function claudeMessage(array $content, string $stopReason = 'end_turn'): array
{
    return ['id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5', 'content' => $content, 'stop_reason' => $stopReason, 'stop_sequence' => null, 'usage' => ['input_tokens' => 10, 'output_tokens' => 20]];
}

it('asks for structured JSON per field and locale and maps it back', function (): void {
    $answer = [
        'identity_tagline__en' => 'Straight answers in Marina.',
        'identity_tagline__ar' => 'إجابات واضحة في المارينا.',
        'seo_meta_description__en' => 'Properties for sale and rent in Marina.',
        'seo_meta_description__ar' => 'عقارات للبيع والإيجار في المارينا.',
    ];
    $transport = new CannedTransport(claudeMessage([['type' => 'text', 'text' => json_encode($answer)]]));
    $generator = new ClaudeGenerator(new Client(apiKey: 'test-key', requestOptions: ['transporter' => $transport]), 'claude-opus-5');

    $out = $generator->generate(
        ['identity' => ['display_name' => 'Sara Mansoori', 'agency_name' => 'Falasi Properties'], 'content' => ['service_areas' => ['Marina']]],
        ['identity.tagline', 'seo.meta_description', 'content.why_me'],
        ['en', 'ar'],
    );

    expect($out)->toBe([
        'identity.tagline' => ['en' => 'Straight answers in Marina.', 'ar' => 'إجابات واضحة في المارينا.'],
        'seo.meta_description' => ['en' => 'Properties for sale and rent in Marina.', 'ar' => 'عقارات للبيع والإيجار في المارينا.'],
    ]);

    $request = $transport->last;
    expect($request)->not->toBeNull();
    $body = json_decode((string) $request->getBody(), true);
    expect((string) $request->getUri())->toContain('/v1/messages')
        ->and($request->getHeaderLine('x-api-key'))->toBe('test-key')
        ->and($request->getHeaderLine('anthropic-beta'))->toContain('server-side-fallback-2026-07-01')
        ->and($body['model'])->toBe('claude-opus-5')
        ->and($body['fallbacks'])->toBe('default')
        ->and($body['output_config']['format']['type'])->toBe('json_schema')
        ->and(array_keys($body['output_config']['format']['schema']['properties']))->toBe(['identity_tagline__en', 'identity_tagline__ar', 'seo_meta_description__en', 'seo_meta_description__ar'])
        ->and($body['messages'][0]['content'])->toContain('Sara Mansoori')->toContain('Falasi Properties')->toContain('Marina')
        ->and($body['system'])->toContain('never invent');
});

it('treats a refusal as a failure so the template text stays', function (): void {
    $transport = new CannedTransport(claudeMessage([], 'refusal'));
    $generator = new ClaudeGenerator(new Client(apiKey: 'k', requestOptions: ['transporter' => $transport]));

    expect(fn () => $generator->generate(['identity' => ['display_name' => 'X Y']], ['identity.tagline'], ['en']))->toThrow(RuntimeException::class);
    expect($generator->generate([], ['unknown.field'], ['en']))->toBe([]);
});
